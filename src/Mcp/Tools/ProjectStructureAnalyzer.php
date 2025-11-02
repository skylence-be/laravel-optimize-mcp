<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
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
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
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

        $summary = $this->buildSummary($issues, $recommendations, $goodPractices);

        return Response::json([
            'summary' => $summary,
            'issues' => $issues,
            'recommendations' => $recommendations,
            'good_practices' => $goodPractices,
            'severity_counts' => [
                'critical' => count(array_filter($issues, fn($i) => $i['severity'] === 'critical')),
                'warning' => count(array_filter($issues, fn($i) => $i['severity'] === 'warning')),
            ],
        ]);
    }

    /**
     * Build human-readable summary.
     */
    private function buildSummary(array $issues, array $recommendations, array $goodPractices): string
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

        return implode("\n", $lines);
    }
}
