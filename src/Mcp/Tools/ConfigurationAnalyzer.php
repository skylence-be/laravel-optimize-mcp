<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Mcp\Tools;

use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Skylence\OptimizeMcp\Mcp\Tools\Analyzers\Config\AppConfigAnalyzer;
use Skylence\OptimizeMcp\Mcp\Tools\Analyzers\Config\CacheConfigAnalyzer;
use Skylence\OptimizeMcp\Mcp\Tools\Analyzers\Config\DatabaseConfigAnalyzer;
use Skylence\OptimizeMcp\Mcp\Tools\Analyzers\Config\EnvironmentDriversAnalyzer;
use Skylence\OptimizeMcp\Mcp\Tools\Analyzers\Config\PerformanceOptimizationsAnalyzer;
use Skylence\OptimizeMcp\Mcp\Tools\Analyzers\Config\QueueConfigAnalyzer;
use Skylence\OptimizeMcp\Mcp\Tools\Analyzers\Config\SessionConfigAnalyzer;
use Skylence\OptimizeMcp\Mcp\Tools\Analyzers\Config\TelescopeConfigAnalyzer;

final class ConfigurationAnalyzer extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'Analyze Laravel configuration for performance, security, and optimization opportunities';

    /**
     * Get the tool's input schema.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'environment' => $schema->string()
                ->description('Target environment: production, staging, or local (optional, will use APP_ENV)')
                ->enum(['production', 'staging', 'local']),
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $params = $request->all();
        $environment = $params['environment'] ?? config('app.env', 'production');

        $issues = [];
        $recommendations = [];
        $optimizations = [];

        // Run all config analyzers
        $configAnalyzers = [
            new AppConfigAnalyzer(),
            new EnvironmentDriversAnalyzer(),
            new CacheConfigAnalyzer(),
            new SessionConfigAnalyzer(),
            new QueueConfigAnalyzer(),
            new DatabaseConfigAnalyzer(),
            new TelescopeConfigAnalyzer(),
        ];

        foreach ($configAnalyzers as $analyzer) {
            $analyzer->analyze($issues, $recommendations, $environment);
        }

        // Run performance optimizations analyzer (different signature)
        $performanceAnalyzer = new PerformanceOptimizationsAnalyzer();
        $performanceAnalyzer->analyze($optimizations, $environment);

        $summary = $this->buildSummary($issues, $recommendations, $optimizations, $environment);

        return Response::json([
            'summary' => $summary,
            'environment' => $environment,
            'issues' => $issues,
            'recommendations' => $recommendations,
            'optimizations' => $optimizations,
            'severity_counts' => [
                'critical' => count(array_filter($issues, fn($i) => $i['severity'] === 'critical')),
                'warning' => count(array_filter($issues, fn($i) => $i['severity'] === 'warning')),
                'info' => count(array_filter($issues, fn($i) => ($i['severity'] ?? null) === 'info')),
            ],
        ]);
    }

    /**
     * Build human-readable summary.
     */
    private function buildSummary(array $issues, array $recommendations, array $optimizations, string $environment): string
    {
        $lines = [];
        $lines[] = "⚙️ Laravel Configuration Analysis";
        $lines[] = "";
        $lines[] = "Environment: " . strtoupper($environment);
        $lines[] = "";

        // Critical issues
        $critical = array_filter($issues, fn($i) => $i['severity'] === 'critical');
        if (!empty($critical)) {
            $lines[] = "🚨 CRITICAL ISSUES (" . count($critical) . "):";
            foreach ($critical as $issue) {
                $lines[] = "  • {$issue['config']}: {$issue['message']}";
                $lines[] = "    Fix: {$issue['fix']}";
            }
            $lines[] = "";
        }

        // Warnings
        $warnings = array_filter($issues, fn($i) => $i['severity'] === 'warning');
        if (!empty($warnings)) {
            $lines[] = "⚠️ WARNINGS (" . count($warnings) . "):";
            foreach ($warnings as $warning) {
                $lines[] = "  • {$warning['config']}: {$warning['message']}";
                $lines[] = "    Fix: {$warning['fix']}";
            }
            $lines[] = "";
        }

        // Performance optimizations
        $disabled = array_filter($optimizations, fn($o) => $o['status'] === 'disabled' && $o['recommended_for'] !== 'optional');
        if (!empty($disabled)) {
            $lines[] = "🚀 PERFORMANCE OPTIMIZATIONS:";
            foreach ($disabled as $opt) {
                $icon = $opt['recommended_for'] === 'critical' ? '🔴' : '🟡';
                $lines[] = "  {$icon} {$opt['type']}: {$opt['status']}";
                if (isset($opt['command'])) {
                    $lines[] = "    Run: {$opt['command']}";
                }
                $lines[] = "    → {$opt['benefit']}";
            }
            $lines[] = "";
        }

        // Recommendations
        if (!empty($recommendations)) {
            $lines[] = "💡 RECOMMENDATIONS (" . count($recommendations) . "):";
            foreach ($recommendations as $rec) {
                $lines[] = "  • {$rec['config']}: {$rec['message']}";
                $lines[] = "    → {$rec['benefit']}";
                if (isset($rec['setup'])) {
                    $lines[] = "    Setup: {$rec['setup']}";
                }
            }
            $lines[] = "";
        }

        // Summary
        if (empty($issues) && empty($disabled)) {
            $lines[] = "✅ Configuration looks good! No critical issues found.";
        } else {
            $lines[] = "📊 Summary:";
            $lines[] = "  • Critical Issues: " . count($critical);
            $lines[] = "  • Warnings: " . count($warnings);
            $lines[] = "  • Missing Optimizations: " . count($disabled);
            $lines[] = "  • Recommendations: " . count($recommendations);
        }

        return implode("\n", $lines);
    }
}
