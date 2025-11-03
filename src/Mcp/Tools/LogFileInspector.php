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
final class LogFileInspector extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'Inspect log file sizes and check if log rotation is configured';

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
            Artisan::call('optimize-mcp:log-size');
            $output = Artisan::output();

            // Parse JSON output from command
            $data = json_decode($output, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return Response::json([
                    'error' => true,
                    'message' => 'Failed to parse log file information',
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
                'total_files' => $data['total_files'] ?? 0,
                'total_size_mb' => $data['total_size_mb'] ?? 0,
                'rotation_configured' => $data['rotation']['configured'] ?? false,
                'rotation_method' => $data['rotation']['method'] ?? null,
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
        $lines[] = "📄 Log File Inspection";
        $lines[] = "";
        $lines[] = "Log Path: {$data['log_path']}";
        $lines[] = "";

        // Total statistics
        $lines[] = "💾 Total Statistics:";
        $lines[] = "  • Files: {$data['total_files']}";
        $lines[] = "  • Total Size: {$data['total_size_mb']} MB";
        if ($data['total_size_gb'] > 0.1) {
            $lines[] = "  • Total Size: {$data['total_size_gb']} GB";
        }
        $lines[] = "";

        // Log files
        $logs = $data['logs'] ?? [];
        if (!empty($logs)) {
            $lines[] = "📋 Log Files (".count($logs)."):";
            $lines[] = "";

            // Show all log files with their details
            foreach ($logs as $log) {
                $name = $log['name'];
                $sizeMb = $log['size_mb'];
                $age = $log['age_days'];
                $modified = $log['modified_at'];

                $lines[] = "  📁 {$name}";
                $lines[] = "     Size: {$sizeMb} MB ({$log['size_kb']} KB)";
                $lines[] = "     Modified: {$modified} ({$age} days old)";

                // Add warning for large files
                if ($sizeMb > 100) {
                    $lines[] = "     ⚠️ WARNING: File is very large (>{$sizeMb} MB)";
                } elseif ($sizeMb > 50) {
                    $lines[] = "     🟡 NOTICE: File size is growing large";
                }

                $lines[] = "";
            }
        } else {
            $lines[] = "No log files found in {$data['log_path']}";
            $lines[] = "";
        }

        // Log rotation status
        $rotation = $data['rotation'] ?? [];
        $lines[] = "🔄 Log Rotation:";
        $lines[] = "";

        if ($rotation['configured'] ?? false) {
            $lines[] = "  ✅ Status: Configured";
            $lines[] = "  📝 Method: {$rotation['method']}";
            if (!empty($rotation['details'])) {
                $lines[] = "";
                $lines[] = "  Details:";
                foreach ($rotation['details'] as $detail) {
                    $lines[] = "    • {$detail}";
                }
            }
        } else {
            $lines[] = "  ❌ Status: Not Configured";
            $lines[] = "  ⚠️ Log files will grow indefinitely without rotation";
        }

        // Recommendations
        if (!empty($rotation['recommendations'])) {
            $lines[] = "";
            $lines[] = "💡 Recommendations:";
            foreach ($rotation['recommendations'] as $recommendation) {
                $lines[] = "  • {$recommendation}";
            }
        }

        // Configuration details
        $config = $data['configuration'] ?? [];
        if (!empty($config)) {
            $lines[] = "";
            $lines[] = "⚙️ Logging Configuration:";
            $lines[] = "  • Default Channel: {$config['default_channel']}";

            if (!empty($config['channel_config'])) {
                $channelConfig = $config['channel_config'];
                $lines[] = "  • Driver: {$channelConfig['driver']}";
                $lines[] = "  • Level: {$channelConfig['level']}";

                if (isset($channelConfig['days'])) {
                    $lines[] = "  • Retention: {$channelConfig['days']} days";
                }

                if (isset($channelConfig['path'])) {
                    $lines[] = "  • Path: {$channelConfig['path']}";
                }
            }
        }

        // Additional insights
        if (!empty($logs)) {
            $lines[] = "";
            $lines[] = "🔍 Insights:";

            $largestLog = $logs[0] ?? null;
            if ($largestLog) {
                $lines[] = "  • Largest file: {$largestLog['name']} ({$largestLog['size_mb']} MB)";
            }

            // Check for old log files
            $oldLogs = array_filter($logs, fn ($log) => $log['age_days'] > 30);
            if (!empty($oldLogs)) {
                $lines[] = "  • Found ".count($oldLogs)." log file(s) older than 30 days";
            }

            // Check for pattern (daily rotation)
            $dailyLogPattern = array_filter($logs, fn ($log) => preg_match('/laravel-\d{4}-\d{2}-\d{2}\.log/', $log['name']));
            if (!empty($dailyLogPattern)) {
                $lines[] = "  • Daily log rotation pattern detected (".count($dailyLogPattern)." daily files)";
            }

            // Check total size
            $totalMb = $data['total_size_mb'] ?? 0;
            if ($totalMb > 500) {
                $lines[] = "  • 🚨 Total log size is very high ({$totalMb} MB) - consider cleanup";
            } elseif ($totalMb > 200) {
                $lines[] = "  • ⚠️ Total log size is growing ({$totalMb} MB) - monitor closely";
            }
        }

        return implode("\n", $lines);
    }
}
