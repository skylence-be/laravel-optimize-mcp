<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Mcp\Tools\Analyzers;

use Illuminate\Support\Facades\File;

final class GitHooksAnalyzer extends AbstractAnalyzer
{
    public function analyze(array &$issues, array &$recommendations, array &$goodPractices): void
    {
        $composerPath = base_path('composer.json');

        if (!File::exists($composerPath)) {
            return;
        }

        $composerData = json_decode(File::get($composerPath), true);
        $devPackages = array_keys($composerData['require-dev'] ?? []);

        // Check for GrumPHP
        $hasGrumPHP = in_array('phpro/grumphp', $devPackages);
        $grumphpConfigExists = File::exists(base_path('.grumphp.yml')) ||
                               File::exists(base_path('grumphp.yml'));

        // Check for CaptainHook
        $hasCaptainHook = in_array('captainhook/captainhook', $devPackages);
        $captainhookConfigExists = File::exists(base_path('captainhook.json'));

        // Check if Git hooks are installed
        $gitHooksPath = base_path('.git/hooks');
        $hasGitHooks = false;

        if (File::exists($gitHooksPath)) {
            $hookFiles = ['pre-commit', 'commit-msg', 'pre-push'];
            foreach ($hookFiles as $hook) {
                if (File::exists("{$gitHooksPath}/{$hook}")) {
                    $hasGitHooks = true;
                    break;
                }
            }
        }

        // Analyze GrumPHP
        if ($hasGrumPHP) {
            if ($grumphpConfigExists) {
                $goodPractices[] = [
                    'category' => 'git-hooks',
                    'message' => 'GrumPHP configured with .grumphp.yml',
                    'details' => 'Pre-commit quality checks enforce code standards automatically',
                ];

                if ($hasGitHooks) {
                    $goodPractices[] = [
                        'category' => 'git-hooks',
                        'message' => 'Git hooks installed and active',
                        'details' => 'Quality checks run automatically on commit',
                    ];
                }
            } else {
                $recommendations[] = [
                    'category' => 'git-hooks',
                    'type' => 'configuration',
                    'message' => 'GrumPHP installed but no .grumphp.yml config found',
                    'benefit' => 'Create .grumphp.yml to configure pre-commit quality checks',
                ];
            }
        }

        // Analyze CaptainHook
        if ($hasCaptainHook) {
            if ($captainhookConfigExists) {
                $goodPractices[] = [
                    'category' => 'git-hooks',
                    'message' => 'CaptainHook configured with captainhook.json',
                    'details' => 'Git hooks management with flexible configuration',
                ];

                if ($hasGitHooks) {
                    $goodPractices[] = [
                        'category' => 'git-hooks',
                        'message' => 'Git hooks installed and active',
                        'details' => 'Quality checks run automatically on commit',
                    ];
                }
            } else {
                $recommendations[] = [
                    'category' => 'git-hooks',
                    'type' => 'configuration',
                    'message' => 'CaptainHook installed but no captainhook.json config found',
                    'benefit' => 'Run vendor/bin/captainhook install to set up Git hooks',
                ];
            }
        }

        // If neither tool is installed, recommend one
        if (!$hasGrumPHP && !$hasCaptainHook) {
            $recommendations[] = [
                'category' => 'git-hooks',
                'type' => 'quality-automation',
                'message' => 'No Git hooks tool detected - install GrumPHP or CaptainHook',
                'benefit' => 'Automatically run Pint, Rector, PHPStan, and tests before commits. Prevents pushing broken code to CI/CD',
            ];

            $recommendations[] = [
                'category' => 'git-hooks',
                'type' => 'recommendation',
                'message' => 'GrumPHP: composer require --dev phpro/grumphp (Laravel-optimized, simple config)',
                'benefit' => 'Easy setup with sensible defaults for Laravel projects',
            ];

            $recommendations[] = [
                'category' => 'git-hooks',
                'type' => 'recommendation',
                'message' => 'CaptainHook: composer require --dev captainhook/captainhook (more flexible, JSON config)',
                'benefit' => 'Highly configurable with plugin system, better for complex workflows',
            ];
        }

        // Check if hooks need to be installed
        if (($hasGrumPHP || $hasCaptainHook) && !$hasGitHooks) {
            $tool = $hasGrumPHP ? 'GrumPHP' : 'CaptainHook';
            $command = $hasGrumPHP ? 'vendor/bin/grumphp git:init' : 'vendor/bin/captainhook install';

            $issues[] = [
                'severity' => 'warning',
                'category' => 'git-hooks',
                'file' => '.git/hooks',
                'message' => "{$tool} is installed but Git hooks are not active",
                'fix' => "Run: {$command}",
            ];
        }
    }
}
