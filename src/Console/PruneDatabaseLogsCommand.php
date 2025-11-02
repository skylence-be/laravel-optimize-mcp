<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Console;

use Illuminate\Console\Command;
use Skylence\OptimizeMcp\Models\DatabaseSizeLog;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('optimize-mcp:prune-database-logs', 'Prune old database size logs based on retention policy')]
class PruneDatabaseLogsCommand extends Command
{
    /**
     * The console command signature.
     */
    protected $signature = 'optimize-mcp:prune-database-logs
                            {--days= : Number of days to keep (overrides config)}
                            {--force : Force deletion without confirmation}';

    /**
     * The console command description.
     */
    protected $description = 'Prune old database size logs based on retention policy';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!config('optimize-mcp.database_monitoring.enabled', false)) {
            $this->info('Database monitoring is disabled.');
            return Command::SUCCESS;
        }

        $retentionDays = $this->option('days')
            ?? config('optimize-mcp.database_monitoring.retention_days', 90);

        $cutoffDate = now()->subDays((int) $retentionDays);

        // Get count of logs to be deleted
        $count = DatabaseSizeLog::olderThan($cutoffDate)->count();

        if ($count === 0) {
            $this->info('No old logs to prune.');
            return Command::SUCCESS;
        }

        // Show what will be deleted
        $this->info("Found {$count} log(s) older than {$retentionDays} days (before {$cutoffDate->format('Y-m-d H:i:s')})");

        // Ask for confirmation unless forced
        if (!$this->option('force') && !$this->confirm('Do you want to delete these logs?')) {
            $this->info('Pruning cancelled.');
            return Command::SUCCESS;
        }

        // Delete old logs
        $deleted = DatabaseSizeLog::olderThan($cutoffDate)->delete();

        $this->info("Successfully pruned {$deleted} log(s).");

        return Command::SUCCESS;
    }
}
