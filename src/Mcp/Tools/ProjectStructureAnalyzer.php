<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Mcp\Tools;

use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Skylence\OptimizeMcp\Mcp\Helpers\StubHelper;
use Skylence\OptimizeMcp\Mcp\Tools\Analyzers\ComposerScriptsAnalyzer;
use Skylence\OptimizeMcp\Mcp\Tools\Analyzers\DeploymentProcessAnalyzer;
use Skylence\OptimizeMcp\Mcp\Tools\Analyzers\GitHooksAnalyzer;
use Skylence\OptimizeMcp\Mcp\Tools\Analyzers\GitHubWorkflowAnalyzer;
use Skylence\OptimizeMcp\Mcp\Tools\Analyzers\PackageJsonAnalyzer;
use Skylence\OptimizeMcp\Mcp\Tools\Analyzers\TestingSetupAnalyzer;

final class ProjectStructureAnalyzer extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'Analyze project structure including composer scripts, GitHub workflows, testing setup, Git hooks, and deployment process';

    /**
     * Get the tool's input schema.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'include_actions' => $schema->boolean()
                ->description('Include actionable recommendations with stub file contents and installation commands')
                ->default(false),
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $params = $request->all();
        $includeActions = $params['include_actions'] ?? false;

        $issues = [];
        $recommendations = [];
        $goodPractices = [];

        // Run all analyzers
        $analyzers = [
            new ComposerScriptsAnalyzer(),
            new GitHubWorkflowAnalyzer(),
            new PackageJsonAnalyzer(),
            new TestingSetupAnalyzer(),
            new GitHooksAnalyzer(),
            new DeploymentProcessAnalyzer(),
        ];

        foreach ($analyzers as $analyzer) {
            $analyzer->analyze($issues, $recommendations, $goodPractices);
        }

        $actions = [];
        if ($includeActions) {
            $actions = $this->buildActions($recommendations);
        }

        $summary = $this->buildSummary($issues, $recommendations, $goodPractices, $includeActions);

        return Response::json([
            'summary' => $summary,
            'issues' => $issues,
            'recommendations' => $recommendations,
            'good_practices' => $goodPractices,
            'actions' => $actions,
            'severity_counts' => [
                'critical' => count(array_filter($issues, fn($i) => $i['severity'] === 'critical')),
                'warning' => count(array_filter($issues, fn($i) => $i['severity'] === 'warning')),
            ],
        ]);
    }

    /**
     * Build actionable recommendations with stub contents.
     */
    private function buildActions(array $recommendations): array
    {
        $actions = [];

        foreach ($recommendations as $rec) {
            $action = [
                'category' => $rec['category'],
                'message' => $rec['message'],
                'type' => null,
                'command' => null,
                'stub_file' => null,
                'stub_destination' => null,
                'stub_contents' => null,
            ];

            // Map recommendations to actionable items
            switch ($rec['category']) {
                case 'ci-cd':
                    if (str_contains($rec['message'], 'GitHub Actions')) {
                        $action['type'] = 'copy_directory';
                        $action['stub_file'] = '.github';
                        $action['stub_destination'] = '.github';
                        $action['files'] = [
                            '.github/workflows/tests.yml' => StubHelper::getStubContents('.github/workflows/tests.yml'),
                            '.github/workflows/dependabot-auto-merge.yml' => StubHelper::getStubContents('.github/workflows/dependabot-auto-merge.yml'),
                            '.github/actions/setup/action.yml' => StubHelper::getStubContents('.github/actions/setup/action.yml'),
                            '.github/dependabot.yml' => StubHelper::getStubContents('.github/dependabot.yml'),
                        ];
                    }
                    break;

                case 'git-hooks':
                    if (str_contains($rec['message'], 'CaptainHook')) {
                        $action['type'] = 'install_and_copy';
                        $action['command'] = 'composer require --dev captainhook/captainhook';
                        $action['stub_file'] = 'captainhook.json';
                        $action['stub_destination'] = 'captainhook.json';
                        $action['stub_contents'] = StubHelper::getStubContents('captainhook.json');
                    } elseif (str_contains($rec['message'], 'GrumPHP')) {
                        $action['type'] = 'install';
                        $action['command'] = 'composer require --dev phpro/grumphp';
                    }
                    break;

                case 'deployment':
                    if (str_contains($rec['message'], 'deployer')) {
                        $action['type'] = 'install_and_copy';
                        $action['command'] = 'composer require --dev deployer/deployer';
                        $action['stub_file'] = 'deploy.php';
                        $action['stub_destination'] = 'deploy.php';
                        $action['stub_contents'] = StubHelper::getStubContents('deploy.php');
                    }
                    break;

                case 'composer':
                    if (str_contains($rec['message'], 'scripts')) {
                        $action['type'] = 'merge_json';
                        $action['stub_file'] = 'composer-scripts.json';
                        $action['stub_destination'] = 'composer.json';
                        $action['stub_contents'] = StubHelper::getStubContents('composer-scripts.json');
                    }
                    break;

                case 'testing':
                case 'tooling':
                    // These usually require package installation
                    if (str_contains($rec['message'], 'composer require')) {
                        $pattern = '/composer require ([^\s]+)/';
                        if (preg_match($pattern, $rec['message'], $matches)) {
                            $action['type'] = 'install';
                            $action['command'] = 'composer require ' . $matches[1];
                        }
                    }
                    break;

                case 'frontend-testing':
                    if (str_contains($rec['message'], 'Playwright')) {
                        $action['type'] = 'install';
                        $action['command'] = 'pnpm add -D playwright';
                    }
                    break;
            }

            if ($action['type']) {
                $actions[] = $action;
            }
        }

        return $actions;
    }

    /**
     * Build human-readable summary.
     */
    private function buildSummary(array $issues, array $recommendations, array $goodPractices, bool $includeActions = false): string
    {
        $lines = [];
        $lines[] = "📋 Project Structure Analysis";
        $lines[] = "";

        // Critical issues
        $critical = array_filter($issues, fn($i) => $i['severity'] === 'critical');
        if (!empty($critical)) {
            $lines[] = "🚨 CRITICAL ISSUES (" . count($critical) . "):";
            foreach ($critical as $issue) {
                $lines[] = "  • {$issue['file']}: {$issue['message']}";
            }
            $lines[] = "";
        }

        // Warnings
        $warnings = array_filter($issues, fn($i) => $i['severity'] === 'warning');
        if (!empty($warnings)) {
            $lines[] = "⚠️ WARNINGS (" . count($warnings) . "):";
            foreach ($warnings as $warning) {
                $lines[] = "  • {$warning['file']}: {$warning['message']}";
            }
            $lines[] = "";
        }

        // Good practices
        if (!empty($goodPractices)) {
            $lines[] = "✅ GOOD PRACTICES (" . count($goodPractices) . "):";
            foreach ($goodPractices as $practice) {
                $lines[] = "  • [{$practice['category']}] {$practice['message']}";
                if (isset($practice['details'])) {
                    $lines[] = "    → {$practice['details']}";
                }
            }
            $lines[] = "";
        }

        // Recommendations
        if (!empty($recommendations)) {
            $lines[] = "💡 RECOMMENDATIONS (" . count($recommendations) . "):";
            foreach ($recommendations as $rec) {
                $lines[] = "  • [{$rec['category']}] {$rec['message']}";
                $lines[] = "    → {$rec['benefit']}";
            }
            $lines[] = "";
        }

        // Summary
        if (empty($issues) && empty($recommendations)) {
            $lines[] = "🎉 Excellent project structure! No issues found.";
        } else {
            $lines[] = "📊 Summary:";
            $lines[] = "  • Critical Issues: " . count($critical);
            $lines[] = "  • Warnings: " . count($warnings);
            $lines[] = "  • Good Practices: " . count($goodPractices);
            $lines[] = "  • Recommendations: " . count($recommendations);
        }

        if ($includeActions && !empty($recommendations)) {
            $lines[] = "";
            $lines[] = "💡 TIP: Actionable recommendations with stub files and commands are included in the 'actions' field.";
            $lines[] = "   Each action contains installation commands and/or stub file contents ready to use.";
        }

        return implode("\n", $lines);
    }
}
