<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Mcp\Tools;

use Illuminate\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\File;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

final class PackageAdvisor extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'Analyze Laravel project and suggest useful packages to improve development workflow and production performance';

    /**
     * Get the tool's input schema.
     */
    public function schema(JsonSchema $schema): array
    {
        // Default to true for stdio (local), false for HTTP (remote)
        $defaultAddPackages = ! $this->isHttpContext();

        return [
            'add_to_composer' => $schema->boolean()
                ->description('Whether to add recommended packages to composer.json (requires manual composer install after)')
                ->default($defaultAddPackages),

            'add_to_package_json' => $schema->boolean()
                ->description('Whether to add recommended npm/pnpm packages to package.json (requires manual npm/pnpm install after)')
                ->default($defaultAddPackages),
        ];
    }

    /**
     * Check if running in HTTP context (vs stdio).
     */
    protected function isHttpContext(): bool
    {
        if (! app()->bound('request')) {
            return false;
        }

        try {
            $request = app('request');

            return $request instanceof \Illuminate\Http\Request && ! app()->runningInConsole();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $params = $request->all();
        $addToComposer = $params['add_to_composer'] ?? false;
        $addToPackageJson = $params['add_to_package_json'] ?? false;

        $composerPath = base_path('composer.json');

        if (! File::exists($composerPath)) {
            return Response::error('composer.json not found in project root');
        }

        $composerData = json_decode(File::get($composerPath), true);

        if (! $composerData) {
            return Response::error('Failed to parse composer.json');
        }

        $installedPackages = array_merge(
            array_keys($composerData['require'] ?? []),
            array_keys($composerData['require-dev'] ?? [])
        );

        // Get all recommendations
        $recommendations = $this->getRecommendations($installedPackages);
        $missing = $this->filterMissingPackages($recommendations, $installedPackages);
        $outdated = $this->checkOutdatedPatterns($installedPackages);

        // Add packages to composer.json if requested
        $addedPackages = [];
        if ($addToComposer && ! empty($missing)) {
            $addedPackages = $this->addPackagesToComposer($composerPath, $composerData, $missing);
        }

        // Handle package.json if requested
        $addedNpmPackages = [];
        $packageManager = null;
        if ($addToPackageJson) {
            $packageJsonPath = base_path('package.json');
            if (File::exists($packageJsonPath)) {
                $packageJsonData = json_decode(File::get($packageJsonPath), true);
                if ($packageJsonData) {
                    $installedNpmPackages = array_merge(
                        array_keys($packageJsonData['dependencies'] ?? []),
                        array_keys($packageJsonData['devDependencies'] ?? [])
                    );

                    $packageManager = $this->detectPackageManager();
                    $npmRecommendations = $this->getNpmRecommendations();
                    $missingNpm = $this->filterMissingPackages($npmRecommendations, $installedNpmPackages);

                    if (! empty($missingNpm)) {
                        $addedNpmPackages = $this->addPackagesToPackageJson($packageJsonPath, $packageJsonData, $missingNpm);
                    }
                }
            }
        }

        $summary = $this->buildSummary($missing, $outdated, $installedPackages, $addedPackages, $addedNpmPackages, $packageManager);

        // Return as JSON response with metadata
        return Response::json([
            'summary' => $summary,
            'total_packages' => count($installedPackages),
            'recommendations' => $missing,
            'outdated_patterns' => $outdated,
            'installed_packages' => $installedPackages,
            'added_to_composer' => $addedPackages,
            'composer_updated' => ! empty($addedPackages),
            'added_to_package_json' => $addedNpmPackages,
            'package_json_updated' => ! empty($addedNpmPackages),
            'package_manager' => $packageManager,
        ]);
    }

    /**
     * Detect environment capabilities.
     */
    private function detectEnvironment(): array
    {
        $isWindows = PHP_OS_FAMILY === 'Windows';
        $hasPhpBat = false;
        $hasComposerBat = false;
        $hasPosix = extension_loaded('posix');
        $hasPcntl = extension_loaded('pcntl');

        if ($isWindows) {
            // Check for php.bat and composer.bat on Windows
            exec('where php 2>nul', $phpOutput);
            exec('where composer 2>nul', $composerOutput);

            $hasPhpBat = ! empty($phpOutput) && str_contains(implode('', $phpOutput), '.bat');
            $hasComposerBat = ! empty($composerOutput) && str_contains(implode('', $composerOutput), '.bat');
        }

        return [
            'is_windows' => $isWindows,
            'has_php_bat' => $hasPhpBat,
            'has_composer_bat' => $hasComposerBat,
            'has_posix' => $hasPosix,
            'has_pcntl' => $hasPcntl,
            'supports_horizon' => $hasPosix || $hasPcntl,
        ];
    }

    /**
     * Get all package recommendations.
     */
    private function getRecommendations(array $installedPackages): array
    {
        $env = $this->detectEnvironment();

        $recommendations = [
            // Essential packages - start here
            'laravel/pint' => 'Code style fixer using Laravel conventions',
            'laravel/boost' => 'Essential performance optimizer - preloads routes/config for 2-5x faster boot time',
            'larastan/larastan' => 'Static analysis tool for Laravel (PHPStan for Laravel)',
            'barryvdh/laravel-ide-helper' => 'Generate IDE helper files for better autocomplete',
            'nunomaduro/essentials' => 'Essential commands for Laravel - generates pint.json, rector.php configs instantly',

            // Testing
            'pestphp/pest' => 'Modern testing framework (Pest v4) with elegant syntax and type coverage',
            'pestphp/pest-plugin-laravel' => 'Pest plugin for Laravel testing features',
            'pestphp/pest-plugin-type-coverage' => 'Type coverage plugin for Pest - required for composer scripts',
            'pestphp/pest-plugin-browser' => 'Browser testing with Pest v4 (if system can handle Chrome)',

            // Auth & Security
            'laravel/breeze' => 'Minimal authentication scaffolding',
            'spatie/laravel-permission' => 'Role and permission management',

            // Database & API
            'spatie/laravel-query-builder' => 'Build Eloquent queries from API requests',
            'spatie/laravel-backup' => 'Database and file backup solution',
            'spatie/laravel-activitylog' => 'Log activity in your Laravel app',
            'laravel/scout' => 'Full-text search for Eloquent models - use with Meilisearch or Typesense',

            // Monitoring & Debugging
            'laravel/pulse' => 'Lightweight production-safe application monitoring',
            'laravel/telescope' => 'Debug assistant for LOCAL/STAGING only (use Pulse for production)',
            'skylence/laravel-telescope-mcp' => 'MCP integration for Telescope - AI-powered access to debugging data',
            'barryvdh/laravel-debugbar' => 'Debug bar for development (dev only)',
            'spatie/laravel-ray' => 'Debug tool with beautiful interface',
            'beyondcode/laravel-dump-server' => 'Collect dump() output in a separate window',

            // Code Quality
            'rector/rector' => 'Automated refactoring and upgrades',
            'phpstan/phpstan' => 'Advanced static analysis (alternative to Larastan)',
        ];

        // Only add laravel/horizon if ext-posix or ext-pcntl is available
        if ($env['supports_horizon']) {
            $recommendations['laravel/horizon'] = 'Queue monitoring dashboard (requires ext-posix or ext-pcntl)';
        }

        return $recommendations;
    }

    /**
     * Filter out already installed packages.
     */
    private function filterMissingPackages(array $recommendations, array $installedPackages): array
    {
        $missing = [];

        foreach ($recommendations as $package => $description) {
            if (! in_array($package, $installedPackages)) {
                $missing[$package] = $description;
            }
        }

        return $missing;
    }

    /**
     * Check for outdated or problematic patterns.
     */
    private function checkOutdatedPatterns(array $packages): array
    {
        $patterns = [];

        // Check for abandoned packages
        $abandonedPackages = [
            'fideloper/proxy' => 'Use Laravel 11+ built-in trusted proxy support instead',
            'barryvdh/laravel-cors' => 'Use Laravel 9+ built-in CORS support instead',
        ];

        foreach ($abandonedPackages as $package => $reason) {
            if (in_array($package, $packages)) {
                $patterns[] = [
                    'package' => $package,
                    'issue' => 'Abandoned/Obsolete',
                    'recommendation' => $reason,
                ];
            }
        }

        // Check for Laravel Telescope in production (not in require-dev)
        if (in_array('laravel/telescope', $packages)) {
            // Check if it's in require (not require-dev)
            $composerPath = base_path('composer.json');
            if (File::exists($composerPath)) {
                $composerData = json_decode(File::get($composerPath), true);
                $prodPackages = array_keys($composerData['require'] ?? []);

                if (in_array('laravel/telescope', $prodPackages)) {
                    $patterns[] = [
                        'package' => 'laravel/telescope',
                        'issue' => 'Production Performance Risk',
                        'recommendation' => 'Telescope can cause 1,100% memory increase and 50-200ms overhead per request. Move to require-dev or use Laravel Pulse/APM tools for production monitoring',
                    ];
                }

                // Check for Telescope without MCP integration
                if (in_array('laravel/telescope', $packages) && ! in_array('skylence/laravel-telescope-mcp', $packages)) {
                    $patterns[] = [
                        'package' => 'laravel/telescope',
                        'issue' => 'Missing MCP Integration',
                        'recommendation' => 'Install skylence/laravel-telescope-mcp to access Telescope data via Model Context Protocol for AI-powered debugging and analysis',
                    ];
                }
            }
        }

        return $patterns;
    }

    /**
     * Install recommended packages using composer require.
     */
    private function addPackagesToComposer(string $composerPath, array $composerData, array $missing): array
    {
        $devPackages = [
            'barryvdh/laravel-ide-helper',
            'barryvdh/laravel-debugbar',
            'beyondcode/laravel-dump-server',
            'pestphp/pest',
            'pestphp/pest-plugin-laravel',
            'pestphp/pest-plugin-type-coverage',
            'pestphp/pest-plugin-browser',
        ];
        $specialVersions = [
            'skylence/laravel-telescope-mcp' => 'dev-main',
        ];
        $added = ['require' => [], 'require-dev' => []];

        // Add repository for skylence/laravel-telescope-mcp if it's in missing packages
        if (isset($missing['skylence/laravel-telescope-mcp'])) {
            if (! isset($composerData['repositories'])) {
                $composerData['repositories'] = [];
            }

            $repoExists = false;
            foreach ($composerData['repositories'] as $repo) {
                if (isset($repo['url']) && str_contains($repo['url'], 'laravel-telescope-mcp')) {
                    $repoExists = true;
                    break;
                }
            }

            if (! $repoExists) {
                $composerData['repositories'][] = [
                    'type' => 'vcs',
                    'url' => 'https://github.com/skylence-be/laravel-telescope-mcp',
                ];

                // Write back to composer.json to add repository
                File::put(
                    $composerPath,
                    json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n"
                );
            }
        }

        // Separate packages by production vs dev
        $prodPackages = [];
        $devOnlyPackages = [];

        foreach ($missing as $package => $description) {
            $isDev = in_array($package, $devPackages);
            $version = $specialVersions[$package] ?? '';

            $packageSpec = $version ? "{$package}:{$version}" : $package;

            if ($isDev) {
                $devOnlyPackages[] = $packageSpec;
                $added['require-dev'][] = $package;
            } else {
                $prodPackages[] = $packageSpec;
                $added['require'][] = $package;
            }
        }

        // Run composer require commands
        $env = $this->detectEnvironment();
        $composerCmd = $env['has_composer_bat'] ? 'composer.bat' : 'composer';

        if (! empty($prodPackages)) {
            $command = $composerCmd.' require --no-interaction '.implode(' ', $prodPackages);
            exec($command, $output, $returnCode);
        }

        if (! empty($devOnlyPackages)) {
            $command = $composerCmd.' require --dev --no-interaction '.implode(' ', $devOnlyPackages);
            exec($command, $output, $returnCode);
        }

        return $added;
    }

    /**
     * Detect package manager (pnpm or npm).
     */
    private function detectPackageManager(): string
    {
        // Check for pnpm-lock.yaml first (pnpm preferred)
        if (File::exists(base_path('pnpm-lock.yaml'))) {
            return 'pnpm';
        }

        // Check for package-lock.json (npm)
        if (File::exists(base_path('package-lock.json'))) {
            return 'npm';
        }

        // Default to pnpm (recommended)
        return 'pnpm';
    }

    /**
     * Get all npm/pnpm package recommendations.
     */
    private function getNpmRecommendations(): array
    {
        return [
            // Essential CSS & Tailwind
            '@tailwindcss/forms' => 'Beautiful form styles for Tailwind CSS',
            '@tailwindcss/typography' => 'Beautiful typographic defaults for prose content',
            'autoprefixer' => 'Parse CSS and add vendor prefixes automatically',

            // Lightweight JavaScript
            'alpinejs' => 'Lightweight JavaScript framework for interactivity (perfect for Laravel)',

            // Code Quality (framework-agnostic)
            'prettier' => 'Opinionated code formatter for consistent code style',
            'eslint' => 'JavaScript linter for code quality',
        ];
    }

    /**
     * Install recommended packages using npm/pnpm.
     */
    private function addPackagesToPackageJson(string $packageJsonPath, array $packageJsonData, array $missing): array
    {
        $devPackages = ['autoprefixer', 'prettier', 'eslint'];
        $added = ['dependencies' => [], 'devDependencies' => []];

        // Separate packages by production vs dev
        $prodPackages = [];
        $devOnlyPackages = [];

        foreach ($missing as $package => $description) {
            $isDev = in_array($package, $devPackages);

            if ($isDev) {
                $devOnlyPackages[] = $package;
                $added['devDependencies'][] = $package;
            } else {
                $prodPackages[] = $package;
                $added['dependencies'][] = $package;
            }
        }

        // Detect package manager
        $packageManager = $this->detectPackageManager();

        // Run installation commands
        if ($packageManager === 'pnpm') {
            if (! empty($prodPackages)) {
                $command = 'pnpm add --force '.implode(' ', $prodPackages);
                exec($command, $output, $returnCode);
            }

            if (! empty($devOnlyPackages)) {
                $command = 'pnpm add -D --force '.implode(' ', $devOnlyPackages);
                exec($command, $output, $returnCode);
            }
        } else {
            // npm
            if (! empty($prodPackages)) {
                $command = 'npm install --yes '.implode(' ', $prodPackages);
                exec($command, $output, $returnCode);
            }

            if (! empty($devOnlyPackages)) {
                $command = 'npm install --save-dev --yes '.implode(' ', $devOnlyPackages);
                exec($command, $output, $returnCode);
            }
        }

        return $added;
    }

    /**
     * Build human-readable summary.
     */
    private function buildSummary(array $missing, array $outdated, array $installed, array $addedPackages = [], array $addedNpmPackages = [], ?string $packageManager = null): string
    {
        $lines = [];
        $lines[] = '📦 Laravel Package Recommendations';
        $lines[] = '';
        $lines[] = 'Total Installed Packages: '.count($installed);
        $lines[] = '';

        if (! empty($missing)) {
            $lines[] = '🎯 Recommended Packages ('.count($missing).'):';
            foreach ($missing as $package => $description) {
                $lines[] = "  • {$package}";
                $lines[] = "    → {$description}";
            }
            $lines[] = '';
        } else {
            $lines[] = '✅ All recommended packages are already installed!';
            $lines[] = '';
        }

        if (! empty($outdated)) {
            $lines[] = '⚠️ Outdated Patterns ('.count($outdated).'):';
            foreach ($outdated as $pattern) {
                $lines[] = "  • {$pattern['package']}: {$pattern['issue']}";
                $lines[] = "    → {$pattern['recommendation']}";
            }
            $lines[] = '';
        }

        // Show what was installed via composer
        if (! empty($addedPackages) && (! empty($addedPackages['require']) || ! empty($addedPackages['require-dev']))) {
            $lines[] = '✅ INSTALLED PACKAGES:';
            if (! empty($addedPackages['require'])) {
                $lines[] = '  Production packages:';
                foreach ($addedPackages['require'] as $package) {
                    $lines[] = "    • {$package}";
                }
            }
            if (! empty($addedPackages['require-dev'])) {
                $lines[] = '  Development packages:';
                foreach ($addedPackages['require-dev'] as $package) {
                    $lines[] = "    • {$package}";
                }
            }
            $lines[] = '';
        } elseif (! empty($missing)) {
            $lines[] = '💡 To install missing packages, run:';
            $devPackages = [
                'barryvdh/laravel-ide-helper',
                'barryvdh/laravel-debugbar',
                'beyondcode/laravel-dump-server',
                'pestphp/pest',
                'pestphp/pest-plugin-laravel',
                'pestphp/pest-plugin-type-coverage',
                'pestphp/pest-plugin-browser',
            ];
            $prodPackages = array_filter(array_keys($missing), fn ($p) => ! in_array($p, $devPackages));
            $devOnlyPackages = array_filter(array_keys($missing), fn ($p) => in_array($p, $devPackages));

            if (! empty($prodPackages)) {
                $lines[] = '  composer require '.implode(' ', $prodPackages);
            }
            if (! empty($devOnlyPackages)) {
                $lines[] = '  composer require --dev '.implode(' ', $devOnlyPackages);
            }
            $lines[] = '';
            $lines[] = 'Or use add_to_composer=true to automatically install them';
        } else {
            $lines[] = '💡 (No missing packages)';
        }

        // Show what was installed via npm/pnpm
        if (! empty($addedNpmPackages) && (! empty($addedNpmPackages['dependencies']) || ! empty($addedNpmPackages['devDependencies']))) {
            $lines[] = '';
            $pmName = strtoupper($packageManager ?? 'NPM');
            $lines[] = "✅ INSTALLED {$pmName} PACKAGES:";
            if (! empty($addedNpmPackages['dependencies'])) {
                $lines[] = '  Dependencies:';
                foreach ($addedNpmPackages['dependencies'] as $package) {
                    $lines[] = "    • {$package}";
                }
            }
            if (! empty($addedNpmPackages['devDependencies'])) {
                $lines[] = '  Dev Dependencies:';
                foreach ($addedNpmPackages['devDependencies'] as $package) {
                    $lines[] = "    • {$package}";
                }
            }
            $lines[] = '';

            // Add pnpm recommendation if using npm
            if ($packageManager === 'npm') {
                $lines[] = '💡 TIP: Consider using pnpm instead of npm for local development:';
                $lines[] = '  - Faster installs and smaller disk usage';
                $lines[] = '  - Better monorepo support';
                $lines[] = '  - Run: npm install -g pnpm';
                $lines[] = '';
                $lines[] = '⚠️  NOTE: If deploying with Laravel Forge, it expects npm by default.';
                $lines[] = '   You may need to configure Forge to use pnpm or keep npm for production.';
            } elseif ($packageManager === 'pnpm') {
                $lines[] = "✅ You're using pnpm - excellent choice for local development!";
                $lines[] = '';
                $lines[] = '⚠️  NOTE: If deploying with Laravel Forge, it expects npm by default.';
                $lines[] = '   You may need to configure Forge to use pnpm or switch to npm for production.';
            }
        }

        return implode("\n", $lines);
    }
}
