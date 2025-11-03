<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('optimize-mcp:log-size', 'Get log file sizes and check log rotation configuration')]
class LogSizeCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $data = $this->getLogFileInformation();

            // Output as JSON for easy consumption by MCP tool
            $this->output->write(json_encode($data, JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->output->write(json_encode([
                'error' => true,
                'message' => $e->getMessage(),
            ], JSON_PRETTY_PRINT));

            return Command::FAILURE;
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
        if (!$config['configured']) {
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
}
