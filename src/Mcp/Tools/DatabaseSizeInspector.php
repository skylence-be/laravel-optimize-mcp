<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Mcp\Tools;

use Illuminate\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Artisan;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Skylence\OptimizeMcp\Models\DatabaseTableSizeLog;

#[IsReadOnly]
final class DatabaseSizeInspector extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'Inspect database size including total database size and individual table sizes with row counts';

    /**
     * Get the tool's input schema.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'format' => $schema->string()
                ->description('Output format: summary (human-readable) or detailed (full JSON)')
                ->enum(['summary', 'detailed'])
                ->default('summary'),
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $params = $request->all();
        $format = $params['format'] ?? 'summary';

        try {
            // Call the console command and capture output
            Artisan::call('optimize-mcp:database-size');
            $output = Artisan::output();

            // Parse JSON output from command
            $data = json_decode($output, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return Response::json([
                    'error' => true,
                    'message' => 'Failed to parse database size information',
                    'raw_output' => $output,
                ]);
            }

            if (isset($data['error']) && $data['error']) {
                return Response::json($data);
            }

            if ($format === 'detailed') {
                return Response::json($data);
            }

            // Build human-readable summary
            $summary = $this->buildSummary($data);

            return Response::json([
                'summary' => $summary,
                'total_size_mb' => $data['total_size_mb'] ?? 0,
                'total_size_gb' => $data['total_size_gb'] ?? 0,
                'max_size_mb' => $data['max_size_mb'] ?? null,
                'max_size_gb' => $data['max_size_gb'] ?? null,
                'usage_percentage' => $data['usage_percentage'] ?? null,
                'table_count' => count($data['tables'] ?? []),
                'driver' => $data['driver'] ?? 'unknown',
            ]);
        } catch (\Exception $e) {
            return Response::json([
                'error' => true,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build human-readable summary.
     */
    private function buildSummary(array $data): string
    {
        $lines = [];
        $lines[] = "📊 Database Size Inspection";
        $lines[] = "";
        $lines[] = "Database: {$data['database']}";
        $lines[] = "Driver: {$data['driver']}";
        $lines[] = "";

        // Total size
        $lines[] = "💾 Total Size:";
        $lines[] = "  • {$data['total_size_mb']} MB ({$data['total_size_gb']} GB)";
        $lines[] = "  • {$data['total_size_bytes']} bytes";

        // Max size and usage (if available)
        if (isset($data['max_size_gb']) && $data['max_size_gb'] !== null) {
            $lines[] = "";
            $lines[] = "📈 Disk Usage:";
            $lines[] = "  • Max Available: {$data['max_size_gb']} GB ({$data['max_size_mb']} MB)";
            $lines[] = "  • Usage: {$data['usage_percentage']}%";

            // Add visual indicator
            $usage = $data['usage_percentage'];
            if ($usage >= 90) {
                $lines[] = "  • Status: 🚨 CRITICAL - Immediate action required";
            } elseif ($usage >= 80) {
                $lines[] = "  • Status: ⚠️ WARNING - Action recommended";
            } elseif ($usage >= 70) {
                $lines[] = "  • Status: 🟡 NOTICE - Monitor closely";
            } else {
                $lines[] = "  • Status: ✅ HEALTHY";
            }
        }

        $lines[] = "";

        // Tables
        $tables = $data['tables'] ?? [];
        if (!empty($tables)) {
            $lines[] = "📋 Tables (" . count($tables) . "):";
            $lines[] = "";

            // Show top 10 largest tables
            $topTables = array_slice($tables, 0, 10);
            foreach ($topTables as $table) {
                $name = $table['name'];
                $sizeMb = $table['size_mb'] ?? 'N/A';
                $rows = isset($table['rows']) ? number_format($table['rows']) : 'N/A';

                $lines[] = "  📁 {$name}";
                if (isset($table['size_mb'])) {
                    $lines[] = "     Size: {$sizeMb} MB";
                    if (isset($table['data_size_mb']) && isset($table['index_size_mb'])) {
                        $lines[] = "     Data: {$table['data_size_mb']} MB | Indexes: {$table['index_size_mb']} MB";
                    }
                }
                $lines[] = "     Rows: {$rows}";
                $lines[] = "";
            }

            if (count($tables) > 10) {
                $remaining = count($tables) - 10;
                $lines[] = "  ... and {$remaining} more tables";
                $lines[] = "";
            }
        }

        // Insights
        if (!empty($tables)) {
            $totalRows = array_sum(array_column($tables, 'rows'));
            $largestTable = $tables[0] ?? null;

            $lines[] = "🔍 Insights:";
            if ($totalRows > 0) {
                $lines[] = "  • Total rows across all tables: " . number_format($totalRows);
            }
            if ($largestTable) {
                $lines[] = "  • Largest table: {$largestTable['name']} ({$largestTable['size_mb']} MB)";
            }
        }

        // Growth insights (if monitoring is enabled)
        if (config('optimize-mcp.database_monitoring.enabled', false)) {
            $growthInsights = $this->getGrowthInsights($data['database'] ?? null);
            if (!empty($growthInsights)) {
                $lines[] = "";
                $lines[] = "📈 Growth Trends (from monitoring):";
                foreach ($growthInsights as $insight) {
                    $lines[] = $insight;
                }
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Get growth insights from historical data.
     */
    private function getGrowthInsights(?string $database): array
    {
        if (!$database) {
            return [];
        }

        $insights = [];

        // Get fastest growing tables (by percentage)
        $fastestGrowing = DatabaseTableSizeLog::query()
            ->whereHas('databaseSizeLog', function ($query) use ($database) {
                $query->where('database_name', $database);
            })
            ->whereNotNull('growth_percentage')
            ->where('growth_percentage', '>', 0)
            ->orderBy('growth_percentage', 'desc')
            ->limit(3)
            ->get();

        if ($fastestGrowing->isNotEmpty()) {
            $insights[] = "  🚀 Fastest Growing Tables:";
            foreach ($fastestGrowing as $table) {
                $growthSign = $table->growth_percentage >= 0 ? '+' : '';
                $insights[] = "     • {$table->table_name}: {$growthSign}{$table->growth_percentage}% ({$growthSign}{$table->growth_mb} MB)";
                if ($table->row_growth && $table->row_growth > 0) {
                    $rowPercentage = $table->row_growth_percentage !== null
                        ? "{$growthSign}{$table->row_growth_percentage}%"
                        : 'from 0 rows';
                    $insights[] = "       Rows: {$growthSign}" . number_format($table->row_growth) . " ({$rowPercentage})";
                }
            }
        }

        // Get tables with most absolute growth (by MB)
        $largestGrowth = DatabaseTableSizeLog::query()
            ->whereHas('databaseSizeLog', function ($query) use ($database) {
                $query->where('database_name', $database);
            })
            ->whereNotNull('growth_mb')
            ->where('growth_mb', '>', 0)
            ->orderBy('growth_mb', 'desc')
            ->limit(3)
            ->get();

        if ($largestGrowth->isNotEmpty() && $largestGrowth->first()->growth_mb > 0.1) {
            $insights[] = "";
            $insights[] = "  📊 Largest Absolute Growth:";
            foreach ($largestGrowth as $table) {
                $growthSign = $table->growth_mb >= 0 ? '+' : '';
                $insights[] = "     • {$table->table_name}: {$growthSign}{$table->growth_mb} MB";
            }
        }

        // Add recommendations based on growth
        if ($fastestGrowing->isNotEmpty()) {
            $insights[] = "";
            $insights[] = "  💡 Recommendations:";

            $topGrowing = $fastestGrowing->first();
            if ($topGrowing->growth_percentage > 50) {
                $insights[] = "     • Consider archiving old data in {$topGrowing->table_name}";
            }

            // Check if it's a known table type
            $tableName = $topGrowing->table_name;
            if (str_contains($tableName, 'telescope')) {
                $insights[] = "     • Run: php artisan telescope:prune to clean old entries";
            } elseif (str_contains($tableName, 'log')) {
                $insights[] = "     • Implement log rotation or archival for {$tableName}";
            } elseif (str_contains($tableName, 'cache') || str_contains($tableName, 'session')) {
                $insights[] = "     • Review cache/session retention policies";
            }
        }

        return $insights;
    }
}
