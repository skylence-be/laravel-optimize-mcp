<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Mcp\Tools\Analyzers\Config;

use Illuminate\Support\Facades\File;

final class TelescopeConfigAnalyzer extends AbstractConfigAnalyzer
{
    public function analyze(array &$issues, array &$recommendations, string $environment): void
    {
        // Check if Telescope is installed
        $composerPath = base_path('composer.json');
        if (!File::exists($composerPath)) {
            return;
        }

        $composerData = json_decode(File::get($composerPath), true);
        $installedPackages = array_merge(
            array_keys($composerData['require'] ?? []),
            array_keys($composerData['require-dev'] ?? [])
        );

        $telescopeInstalled = in_array('laravel/telescope', $installedPackages);

        if (!$telescopeInstalled) {
            return;
        }

        // Check if Telescope is enabled in production
        $telescopeEnabled = config('telescope.enabled', true);

        if ($environment === 'production' && $telescopeEnabled === true) {
            $issues[] = [
                'severity' => 'critical',
                'category' => 'performance',
                'config' => 'telescope.enabled',
                'current' => 'true',
                'message' => 'Telescope is enabled in production. This can cause 1,100% memory increase and 50-200ms overhead per request',
                'fix' => 'Set TELESCOPE_ENABLED=false or optimize with minimal watchers (ExceptionWatcher, LogWatcher, JobWatcher, ScheduleWatcher only)',
            ];

            // Check for high-frequency watchers that should be disabled
            $this->checkTelescopeWatchers($issues, $recommendations, $environment);

            // Check pruning configuration
            $this->checkTelescopePruning($issues, $recommendations, $environment);

            // Check ignore paths and commands
            $this->checkTelescopeIgnorePatterns($recommendations);

            // Check TelescopeServiceProvider implementation
            $this->checkTelescopeServiceProvider($issues, $recommendations, $environment);

            // Recommendations for optimization
            $recommendations[] = [
                'category' => 'performance',
                'config' => 'telescope.optimization',
                'message' => 'Consider using Laravel Pulse, New Relic, or Inspector instead of Telescope for production APM',
                'benefit' => 'Purpose-built APM tools have 80-95% less overhead than Telescope',
            ];

            $recommendations[] = [
                'category' => 'performance',
                'config' => 'telescope.deployment',
                'message' => 'Use TELESCOPE_ENABLED=false by default, enable temporarily only when debugging specific issues',
                'benefit' => 'Eliminates performance overhead while maintaining debugging capability when needed',
            ];

            // Check for telescope-flusher package
            if (!in_array('binarcode/laravel-telescope-flusher', $installedPackages)) {
                $recommendations[] = [
                    'category' => 'database',
                    'config' => 'telescope.pruning',
                    'message' => 'Consider using binarcode/laravel-telescope-flusher for better cleanup performance',
                    'benefit' => 'More efficient pruning than built-in telescope:prune command',
                ];
            }
        } elseif ($environment === 'production' && $telescopeEnabled === false) {
            // Telescope is properly disabled in production
            $recommendations[] = [
                'category' => 'monitoring',
                'config' => 'telescope.alternative',
                'message' => 'Telescope is disabled (good!). Consider Laravel Pulse for lightweight production monitoring',
                'benefit' => 'Laravel Pulse provides production-safe monitoring with minimal overhead',
            ];
        }

        // Environment-agnostic checks
        if ($telescopeEnabled) {
            // Check if storage path is configured
            $storagePath = config('telescope.storage.database.connection');
            if (empty($storagePath) || $storagePath === config('database.default')) {
                $recommendations[] = [
                    'category' => 'performance',
                    'config' => 'telescope.storage.database.connection',
                    'message' => 'Use a dedicated database connection for Telescope data',
                    'benefit' => 'Isolates Telescope queries from application queries, improves performance',
                ];
            }
        }
    }

    private function checkTelescopeWatchers(array &$issues, array &$recommendations, string $environment): void
    {
        $watchers = config('telescope.watchers', []);

        // High-frequency watchers that should be disabled in production
        $highFrequencyWatchers = [
            'Laravel\Telescope\Watchers\CacheWatcher' => 'CacheWatcher',
            'Laravel\Telescope\Watchers\EventWatcher' => 'EventWatcher',
            'Laravel\Telescope\Watchers\ModelWatcher' => 'ModelWatcher',
            'Laravel\Telescope\Watchers\RedisWatcher' => 'RedisWatcher',
            'Laravel\Telescope\Watchers\RequestWatcher' => 'RequestWatcher',
            'Laravel\Telescope\Watchers\ViewWatcher' => 'ViewWatcher',
            'Laravel\Telescope\Watchers\QueryWatcher' => 'QueryWatcher',
        ];

        $problematicEnabled = [];

        foreach ($highFrequencyWatchers as $class => $name) {
            $watcherConfig = $watchers[$class] ?? null;

            if ($watcherConfig === true || (is_array($watcherConfig) && ($watcherConfig['enabled'] ?? true) === true)) {
                $problematicEnabled[] = $name;
            }
        }

        if (!empty($problematicEnabled)) {
            $issues[] = [
                'severity' => 'warning',
                'category' => 'performance',
                'config' => 'telescope.watchers',
                'current' => implode(', ', $problematicEnabled) . ' enabled',
                'message' => 'High-frequency watchers enabled in production cause extreme overhead (~90% of performance impact)',
                'fix' => 'Disable these watchers: ' . implode(', ', $problematicEnabled),
            ];
        }

        // Check ModelWatcher hydrations
        $modelWatcher = $watchers['Laravel\Telescope\Watchers\ModelWatcher'] ?? null;
        if (is_array($modelWatcher) && ($modelWatcher['hydrations'] ?? false) === true) {
            $issues[] = [
                'severity' => 'critical',
                'category' => 'performance',
                'config' => 'telescope.watchers.ModelWatcher.hydrations',
                'current' => 'true',
                'message' => 'ModelWatcher hydrations should NEVER be true in production (extreme memory usage)',
                'fix' => 'Set hydrations to false or disable ModelWatcher entirely',
            ];
        }

        // Check LogWatcher level
        $logWatcher = $watchers['Laravel\Telescope\Watchers\LogWatcher'] ?? null;
        if (is_array($logWatcher) && ($logWatcher['enabled'] ?? true) === true) {
            $level = $logWatcher['level'] ?? 'debug';
            if ($level !== 'error') {
                $recommendations[] = [
                    'category' => 'performance',
                    'config' => 'telescope.watchers.LogWatcher.level',
                    'message' => "LogWatcher level is '{$level}', should be 'error' in production",
                    'benefit' => 'Reduces log storage and focuses on critical issues only',
                ];
            }
        }

        // Check RequestWatcher size_limit
        $requestWatcher = $watchers['Laravel\Telescope\Watchers\RequestWatcher'] ?? null;
        if (is_array($requestWatcher) && ($requestWatcher['enabled'] ?? true) === true) {
            $sizeLimit = $requestWatcher['size_limit'] ?? 64;
            if ($sizeLimit > 10) {
                $recommendations[] = [
                    'category' => 'performance',
                    'config' => 'telescope.watchers.RequestWatcher.size_limit',
                    'message' => "RequestWatcher size_limit is {$sizeLimit}KB, reduce to 10KB for production",
                    'benefit' => 'Reduces memory usage and database storage',
                ];
            }
        }

        // Check QueryWatcher slow threshold
        $queryWatcher = $watchers['Laravel\Telescope\Watchers\QueryWatcher'] ?? null;
        if (is_array($queryWatcher) && ($queryWatcher['enabled'] ?? true) === true) {
            $slowThreshold = $queryWatcher['slow'] ?? 0;
            if ($slowThreshold === 0 || $slowThreshold > 100) {
                $recommendations[] = [
                    'category' => 'performance',
                    'config' => 'telescope.watchers.QueryWatcher.slow',
                    'message' => 'Set QueryWatcher slow threshold to 100ms to only capture problematic queries',
                    'benefit' => 'Reduces storage while still catching slow queries',
                ];
            }
        }
    }

    private function checkTelescopePruning(array &$issues, array &$recommendations, string $environment): void
    {
        $pruningEnabled = config('telescope.pruning.enabled', false);
        $pruningRetention = config('telescope.pruning.retention', 168); // Default 168 hours (7 days)

        if (!$pruningEnabled) {
            $issues[] = [
                'severity' => 'critical',
                'category' => 'database',
                'config' => 'telescope.pruning.enabled',
                'current' => 'false',
                'message' => 'Telescope pruning is disabled. Database can bloat to dozens of GB within weeks',
                'fix' => 'Enable pruning: Set lottery [2, 100] and retention to 24-48 hours',
            ];
        } elseif ($pruningRetention > 48) {
            $recommendations[] = [
                'category' => 'database',
                'config' => 'telescope.pruning.retention',
                'message' => "Telescope retention is {$pruningRetention} hours. Reduce to 12-48 hours for production",
                'benefit' => 'Prevents database bloat and improves query performance',
            ];
        }
    }

    private function checkTelescopeIgnorePatterns(array &$recommendations): void
    {
        $ignorePaths = config('telescope.ignore_paths', []);
        $ignoreCommands = config('telescope.ignore_commands', []);

        // Recommended paths to ignore
        $recommendedPaths = ['health', 'horizon*', 'pulse*', 'storage/*', 'css/*', 'js/*', 'vendor/*', '_boost*'];
        $missingPaths = array_filter($recommendedPaths, fn($path) => !in_array($path, $ignorePaths));

        if (!empty($missingPaths)) {
            $recommendations[] = [
                'category' => 'performance',
                'config' => 'telescope.ignore_paths',
                'message' => 'Add more paths to ignore: ' . implode(', ', $missingPaths),
                'benefit' => 'Reduces unnecessary monitoring of assets, health checks, and internal endpoints',
            ];
        }

        // Recommended commands to ignore
        $recommendedCommands = ['schedule:run', 'queue:work', 'horizon'];
        $missingCommands = array_filter($recommendedCommands, fn($cmd) => !in_array($cmd, $ignoreCommands));

        if (!empty($missingCommands)) {
            $recommendations[] = [
                'category' => 'performance',
                'config' => 'telescope.ignore_commands',
                'message' => 'Add high-frequency commands to ignore: ' . implode(', ', $missingCommands),
                'benefit' => 'Prevents overwhelming Telescope with long-running and high-frequency commands',
            ];
        }
    }

    private function checkTelescopeServiceProvider(array &$issues, array &$recommendations, string $environment): void
    {
        $providerPath = app_path('Providers/TelescopeServiceProvider.php');

        if (!File::exists($providerPath)) {
            return;
        }

        $providerContent = File::get($providerPath);

        // Check for filter() method implementation
        $hasFilter = str_contains($providerContent, 'Telescope::filter(');

        if (!$hasFilter) {
            $issues[] = [
                'severity' => 'warning',
                'category' => 'security',
                'config' => 'TelescopeServiceProvider::filter',
                'current' => 'missing',
                'message' => 'Telescope filter() method not implemented - all entries will be recorded',
                'fix' => 'Implement Telescope::filter() to restrict production entries to exceptions, failed jobs, and scheduled tasks',
            ];
        } else {
            // Check if filter is properly restrictive for production
            $hasProperFilter = str_contains($providerContent, 'isReportableException()') ||
                              str_contains($providerContent, 'isFailedRequest()') ||
                              str_contains($providerContent, 'isFailedJob()');

            if (!$hasProperFilter) {
                $recommendations[] = [
                    'category' => 'performance',
                    'config' => 'TelescopeServiceProvider::filter',
                    'message' => 'Telescope filter should restrict production entries to critical events only',
                    'benefit' => 'Use isReportableException(), isFailedRequest(), isFailedJob(), isScheduledTask() for production filtering',
                ];
            }

            // Check if filter respects environment
            $hasEnvironmentCheck = str_contains($providerContent, "environment('local')") ||
                                  str_contains($providerContent, "environment(['local'");

            if (!$hasEnvironmentCheck) {
                $recommendations[] = [
                    'category' => 'performance',
                    'config' => 'TelescopeServiceProvider::filter',
                    'message' => 'Filter should allow all entries in local environment, restricted entries in production',
                    'benefit' => 'Full debugging in development, minimal overhead in production',
                ];
            }
        }

        // Check for hideSensitiveRequestDetails() implementation
        $hasSensitiveHiding = str_contains($providerContent, 'hideSensitiveRequestDetails()');

        if (!$hasSensitiveHiding) {
            $issues[] = [
                'severity' => 'warning',
                'category' => 'security',
                'config' => 'TelescopeServiceProvider::hideSensitiveRequestDetails',
                'current' => 'missing',
                'message' => 'Sensitive request details not being hidden - potential security risk',
                'fix' => 'Implement hideSensitiveRequestDetails() to hide tokens, cookies, and sensitive headers',
            ];
        } else {
            // Check if common sensitive data is hidden
            $hidesTokens = str_contains($providerContent, 'hideRequestParameters') &&
                          str_contains($providerContent, '_token');
            $hidesHeaders = str_contains($providerContent, 'hideRequestHeaders') &&
                           (str_contains($providerContent, 'cookie') || str_contains($providerContent, 'authorization'));

            if (!$hidesTokens) {
                $recommendations[] = [
                    'category' => 'security',
                    'config' => 'TelescopeServiceProvider::hideSensitiveRequestDetails',
                    'message' => 'Ensure _token, password, and other sensitive parameters are hidden',
                    'benefit' => 'Prevents logging sensitive form data and credentials',
                ];
            }

            if (!$hidesHeaders) {
                $recommendations[] = [
                    'category' => 'security',
                    'config' => 'TelescopeServiceProvider::hideSensitiveRequestDetails',
                    'message' => 'Ensure cookie, authorization, and x-csrf-token headers are hidden',
                    'benefit' => 'Prevents logging authentication tokens and session data',
                ];
            }
        }

        // Check for gate() implementation
        $hasGate = str_contains($providerContent, 'function gate()') ||
                   str_contains($providerContent, 'Gate::define(\'viewTelescope\'');

        if (!$hasGate) {
            $issues[] = [
                'severity' => 'critical',
                'category' => 'security',
                'config' => 'TelescopeServiceProvider::gate',
                'current' => 'missing',
                'message' => 'Telescope gate not configured - anyone can access Telescope in non-local environments',
                'fix' => 'Implement gate() method to restrict Telescope access to authorized users only',
            ];
        } else {
            // Check if gate has actual restrictions
            $hasEmailCheck = preg_match('/in_array\s*\(\s*\$user->email/', $providerContent);
            $gateIsEmpty = str_contains($providerContent, 'return in_array($user->email, [') &&
                          preg_match('/\[\s*\/\/\s*\]/', $providerContent);

            if ($gateIsEmpty || !$hasEmailCheck) {
                $issues[] = [
                    'severity' => 'critical',
                    'category' => 'security',
                    'config' => 'TelescopeServiceProvider::gate',
                    'current' => 'empty or misconfigured',
                    'message' => 'Telescope gate is not restricting access - add authorized user emails',
                    'fix' => 'Add authorized user emails to the gate() method or implement role-based access control',
                ];
            }
        }

        // Recommendations for advanced optimizations
        if ($environment === 'production') {
            $hasStopRecording = str_contains($providerContent, 'Telescope::stopRecording()');

            if (!$hasStopRecording) {
                $recommendations[] = [
                    'category' => 'performance',
                    'config' => 'TelescopeServiceProvider::stopRecording',
                    'message' => 'Use Telescope::stopRecording() before bulk operations to prevent memory overflow',
                    'benefit' => 'Prevents Telescope from recording during data imports, migrations, or bulk updates',
                ];
            }

            $recommendations[] = [
                'category' => 'performance',
                'config' => 'TelescopeServiceProvider::optimization',
                'message' => 'Consider using Telescope::tag() to selectively monitor specific requests instead of filtering after recording',
                'benefit' => 'More efficient than post-recording filtering, reduces memory usage',
            ];
        }
    }
}
