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
        return [
            'add_to_composer' => $schema->boolean()
                ->description('Whether to add recommended packages to composer.json (requires manual composer install after)')
                ->default(false),

            'add_to_package_json' => $schema->boolean()
                ->description('Whether to add recommended npm/pnpm packages to package.json (requires manual npm/pnpm install after)')
                ->default(false),
        ];
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

        if (!File::exists($composerPath)) {
            return Response::error('composer.json not found in project root');
        }

        $composerData = json_decode(File::get($composerPath), true);

        if (!$composerData) {
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
        if ($addToComposer && !empty($missing)) {
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

                    if (!empty($missingNpm)) {
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
            'composer_updated' => !empty($addedPackages),
            'added_to_package_json' => $addedNpmPackages,
            'package_json_updated' => !empty($addedNpmPackages),
            'package_manager' => $packageManager,
        ]);
    }

    /**
     * Get all package recommendations.
     */
    private function getRecommendations(array $installedPackages): array
    {
        return [
            // Essential packages - start here
            'laravel/pint' => 'Code style fixer using Laravel conventions',
            'laravel/boost' => 'Essential performance optimizer - preloads routes/config for 2-5x faster boot time',
            'nunomaduro/larastan' => 'Static analysis tool for Laravel',
            'barryvdh/laravel-ide-helper' => 'Generate IDE helper files for better autocomplete',
            'nunomaduro/essentials' => 'Essential commands for Laravel - generates pint.json, rector.php configs instantly',

            // Testing
            'pestphp/pest' => 'Modern testing framework (Pest v4) with elegant syntax and type coverage',
            'pestphp/pest-plugin-laravel' => 'Pest plugin for Laravel testing features',
            'pestphp/pest-plugin-browser' => 'Browser testing with Pest v4 (if system can handle Chrome)',

            // Auth & Security
            'laravel/breeze' => 'Minimal authentication scaffolding',
            'spatie/laravel-permission' => 'Role and permission management',
            'enlightn/enlightn' => 'Laravel application security and performance scanner',

            // Database & API
            'spatie/laravel-query-builder' => 'Build Eloquent queries from API requests',
            'spatie/laravel-backup' => 'Database and file backup solution',
            'spatie/laravel-activitylog' => 'Log activity in your Laravel app',
            'laravel/scout' => 'Full-text search for Eloquent models - use with Meilisearch or Typesense',

            // Monitoring & Debugging
            'laravel/pulse' => 'Lightweight production-safe application monitoring',
            'laravel/telescope' => 'Debug assistant for LOCAL/STAGING only (use Pulse for production)',
            'skylence/laravel-telescope-mcp' => 'MCP integration for Telescope - AI-powered access to debugging data',
            'laravel/horizon' => 'Queue monitoring dashboard',
            'barryvdh/laravel-debugbar' => 'Debug bar for development (dev only)',
            'spatie/laravel-ray' => 'Debug tool with beautiful interface',
            'beyondcode/laravel-dump-server' => 'Collect dump() output in a separate window',

            // Code Quality
            'rector/rector' => 'Automated refactoring and upgrades',
            'phpstan/phpstan' => 'Advanced static analysis (alternative to Larastan)',
        ];
    }

    /**
     * Filter out already installed packages.
     */
    private function filterMissingPackages(array $recommendations, array $installedPackages): array
    {
        $missing = [];

        foreach ($recommendations as $package => $description) {
            if (!in_array($package, $installedPackages)) {
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

                // Check for Telescope without optimization packages
                if (in_array('laravel/telescope', $packages) && !in_array('binarcode/laravel-telescope-flusher', $packages)) {
                    $patterns[] = [
                        'package' => 'laravel/telescope',
                        'issue' => 'Missing Optimization Package',
                        'recommendation' => 'Install binarcode/laravel-telescope-flusher for better pruning performance to prevent database bloat',
                    ];
                }

                // Check for Telescope without MCP integration
                if (in_array('laravel/telescope', $packages) && !in_array('skylence/laravel-telescope-mcp', $packages)) {
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
     * Add recommended packages to composer.json.
     */
    private function addPackagesToComposer(string $composerPath, array $composerData, array $missing): array
    {
        $devPackages = ['barryvdh/laravel-ide-helper', 'barryvdh/laravel-debugbar', 'beyondcode/laravel-dump-server'];
        $added = ['require' => [], 'require-dev' => []];

        foreach ($missing as $package => $description) {
            $isDev = in_array($package, $devPackages);
            $section = $isDev ? 'require-dev' : 'require';

            // Add with latest version constraint
            if (!isset($composerData[$section])) {
                $composerData[$section] = [];
            }

            $composerData[$section][$package] = '*';
            $added[$section][] = $package;
        }

        // Sort the packages alphabetically
        if (isset($composerData['require'])) {
            ksort($composerData['require']);
        }
        if (isset($composerData['require-dev'])) {
            ksort($composerData['require-dev']);
        }

        // Write back to composer.json with pretty print
        File::put(
            $composerPath,
            json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
        );

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
     * Add recommended packages to package.json.
     */
    private function addPackagesToPackageJson(string $packageJsonPath, array $packageJsonData, array $missing): array
    {
        $devPackages = ['autoprefixer', 'prettier', 'eslint'];
        $added = ['dependencies' => [], 'devDependencies' => []];

        foreach ($missing as $package => $description) {
            $isDev = in_array($package, $devPackages);
            $section = $isDev ? 'devDependencies' : 'dependencies';

            // Add with latest version constraint
            if (!isset($packageJsonData[$section])) {
                $packageJsonData[$section] = [];
            }

            $packageJsonData[$section][$package] = '^1.0.0';
            $added[$section][] = $package;
        }

        // Sort the packages alphabetically
        if (isset($packageJsonData['dependencies'])) {
            ksort($packageJsonData['dependencies']);
        }
        if (isset($packageJsonData['devDependencies'])) {
            ksort($packageJsonData['devDependencies']);
        }

        // Write back to package.json with pretty print
        File::put(
            $packageJsonPath,
            json_encode($packageJsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
        );

        return $added;
    }

    /**
     * Build human-readable summary.
     */
    private function buildSummary(array $missing, array $outdated, array $installed, array $addedPackages = [], array $addedNpmPackages = [], ?string $packageManager = null): string
    {
        $lines = [];
        $lines[] = "📦 Laravel Package Recommendations";
        $lines[] = "";
        $lines[] = "Total Installed Packages: " . count($installed);
        $lines[] = "";

        if (!empty($missing)) {
            $lines[] = "🎯 Recommended Packages (" . count($missing) . "):";
            foreach ($missing as $package => $description) {
                $lines[] = "  • {$package}";
                $lines[] = "    → {$description}";
            }
            $lines[] = "";
        } else {
            $lines[] = "✅ All recommended packages are already installed!";
            $lines[] = "";
        }

        if (!empty($outdated)) {
            $lines[] = "⚠️ Outdated Patterns (" . count($outdated) . "):";
            foreach ($outdated as $pattern) {
                $lines[] = "  • {$pattern['package']}: {$pattern['issue']}";
                $lines[] = "    → {$pattern['recommendation']}";
            }
            $lines[] = "";
        }

        // Show what was added to composer.json
        if (!empty($addedPackages) && (!empty($addedPackages['require']) || !empty($addedPackages['require-dev']))) {
            $lines[] = "✅ ADDED TO COMPOSER.JSON:";
            if (!empty($addedPackages['require'])) {
                $lines[] = "  Production packages:";
                foreach ($addedPackages['require'] as $package) {
                    $lines[] = "    • {$package}";
                }
            }
            if (!empty($addedPackages['require-dev'])) {
                $lines[] = "  Development packages:";
                foreach ($addedPackages['require-dev'] as $package) {
                    $lines[] = "    • {$package}";
                }
            }
            $lines[] = "";
            $lines[] = "⚡ Next step: Run 'composer update' to install the packages";
        } elseif (!empty($missing)) {
            $lines[] = "💡 Install missing packages:";
            $devPackages = ['barryvdh/laravel-ide-helper', 'barryvdh/laravel-debugbar', 'beyondcode/laravel-dump-server'];
            $prodPackages = array_filter(array_keys($missing), fn($p) => !in_array($p, $devPackages));
            $devOnlyPackages = array_filter(array_keys($missing), fn($p) => in_array($p, $devPackages));

            if (!empty($prodPackages)) {
                $lines[] = "  composer require " . implode(' ', $prodPackages);
            }
            if (!empty($devOnlyPackages)) {
                $lines[] = "  composer require --dev " . implode(' ', $devOnlyPackages);
            }
            $lines[] = "";
            $lines[] = "Or use add_to_composer=true to automatically add them to composer.json";
        } else {
            $lines[] = "💡 (No missing packages)";
        }

        // Show what was added to package.json
        if (!empty($addedNpmPackages) && (!empty($addedNpmPackages['dependencies']) || !empty($addedNpmPackages['devDependencies']))) {
            $lines[] = "";
            $lines[] = "✅ ADDED TO PACKAGE.JSON:";
            if (!empty($addedNpmPackages['dependencies'])) {
                $lines[] = "  Dependencies:";
                foreach ($addedNpmPackages['dependencies'] as $package) {
                    $lines[] = "    • {$package}";
                }
            }
            if (!empty($addedNpmPackages['devDependencies'])) {
                $lines[] = "  Dev Dependencies:";
                foreach ($addedNpmPackages['devDependencies'] as $package) {
                    $lines[] = "    • {$package}";
                }
            }
            $lines[] = "";
            $lines[] = "⚡ Next step: Run '{$packageManager} install' to install the packages";
            $lines[] = "";

            // Add pnpm recommendation if using npm
            if ($packageManager === 'npm') {
                $lines[] = "💡 TIP: Consider using pnpm instead of npm for local development:";
                $lines[] = "  - Faster installs and smaller disk usage";
                $lines[] = "  - Better monorepo support";
                $lines[] = "  - Run: npm install -g pnpm && pnpm install";
                $lines[] = "";
                $lines[] = "⚠️  NOTE: If deploying with Laravel Forge, it expects npm by default.";
                $lines[] = "   You may need to configure Forge to use pnpm or keep npm for production.";
            } elseif ($packageManager === 'pnpm') {
                $lines[] = "✅ You're using pnpm - excellent choice for local development!";
                $lines[] = "";
                $lines[] = "⚠️  NOTE: If deploying with Laravel Forge, it expects npm by default.";
                $lines[] = "   You may need to configure Forge to use pnpm or switch to npm for production.";
            }
        }

        return implode("\n", $lines);
    }
}
