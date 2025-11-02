<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Mcp\Tools\Analyzers\Config;

use Illuminate\Support\Facades\File;

final class EnvironmentDriversAnalyzer extends AbstractConfigAnalyzer
{
    public function analyze(array &$issues, array &$recommendations, string $environment): void
    {
        $envPath = base_path('.env');
        if (!File::exists($envPath)) {
            return;
        }

        $envContent = File::get($envPath);
        $envVars = $this->parseEnvFile($envContent);

        // Define optimal drivers per environment
        $optimalDrivers = [
            'production' => [
                'CACHE_DRIVER' => ['redis', 'valkey', 'memcached'],
                'QUEUE_CONNECTION' => ['redis', 'valkey', 'sqs', 'database'],
                'SESSION_DRIVER' => ['redis', 'valkey', 'database', 'cookie'],
                'BROADCAST_DRIVER' => ['redis', 'valkey', 'pusher', 'ably'],
                'MAIL_MAILER' => ['smtp', 'ses', 'postmark', 'mailgun'],
                'FILESYSTEM_DISK' => ['s3', 'local'],
                'LOG_CHANNEL' => ['stack', 'daily', 'syslog'],
            ],
            'local' => [
                'CACHE_DRIVER' => ['file', 'redis', 'valkey', 'array'],
                'QUEUE_CONNECTION' => ['sync', 'database', 'redis', 'valkey'],
                'SESSION_DRIVER' => ['file', 'cookie', 'database', 'redis', 'valkey'],
                'BROADCAST_DRIVER' => ['log', 'redis', 'valkey', 'pusher'],
                'MAIL_MAILER' => ['log', 'smtp'],
                'FILESYSTEM_DISK' => ['local', 'public'],
                'LOG_CHANNEL' => ['stack', 'single'],
            ],
        ];

        $targetOptimal = $optimalDrivers[$environment === 'production' ? 'production' : 'local'];

        // Check each driver configuration
        $this->checkDriver($envVars, 'CACHE_DRIVER', $targetOptimal, $issues, $recommendations, $environment, [
            'production_bad' => ['array', 'file'],
            'production_best' => 'redis',
            'reason' => 'In-memory cache (Valkey/Redis) provides persistence and is significantly faster than file-based cache',
            'migration' => 'Install Valkey (preferred, Linux Foundation maintained) or Redis. Use redis driver (fully compatible with both). Configure REDIS_HOST in .env',
        ]);

        $this->checkDriver($envVars, 'QUEUE_CONNECTION', $targetOptimal, $issues, $recommendations, $environment, [
            'production_bad' => ['sync'],
            'production_best' => 'redis',
            'reason' => 'Sync queue blocks request processing, in-memory queue (Valkey/Redis) provides fast async job processing',
            'migration' => 'Use redis driver (works with Valkey or Redis), configure queue workers with Supervisor or Laravel Horizon',
        ]);

        $this->checkDriver($envVars, 'SESSION_DRIVER', $targetOptimal, $issues, $recommendations, $environment, [
            'production_bad' => ['file'],
            'production_best' => 'redis',
            'reason' => 'File sessions don\'t scale across multiple servers, in-memory storage (Valkey/Redis) allows horizontal scaling',
            'migration' => 'Switch to redis driver (supports Valkey or Redis) for multi-server setups, or use database for simplicity',
        ]);

        $this->checkDriver($envVars, 'BROADCAST_DRIVER', $targetOptimal, $issues, $recommendations, $environment, [
            'production_bad' => ['log'],
            'production_best' => 'redis',
            'reason' => 'Log driver doesn\'t broadcast events to clients, use Valkey/Redis with Laravel Echo',
            'migration' => 'Set up Valkey/Redis with laravel-echo-server or use Pusher/Ably for managed solution',
        ]);

        $this->checkDriver($envVars, 'MAIL_MAILER', $targetOptimal, $issues, $recommendations, $environment, [
            'production_bad' => ['log', 'array'],
            'production_best' => 'ses',
            'reason' => 'Log/array mailers don\'t send real emails, use SES/Postmark/Mailgun for production',
            'migration' => 'Configure SMTP or use AWS SES (cost-effective, reliable)',
        ]);

        $this->checkDriver($envVars, 'LOG_CHANNEL', $targetOptimal, $issues, $recommendations, $environment, [
            'production_bad' => ['single'],
            'production_best' => 'daily',
            'reason' => 'Single log file grows indefinitely, daily rotation prevents disk space issues',
            'migration' => 'Use daily logs with LOG_CHANNEL=daily',
        ]);

        $this->checkDriver($envVars, 'FILESYSTEM_DISK', $targetOptimal, $issues, $recommendations, $environment, [
            'production_bad' => [],
            'production_best' => 's3',
            'reason' => 'S3 provides scalable, durable file storage with CDN integration',
            'migration' => 'Consider S3 for file uploads, especially for multi-server deployments',
        ]);

        // Check for missing critical environment variables
        $criticalVars = [
            'APP_KEY' => 'critical',
            'APP_ENV' => 'critical',
            'APP_DEBUG' => 'critical',
            'DB_CONNECTION' => 'critical',
        ];

        foreach ($criticalVars as $var => $severity) {
            if (!isset($envVars[$var]) || empty($envVars[$var])) {
                $issues[] = [
                    'severity' => $severity,
                    'category' => 'configuration',
                    'config' => $var,
                    'current' => 'missing',
                    'message' => "{$var} is not set in .env file",
                    'fix' => "Set {$var} in .env file",
                ];
            }
        }

        // Check for database connection optimization
        if (isset($envVars['DB_CONNECTION']) && $envVars['DB_CONNECTION'] === 'mysql') {
            if (!isset($envVars['DB_CHARSET']) || $envVars['DB_CHARSET'] !== 'utf8mb4') {
                $recommendations[] = [
                    'category' => 'database',
                    'config' => 'DB_CHARSET',
                    'message' => 'Use utf8mb4 charset for full Unicode support (emojis, special characters)',
                    'benefit' => 'Prevents data loss and encoding issues',
                ];
            }

            if (!isset($envVars['DB_COLLATION']) || $envVars['DB_COLLATION'] !== 'utf8mb4_unicode_ci') {
                $recommendations[] = [
                    'category' => 'database',
                    'config' => 'DB_COLLATION',
                    'message' => 'Use utf8mb4_unicode_ci collation for better sorting and comparison',
                    'benefit' => 'Improved multilingual support',
                ];
            }
        }

        // Check Redis/Valkey configuration
        if (in_array('redis', [
            $envVars['CACHE_DRIVER'] ?? '',
            $envVars['QUEUE_CONNECTION'] ?? '',
            $envVars['SESSION_DRIVER'] ?? '',
            $envVars['BROADCAST_DRIVER'] ?? '',
        ])) {
            if (!isset($envVars['REDIS_HOST'])) {
                $issues[] = [
                    'severity' => 'warning',
                    'category' => 'configuration',
                    'config' => 'REDIS_HOST',
                    'current' => 'missing',
                    'message' => 'Redis driver is configured but REDIS_HOST is not set',
                    'fix' => 'Set REDIS_HOST=127.0.0.1 or your Valkey/Redis server address',
                ];
            }

            if (!isset($envVars['REDIS_CLIENT']) || $envVars['REDIS_CLIENT'] !== 'phpredis') {
                $recommendations[] = [
                    'category' => 'performance',
                    'config' => 'REDIS_CLIENT',
                    'message' => 'Use phpredis client instead of predis for better performance',
                    'benefit' => 'phpredis is a C extension, ~5x faster than predis PHP library. Works with both Valkey and Redis',
                ];
            }

            // Recommend Valkey as optional alternative
            $recommendations[] = [
                'category' => 'infrastructure',
                'config' => 'redis_alternative',
                'message' => 'Consider using Valkey instead of Redis (optional)',
                'benefit' => 'Valkey is a Linux Foundation fork of Redis - fully compatible, open-source, no licensing concerns. Works as drop-in replacement',
            ];
        }

        // Check Laravel Scout configuration
        $composerPath = base_path('composer.json');
        if (File::exists($composerPath)) {
            $composerData = json_decode(File::get($composerPath), true);
            $installedPackages = array_merge(
                array_keys($composerData['require'] ?? []),
                array_keys($composerData['require-dev'] ?? [])
            );

            $scoutInstalled = in_array('laravel/scout', $installedPackages);

            if ($scoutInstalled) {
                $scoutDriver = $envVars['SCOUT_DRIVER'] ?? config('scout.driver', 'algolia');

                // Check if using optimal search engine
                if (!in_array($scoutDriver, ['meilisearch', 'typesense'])) {
                    $recommendations[] = [
                        'category' => 'performance',
                        'config' => 'SCOUT_DRIVER',
                        'message' => "Scout driver is '{$scoutDriver}'. For large datasets, consider Meilisearch or Typesense",
                        'benefit' => 'Meilisearch and Typesense are open-source, blazing fast, and optimized for large-scale search. Better than Algolia for self-hosted solutions',
                    ];
                }

                // Specific driver recommendations
                if ($scoutDriver === 'meilisearch' || $scoutDriver === 'typesense') {
                    $recommendations[] = [
                        'category' => 'search',
                        'config' => 'scout_optimization',
                        'message' => "Scout is configured with {$scoutDriver} - excellent choice for production!",
                        'benefit' => ucfirst($scoutDriver) . ' provides typo-tolerance, faceted search, and sub-50ms response times on millions of records',
                    ];
                }
            }
        }
    }

    private function parseEnvFile(string $content): array
    {
        $vars = [];
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip comments and empty lines
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            // Parse KEY=VALUE
            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Remove quotes
                $value = trim($value, '"\'');

                $vars[$key] = $value;
            }
        }

        return $vars;
    }

    private function checkDriver(
        array $envVars,
        string $varName,
        array $targetOptimal,
        array &$issues,
        array &$recommendations,
        string $environment,
        array $config
    ): void {
        $currentValue = $envVars[$varName] ?? null;

        if (empty($currentValue)) {
            return; // Skip if not configured
        }

        $optimalValues = $targetOptimal[$varName] ?? [];
        $badValues = $config['production_bad'] ?? [];
        $bestValue = $config['production_best'] ?? '';
        $reason = $config['reason'] ?? '';
        $migration = $config['migration'] ?? '';

        // Check if current value is suboptimal in production
        if ($environment === 'production' && in_array($currentValue, $badValues)) {
            $issues[] = [
                'severity' => 'warning',
                'category' => 'performance',
                'config' => $varName,
                'current' => $currentValue,
                'message' => "{$varName}={$currentValue} is not recommended for production",
                'fix' => "Change to {$bestValue}: {$migration}",
            ];
        } elseif ($environment === 'production' && !in_array($currentValue, $optimalValues)) {
            // Suggest better alternative if not optimal
            $recommendations[] = [
                'category' => 'performance',
                'config' => $varName,
                'message' => "{$varName}={$currentValue} could be optimized. Consider {$bestValue}",
                'benefit' => $reason,
            ];
        }
    }
}
