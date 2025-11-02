<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Mcp\Tools\Analyzers;

use Illuminate\Support\Facades\File;

final class GitHubWorkflowAnalyzer extends AbstractAnalyzer
{
    public function analyze(array &$issues, array &$recommendations, array &$goodPractices): void
    {
        $workflowsPath = base_path('.github/workflows');

        if (!File::exists($workflowsPath)) {
            $recommendations[] = [
                'category' => 'ci-cd',
                'type' => 'missing_workflows',
                'message' => 'No GitHub Actions workflows found',
                'benefit' => 'Add CI/CD workflows for automated testing and quality checks',
            ];

            return;
        }

        $workflowFiles = File::glob($workflowsPath . '/*.yml');

        if (empty($workflowFiles)) {
            $recommendations[] = [
                'category' => 'ci-cd',
                'type' => 'empty_workflows',
                'message' => 'No workflow files found in .github/workflows',
                'benefit' => 'Add automated testing workflows for continuous integration',
            ];

            return;
        }

        // Check for tests workflow
        $hasTestsWorkflow = false;
        $testWorkflowContent = null;

        foreach ($workflowFiles as $file) {
            $filename = basename($file);
            if (str_contains($filename, 'test')) {
                $hasTestsWorkflow = true;
                $testWorkflowContent = File::get($file);
                break;
            }
        }

        if (!$hasTestsWorkflow) {
            $recommendations[] = [
                'category' => 'ci-cd',
                'type' => 'missing_tests_workflow',
                'message' => 'No automated testing workflow found',
                'benefit' => 'Add tests.yml workflow for continuous integration',
            ];
        } else {
            $goodPractices[] = [
                'category' => 'ci-cd',
                'message' => 'Automated testing workflow configured',
                'details' => 'GitHub Actions workflow for continuous integration',
            ];

            // Analyze test workflow quality
            if ($testWorkflowContent) {
                $this->analyzeWorkflowQuality($testWorkflowContent, $recommendations, $goodPractices);
            }
        }

        // Check for Dependabot configuration
        $this->analyzeDependabot($recommendations, $goodPractices);
    }

    private function analyzeWorkflowQuality(string $content, array &$recommendations, array &$goodPractices): void
    {
        // Check for parallel testing
        if (str_contains($content, '--parallel') || str_contains($content, 'shard')) {
            $goodPractices[] = [
                'category' => 'ci-cd',
                'message' => 'Parallel testing configured',
                'details' => 'Tests run in parallel for faster CI/CD pipeline',
            ];
        } else {
            $recommendations[] = [
                'category' => 'ci-cd',
                'type' => 'performance',
                'message' => 'Enable parallel testing in GitHub Actions',
                'benefit' => 'Use --parallel flag or matrix shards to speed up test execution by 3-5x',
            ];
        }

        // Check for quality gates
        $hasQualityGates = str_contains($content, 'pint') &&
                          str_contains($content, 'rector') &&
                          (str_contains($content, 'phpstan') || str_contains($content, 'larastan'));

        if ($hasQualityGates) {
            $goodPractices[] = [
                'category' => 'ci-cd',
                'message' => 'Multiple quality gates configured (Pint, Rector, PHPStan)',
                'details' => 'Comprehensive code quality checks before merging',
            ];
        } else {
            $recommendations[] = [
                'category' => 'ci-cd',
                'type' => 'quality',
                'message' => 'Add quality gate jobs: Pint, Rector, PHPStan',
                'benefit' => 'Catch code style, quality, and type issues before deployment',
            ];
        }

        // Check for type coverage
        if (str_contains($content, 'type-coverage')) {
            $goodPractices[] = [
                'category' => 'ci-cd',
                'message' => 'Type coverage check configured',
                'details' => 'Pest v4 type coverage ensures 100% type safety',
            ];
        } else {
            $recommendations[] = [
                'category' => 'ci-cd',
                'type' => 'quality',
                'message' => 'Add Pest type coverage check: pest --type-coverage --min=100',
                'benefit' => 'Ensures complete type safety across your codebase',
            ];
        }

        // Check for caching
        if (str_contains($content, 'actions/cache')) {
            $goodPractices[] = [
                'category' => 'ci-cd',
                'message' => 'Dependency caching configured',
                'details' => 'Faster CI/CD with cached Composer and npm dependencies',
            ];
        }

        // Check for artifacts
        if (str_contains($content, 'upload-artifact')) {
            $goodPractices[] = [
                'category' => 'ci-cd',
                'message' => 'Build artifacts sharing configured',
                'details' => 'Efficient workflow with artifact sharing between jobs',
            ];
        }
    }

    private function analyzeDependabot(array &$recommendations, array &$goodPractices): void
    {
        $dependabotPaths = [
            base_path('.github/dependabot.yml'),
            base_path('.github/dependabot.yaml'),
        ];

        $hasDependabot = false;
        $dependabotConfig = null;

        foreach ($dependabotPaths as $path) {
            if (File::exists($path)) {
                $hasDependabot = true;
                $dependabotConfig = File::get($path);
                break;
            }
        }

        if ($hasDependabot) {
            $goodPractices[] = [
                'category' => 'dependency-management',
                'message' => 'Dependabot configured for automated dependency updates',
                'details' => 'Keeps packages up-to-date with automated pull requests',
            ];

            // Check for multiple ecosystems
            if ($dependabotConfig) {
                $ecosystems = [];
                if (str_contains($dependabotConfig, 'package-ecosystem: composer')) {
                    $ecosystems[] = 'Composer';
                }
                if (str_contains($dependabotConfig, 'package-ecosystem: npm') ||
                    str_contains($dependabotConfig, 'package-ecosystem: pnpm')) {
                    $ecosystems[] = 'npm/pnpm';
                }
                if (str_contains($dependabotConfig, 'package-ecosystem: github-actions')) {
                    $ecosystems[] = 'GitHub Actions';
                }

                if (count($ecosystems) > 1) {
                    $goodPractices[] = [
                        'category' => 'dependency-management',
                        'message' => 'Dependabot monitors multiple ecosystems: ' . implode(', ', $ecosystems),
                        'details' => 'Comprehensive dependency monitoring across all package managers',
                    ];
                } elseif (count($ecosystems) === 1 && $ecosystems[0] === 'Composer') {
                    $recommendations[] = [
                        'category' => 'dependency-management',
                        'type' => 'enhancement',
                        'message' => 'Extend Dependabot to monitor npm/pnpm and GitHub Actions',
                        'benefit' => 'Keep frontend dependencies and workflow actions up-to-date automatically',
                    ];
                }
            }

            // Check for auto-merge workflow
            $workflowsPath = base_path('.github/workflows');
            $hasAutoMerge = false;

            if (File::exists($workflowsPath)) {
                $workflowFiles = File::glob($workflowsPath . '/*.yml');
                foreach ($workflowFiles as $file) {
                    $filename = basename($file);
                    if (str_contains($filename, 'dependabot') && str_contains($filename, 'merge')) {
                        $hasAutoMerge = true;
                        break;
                    }
                }
            }

            if ($hasAutoMerge) {
                $goodPractices[] = [
                    'category' => 'dependency-management',
                    'message' => 'Dependabot auto-merge workflow configured',
                    'details' => 'Automatically merges minor/patch updates after tests pass',
                ];
            } else {
                $recommendations[] = [
                    'category' => 'dependency-management',
                    'type' => 'automation',
                    'message' => 'Add dependabot-auto-merge workflow for patch/minor updates',
                    'benefit' => 'Automatically merge safe dependency updates after CI passes, reducing manual PR reviews',
                ];
            }
        } else {
            $recommendations[] = [
                'category' => 'dependency-management',
                'type' => 'automation',
                'message' => 'Configure Dependabot for automated dependency updates',
                'benefit' => 'Automatically creates PRs for package updates, keeping dependencies secure and current',
            ];

            $recommendations[] = [
                'category' => 'dependency-management',
                'type' => 'setup-guide',
                'message' => 'Create .github/dependabot.yml with composer, npm, and github-actions ecosystems',
                'benefit' => 'Enable automated security patches and version updates across all package managers',
            ];
        }

        // Check for Renovate as alternative
        $renovateConfigs = [
            base_path('renovate.json'),
            base_path('.github/renovate.json'),
            base_path('renovate.json5'),
        ];

        $hasRenovate = false;
        foreach ($renovateConfigs as $config) {
            if (File::exists($config)) {
                $hasRenovate = true;
                break;
            }
        }

        if ($hasRenovate) {
            $goodPractices[] = [
                'category' => 'dependency-management',
                'message' => 'Renovate Bot configured (alternative to Dependabot)',
                'details' => 'Advanced dependency management with customizable update strategies',
            ];
        }

        // If neither is configured, recommend both options
        if (!$hasDependabot && !$hasRenovate) {
            $recommendations[] = [
                'category' => 'dependency-management',
                'type' => 'alternatives',
                'message' => 'Alternative: Renovate Bot offers more configuration options than Dependabot',
                'benefit' => 'Renovate provides monorepo support, auto-merging, and advanced scheduling options',
            ];
        }
    }
}
