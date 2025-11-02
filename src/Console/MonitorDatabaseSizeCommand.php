<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Skylence\OptimizeMcp\Models\DatabaseSizeLog;
use Skylence\OptimizeMcp\Models\DatabaseTableSizeLog;
use Skylence\OptimizeMcp\Notifications\DatabaseSizeWarning;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('optimize-mcp:monitor-database', 'Monitor database size and send alerts if thresholds exceeded')]
class MonitorDatabaseSizeCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Check if monitoring is enabled
        if (!config('optimize-mcp.database_monitoring.enabled', false)) {
            $this->info('Database monitoring is disabled. Enable it in config/optimize-mcp.php');
            return Command::SUCCESS;
        }

        $this->info('Monitoring database size...');

        try {
            // Get current database size information
            Artisan::call('optimize-mcp:database-size');
            $output = Artisan::output();

            $data = json_decode($output, true);

            if (json_last_error() !== JSON_ERROR_NONE || isset($data['error'])) {
                $this->error('Failed to get database size information');
                return Command::FAILURE;
            }

            // Create log entry
            $log = $this->createLogEntry($data);

            // Calculate growth and predictions
            $log->calculateGrowth();
            $log->calculatePrediction();
            $log->save();

            $this->info("Database size logged: {$log->total_size_mb} MB");

            // Log individual table sizes
            $this->logTableSizes($log, $data['tables'] ?? []);

            // Check thresholds and send notifications
            $this->checkThresholdsAndNotify($log);

            // Cleanup old logs
            $this->cleanupOldLogs();

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Error monitoring database: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    /**
     * Create a database size log entry from the data.
     */
    protected function createLogEntry(array $data): DatabaseSizeLog
    {
        // Get top 5 largest tables for reference
        $largestTables = array_slice($data['tables'] ?? [], 0, 5);

        return DatabaseSizeLog::create([
            'database_name' => $data['database'] ?? 'unknown',
            'driver' => $data['driver'] ?? 'unknown',
            'total_size_bytes' => $data['total_size_bytes'] ?? 0,
            'total_size_mb' => $data['total_size_mb'] ?? 0,
            'total_size_gb' => $data['total_size_gb'] ?? 0,
            'max_size_bytes' => $data['max_size_bytes'] ?? null,
            'max_size_mb' => $data['max_size_mb'] ?? null,
            'max_size_gb' => $data['max_size_gb'] ?? null,
            'usage_percentage' => $data['usage_percentage'] ?? null,
            'table_count' => count($data['tables'] ?? []),
            'total_rows' => array_sum(array_column($data['tables'] ?? [], 'rows')),
            'largest_tables' => $largestTables,
        ]);
    }

    /**
     * Log individual table sizes.
     */
    protected function logTableSizes(DatabaseSizeLog $log, array $tables): void
    {
        $count = 0;

        foreach ($tables as $table) {
            $tableLog = DatabaseTableSizeLog::create([
                'database_size_log_id' => $log->id,
                'table_name' => $table['name'],
                'size_bytes' => $table['size_bytes'] ?? 0,
                'size_mb' => $table['size_mb'] ?? 0,
                'data_size_mb' => $table['data_size_mb'] ?? null,
                'index_size_mb' => $table['index_size_mb'] ?? null,
                'row_count' => $table['rows'] ?? 0,
            ]);

            // Calculate growth for this table
            $tableLog->calculateGrowth();
            $tableLog->save();

            $count++;
        }

        $this->info("Logged {$count} table size(s)");
    }

    /**
     * Check thresholds and send notifications if needed.
     */
    protected function checkThresholdsAndNotify(DatabaseSizeLog $log): void
    {
        if (!config('optimize-mcp.database_monitoring.notifications.enabled', false)) {
            return;
        }

        $recipients = config('optimize-mcp.database_monitoring.notifications.recipients', []);
        if (empty($recipients)) {
            return;
        }

        $notifyOncePerLevel = config('optimize-mcp.database_monitoring.notifications.notify_once_per_level', true);

        // Check critical threshold first
        if ($log->isApproachingCriticalThreshold() &&
            config('optimize-mcp.database_monitoring.notifications.notify_on_critical', true)) {

            // Check if we already sent a critical notification
            if ($notifyOncePerLevel && $this->alreadyNotified($log, 'critical')) {
                return;
            }

            $this->warn('Critical threshold reached! Sending notifications...');
            $this->sendNotification($log, 'critical', $recipients);
            return;
        }

        // Check warning threshold
        if ($log->isApproachingWarningThreshold() &&
            config('optimize-mcp.database_monitoring.notifications.notify_on_warning', true)) {

            // Check if we already sent a warning notification
            if ($notifyOncePerLevel && $this->alreadyNotified($log, 'warning')) {
                return;
            }

            $this->warn('Warning threshold reached! Sending notifications...');
            $this->sendNotification($log, 'warning', $recipients);
        }
    }

    /**
     * Check if we already sent a notification at this level.
     */
    protected function alreadyNotified(DatabaseSizeLog $log, string $level): bool
    {
        $threshold = $level === 'critical'
            ? config('optimize-mcp.database_monitoring.critical_threshold', 90)
            : config('optimize-mcp.database_monitoring.warning_threshold', 80);

        // Check if any recent log at or above this threshold exists
        return DatabaseSizeLog::forDatabase($log->database_name)
            ->where('usage_percentage', '>=', $threshold)
            ->where('created_at', '>', now()->subDay())
            ->exists();
    }

    /**
     * Send notification to recipients.
     */
    protected function sendNotification(DatabaseSizeLog $log, string $level, array $recipients): void
    {
        foreach ($recipients as $email) {
            Notification::route('mail', $email)
                ->notify(new DatabaseSizeWarning($log, $level));
        }

        $this->info("Notifications sent to: " . implode(', ', $recipients));
    }

    /**
     * Cleanup old logs based on retention policy.
     */
    protected function cleanupOldLogs(): void
    {
        $retentionDays = config('optimize-mcp.database_monitoring.retention_days', 90);
        $cutoffDate = now()->subDays($retentionDays);

        $deleted = DatabaseSizeLog::olderThan($cutoffDate)->delete();

        if ($deleted > 0) {
            $this->info("Cleaned up {$deleted} old log(s)");
        }
    }
}
