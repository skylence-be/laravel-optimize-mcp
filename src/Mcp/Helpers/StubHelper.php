<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Mcp\Helpers;

use Illuminate\Support\Facades\File;

class StubHelper
{
    /**
     * Get the path to a stub file.
     */
    public static function getStubPath(string $stub): string
    {
        return __DIR__ . '/../../../stubs/' . $stub;
    }

    /**
     * Get the contents of a stub file.
     */
    public static function getStubContents(string $stub): ?string
    {
        $path = self::getStubPath($stub);

        if (!File::exists($path)) {
            return null;
        }

        return File::get($path);
    }

    /**
     * Get all available stubs with their descriptions.
     */
    public static function getAvailableStubs(): array
    {
        return [
            '.github' => [
                'description' => 'Complete GitHub configuration including workflows (tests, dependabot auto-merge), custom actions (setup), and dependabot config',
                'destination' => '.github',
                'category' => 'ci-cd',
                'is_directory' => true,
            ],
            '.github/workflows/tests.yml' => [
                'description' => 'Main GitHub Actions workflow with parallel testing, code quality checks (Pint, PHPStan, Rector), and type coverage',
                'destination' => '.github/workflows/tests.yml',
                'category' => 'ci-cd',
            ],
            '.github/workflows/dependabot-auto-merge.yml' => [
                'description' => 'Auto-merge workflow for Dependabot PRs',
                'destination' => '.github/workflows/dependabot-auto-merge.yml',
                'category' => 'ci-cd',
            ],
            '.github/actions/setup/action.yml' => [
                'description' => 'Custom GitHub Action for PHP, Composer, and pnpm setup',
                'destination' => '.github/actions/setup/action.yml',
                'category' => 'ci-cd',
            ],
            '.github/dependabot.yml' => [
                'description' => 'Dependabot configuration for automated dependency updates',
                'destination' => '.github/dependabot.yml',
                'category' => 'ci-cd',
            ],
            'captainhook.json' => [
                'description' => 'CaptainHook Git hooks configuration - runs Pint, Rector, PHPStan before commits',
                'destination' => 'captainhook.json',
                'category' => 'git-hooks',
            ],
            'deploy.php' => [
                'description' => 'Deployer configuration for zero-downtime Laravel deployments with migrations, cache optimization, and queue management',
                'destination' => 'deploy.php',
                'category' => 'deployment',
            ],
            'pint.json' => [
                'description' => 'Laravel Pint configuration with strict rules and Laravel conventions',
                'destination' => 'pint.json',
                'category' => 'code-quality',
            ],
            'phpstan.neon' => [
                'description' => 'PHPStan static analysis configuration for Laravel with strict level settings',
                'destination' => 'phpstan.neon',
                'category' => 'code-quality',
            ],
            'rector.php' => [
                'description' => 'Rector automated refactoring configuration for PHP and Laravel',
                'destination' => 'rector.php',
                'category' => 'code-quality',
            ],
            'composer-scripts.json' => [
                'description' => 'Recommended composer scripts for testing, linting, and quality checks',
                'destination' => null, // Merge into existing composer.json
                'category' => 'composer',
            ],
        ];
    }

    /**
     * Get stubs by category.
     */
    public static function getStubsByCategory(string $category): array
    {
        return array_filter(
            self::getAvailableStubs(),
            fn($stub) => $stub['category'] === $category,
            ARRAY_FILTER_USE_BOTH
        );
    }
}
