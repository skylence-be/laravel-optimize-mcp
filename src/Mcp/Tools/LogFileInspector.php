<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
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
     * Check if running in HTTP context (vs stdio).
     */
    protected function isHttpContext(): bool
    {
        if (! app()->bound('request')) {
            return false;
        }

        try {
            $request = app('request');

            return $request instanceof \Illuminate\Http\Request && ! app()->runningInConsole();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        // This tool is only available for HTTP MCP (remote servers)
        if (! $this->isHttpContext()) {
            return Response::json([
                'error' => true,
                'message' => 'LogFileInspector is only available for HTTP MCP (remote servers). For local development, use ConfigurationAnalyzer which includes log rotation checks.',
            ]);
        }

        $params = $request->all();
        $format = $params['format'] ?? 'summary';

        try {
            $data = $this->getLogFileInformation();

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
     * Get log file information including sizes and rotation configuration.
     */
    protected function getLogFileInformation(): array
    {
        $logPath = storage_path('logs');
        $logs = [];
        $totalSize = 0;

        if (is_dir($logPath)) {
            $files = scandir($logPath);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                $filePath = $logPath.DIRECTORY_SEPARATOR.$file;
                if (is_file($filePath)) {
                    $size = filesize($filePath);
                    $totalSize += $size;

                    $logs[] = [
                        'name' => $file,
                        'path' => $filePath,
                        'size_bytes' => $size,
                        'size_kb' => round($size / 1024, 2),
                        'size_mb' => round($size / 1024 / 1024, 2),
                        'modified_at' => date('Y-m-d H:i:s', filemtime($filePath)),
                        'age_days' => floor((time() - filemtime($filePath)) / 86400),
                    ];
                }
            }

            // Sort by size descending
            usort($logs, fn ($a, $b) => $b['size_bytes'] <=> $a['size_bytes']);
        }

        // Check for log rotation configuration
        $rotationConfig = $this->checkLogRotationConfiguration();

        // Get logging configuration
        $loggingConfig = $this->getLoggingConfiguration();

        return [
            'log_path' => $logPath,
            'total_files' => count($logs),
            'total_size_bytes' => $totalSize,
            'total_size_kb' => round($totalSize / 1024, 2),
            'total_size_mb' => round($totalSize / 1024 / 1024, 2),
            'total_size_gb' => round($totalSize / 1024 / 1024 / 1024, 2),
            'logs' => $logs,
            'rotation' => $rotationConfig,
            'configuration' => $loggingConfig,
        ];
    }

    /**
     * Check for log rotation configuration.
     */
    protected function checkLogRotationConfiguration(): array
    {
        $config = [
            'configured' => false,
            'method' => null,
            'details' => [],
            'recommendations' => [],
        ];

        // Check Laravel logging configuration
        $channels = config('logging.channels', []);
        $defaultChannel = config('logging.default', 'stack');

        // Check if daily rotation is configured
        if (isset($channels['daily']) || $defaultChannel === 'daily') {
            $config['configured'] = true;
            $config['method'] = 'Laravel Daily Driver';
            $config['details'][] = 'Using Laravel\'s built-in daily log rotation';
            $config['details'][] = 'Log files are automatically rotated daily';

            $days = config('logging.channels.daily.days', 14);
            $config['details'][] = "Retention period: {$days} days";
        }

        // Check if using single driver (no rotation)
        if ($defaultChannel === 'single') {
            $config['method'] = 'Single file (No rotation)';
            $config['recommendations'][] = 'Consider switching to "daily" driver for automatic log rotation';
            $config['recommendations'][] = 'Large single log files can impact performance and disk space';
        }

        // Check if using stack driver
        if ($defaultChannel === 'stack') {
            $stackChannels = config('logging.channels.stack.channels', []);
            $config['method'] = 'Stack driver';
            $config['details'][] = 'Using stack driver with channels: '.implode(', ', $stackChannels);

            if (in_array('daily', $stackChannels)) {
                $config['configured'] = true;
                $config['details'][] = 'Daily rotation is enabled via stack';
            } else {
                $config['recommendations'][] = 'Consider adding "daily" to stack channels for log rotation';
            }
        }

        // Check for logrotate (Linux/Unix systems)
        if (PHP_OS_FAMILY !== 'Windows') {
            $logrotateConfig = '/etc/logrotate.d/laravel';
            if (file_exists($logrotateConfig)) {
                $config['configured'] = true;
                $config['method'] = ($config['method'] ?? '').' + System logrotate';
                $config['details'][] = 'System-level logrotate configuration found';
            } else {
                $config['recommendations'][] = 'Consider setting up system-level logrotate for additional log management';
            }
        }

        // Add general recommendations if not configured
        if (! $config['configured']) {
            $config['recommendations'][] = 'Enable log rotation to prevent disk space issues';
            $config['recommendations'][] = 'Set LOG_CHANNEL=daily in your .env file';
            $config['recommendations'][] = 'Configure log retention period using LOG_DAILY_DAYS in .env';
        }

        return $config;
    }

    /**
     * Get logging configuration details.
     */
    protected function getLoggingConfiguration(): array
    {
        $defaultChannel = config('logging.default', 'stack');
        $channels = config('logging.channels', []);

        $channelInfo = [];
        if (isset($channels[$defaultChannel])) {
            $channelConfig = $channels[$defaultChannel];
            $channelInfo = [
                'driver' => $channelConfig['driver'] ?? 'unknown',
                'path' => $channelConfig['path'] ?? null,
                'level' => $channelConfig['level'] ?? config('logging.level', 'debug'),
                'days' => $channelConfig['days'] ?? null,
                'channels' => $channelConfig['channels'] ?? null,
            ];
        }

        return [
            'default_channel' => $defaultChannel,
            'channel_config' => $channelInfo,
            'available_channels' => array_keys($channels),
        ];
    }

    /**
     * Build human-readable summary.
     */
    private function buildSummary(array $data): string
    {
        $lines = [];
        $lines[] = '📄 Log File Inspection';
        $lines[] = '';
        $lines[] = "Log Path: {$data['log_path']}";
        $lines[] = '';

        // Total statistics
        $lines[] = '💾 Total Statistics:';
        $lines[] = "  • Files: {$data['total_files']}";
        $lines[] = "  • Total Size: {$data['total_size_mb']} MB";
        if ($data['total_size_gb'] > 0.1) {
            $lines[] = "  • Total Size: {$data['total_size_gb']} GB";
        }
        $lines[] = '';

        // Log files
        $logs = $data['logs'] ?? [];
        if (! empty($logs)) {
            $lines[] = '📋 Log Files ('.count($logs).'):';
            $lines[] = '';

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
                    $lines[] = '     🟡 NOTICE: File size is growing large';
                }

                $lines[] = '';
            }
        } else {
            $lines[] = "No log files found in {$data['log_path']}";
            $lines[] = '';
        }

        // Log rotation status
        $rotation = $data['rotation'] ?? [];
        $lines[] = '🔄 Log Rotation:';
        $lines[] = '';

        if ($rotation['configured'] ?? false) {
            $lines[] = '  ✅ Status: Configured';
            $lines[] = "  📝 Method: {$rotation['method']}";
            if (! empty($rotation['details'])) {
                $lines[] = '';
                $lines[] = '  Details:';
                foreach ($rotation['details'] as $detail) {
                    $lines[] = "    • {$detail}";
                }
            }
        } else {
            $lines[] = '  ❌ Status: Not Configured';
            $lines[] = '  ⚠️ Log files will grow indefinitely without rotation';
        }

        // Recommendations
        if (! empty($rotation['recommendations'])) {
            $lines[] = '';
            $lines[] = '💡 Recommendations:';
            foreach ($rotation['recommendations'] as $recommendation) {
                $lines[] = "  • {$recommendation}";
            }
        }

        // Configuration details
        $config = $data['configuration'] ?? [];
        if (! empty($config)) {
            $lines[] = '';
            $lines[] = '⚙️ Logging Configuration:';
            $lines[] = "  • Default Channel: {$config['default_channel']}";

            if (! empty($config['channel_config'])) {
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
        if (! empty($logs)) {
            $lines[] = '';
            $lines[] = '🔍 Insights:';

            $largestLog = $logs[0] ?? null;
            if ($largestLog) {
                $lines[] = "  • Largest file: {$largestLog['name']} ({$largestLog['size_mb']} MB)";
            }

            // Check for old log files
            $oldLogs = array_filter($logs, fn ($log) => $log['age_days'] > 30);
            if (! empty($oldLogs)) {
                $lines[] = '  • Found '.count($oldLogs).' log file(s) older than 30 days';
            }

            // Check for pattern (daily rotation)
            $dailyLogPattern = array_filter($logs, fn ($log) => preg_match('/laravel-\d{4}-\d{2}-\d{2}\.log/', $log['name']));
            if (! empty($dailyLogPattern)) {
                $lines[] = '  • Daily log rotation pattern detected ('.count($dailyLogPattern).' daily files)';
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
