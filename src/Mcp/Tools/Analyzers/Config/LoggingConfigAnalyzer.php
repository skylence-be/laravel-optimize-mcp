<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Mcp\Tools\Analyzers\Config;

final class LoggingConfigAnalyzer extends AbstractConfigAnalyzer
{
    public function analyze(array &$issues, array &$recommendations, string $environment): void
    {
        // Check log channel configuration
        $defaultChannel = config('logging.default', 'stack');
        $channels = config('logging.channels', []);

        // Check if log rotation is configured
        $rotationConfigured = $this->isLogRotationConfigured($defaultChannel, $channels);

        if (! $rotationConfigured) {
            $severity = $environment === 'production' ? 'warning' : 'info';

            $issues[] = [
                'severity' => $severity,
                'category' => 'logging',
                'config' => 'logging.default',
                'current' => $defaultChannel,
                'message' => 'Log rotation is not configured - logs will grow indefinitely',
                'fix' => 'Set LOG_CHANNEL=daily in .env or configure logrotate',
            ];

            $recommendations[] = [
                'category' => 'logging',
                'config' => 'logging.default',
                'message' => 'Enable daily log rotation to prevent disk space issues',
                'benefit' => 'Automatically rotates logs daily and manages retention period',
                'setup' => 'Set LOG_CHANNEL=daily and LOG_DAILY_DAYS=14 (or desired retention) in .env',
            ];
        }

        // Check log level for production
        if ($environment === 'production') {
            $level = config('logging.level', 'debug');
            if (in_array($level, ['debug', 'info'])) {
                $recommendations[] = [
                    'category' => 'logging',
                    'config' => 'logging.level',
                    'message' => "Log level '{$level}' may create excessive logs in production",
                    'benefit' => 'Setting level to "warning" or "error" reduces log size and improves performance',
                    'setup' => 'Set LOG_LEVEL=warning in .env',
                ];
            }
        }

        // Check for single driver in production (no rotation)
        if ($defaultChannel === 'single' && $environment === 'production') {
            $issues[] = [
                'severity' => 'warning',
                'category' => 'logging',
                'config' => 'logging.default',
                'current' => 'single',
                'message' => 'Using single log file without rotation in production',
                'fix' => 'Switch to "daily" driver: Set LOG_CHANNEL=daily in .env',
            ];
        }

        // Check stack configuration
        if ($defaultChannel === 'stack') {
            $stackChannels = config('logging.channels.stack.channels', []);

            if (! in_array('daily', $stackChannels)) {
                $recommendations[] = [
                    'category' => 'logging',
                    'config' => 'logging.channels.stack.channels',
                    'message' => 'Stack driver does not include daily rotation',
                    'benefit' => 'Add "daily" channel to stack for automatic log rotation',
                ];
            }
        }

        // Check retention period for daily logs
        if ($defaultChannel === 'daily' || in_array('daily', config('logging.channels.stack.channels', []))) {
            $days = config('logging.channels.daily.days', 14);

            if ($days > 30 && $environment === 'production') {
                $recommendations[] = [
                    'category' => 'logging',
                    'config' => 'logging.channels.daily.days',
                    'message' => "Log retention is set to {$days} days - consider shorter retention",
                    'benefit' => 'Shorter retention (14-30 days) saves disk space and improves cleanup performance',
                    'setup' => 'Set LOG_DAILY_DAYS=14 in .env',
                ];
            }
        }
    }

    /**
     * Check if log rotation is configured.
     */
    private function isLogRotationConfigured(string $defaultChannel, array $channels): bool
    {
        // Check if daily rotation is configured
        if (isset($channels['daily']) || $defaultChannel === 'daily') {
            return true;
        }

        // Check if using stack driver with daily
        if ($defaultChannel === 'stack') {
            $stackChannels = config('logging.channels.stack.channels', []);
            if (in_array('daily', $stackChannels)) {
                return true;
            }
        }

        // Check for logrotate (Linux/Unix systems)
        if (PHP_OS_FAMILY !== 'Windows') {
            $logrotateConfig = '/etc/logrotate.d/laravel';
            if (file_exists($logrotateConfig)) {
                return true;
            }
        }

        return false;
    }
}
