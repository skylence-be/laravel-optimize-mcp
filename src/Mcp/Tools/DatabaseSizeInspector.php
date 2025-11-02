<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Mcp\Tools;

use Illuminate\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Artisan;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

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

        return implode("\n", $lines);
    }
}
