<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('optimize-mcp:database-size', 'Get database size information (total size and individual table sizes)')]
class DatabaseSizeCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        try {
            $data = match ($driver) {
                'mysql', 'mariadb' => $this->getMySqlDatabaseSize($connection),
                'pgsql' => $this->getPostgresDatabaseSize($connection),
                'sqlite' => $this->getSqliteDatabaseSize($connection),
                default => $this->getGenericDatabaseSize($connection),
            };

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
     * Get database size for MySQL/MariaDB.
     */
    protected function getMySqlDatabaseSize(string $connection): array
    {
        $database = config("database.connections.{$connection}.database");

        // Get total database size
        $databaseSize = DB::select("
            SELECT
                SUM(data_length + index_length) as size_bytes,
                SUM(data_length + index_length) / 1024 / 1024 as size_mb
            FROM information_schema.TABLES
            WHERE table_schema = ?
        ", [$database])[0] ?? null;

        // Get individual table sizes
        $tables = DB::select("
            SELECT
                table_name,
                (data_length + index_length) as size_bytes,
                (data_length + index_length) / 1024 / 1024 as size_mb,
                table_rows,
                data_length / 1024 / 1024 as data_size_mb,
                index_length / 1024 / 1024 as index_size_mb
            FROM information_schema.TABLES
            WHERE table_schema = ?
            ORDER BY (data_length + index_length) DESC
        ", [$database]);

        // Try to get max database size (disk space available)
        $maxSize = $this->getMySqlMaxSize();

        return [
            'driver' => 'mysql',
            'database' => $database,
            'total_size_bytes' => (int) ($databaseSize->size_bytes ?? 0),
            'total_size_mb' => round($databaseSize->size_mb ?? 0, 2),
            'total_size_gb' => round(($databaseSize->size_mb ?? 0) / 1024, 2),
            'max_size_bytes' => $maxSize['max_size_bytes'] ?? null,
            'max_size_mb' => $maxSize['max_size_mb'] ?? null,
            'max_size_gb' => $maxSize['max_size_gb'] ?? null,
            'usage_percentage' => $maxSize['usage_percentage'] ?? null,
            'tables' => array_map(function ($table) {
                return [
                    'name' => $table->table_name,
                    'size_bytes' => (int) $table->size_bytes,
                    'size_mb' => round($table->size_mb, 2),
                    'rows' => (int) $table->table_rows,
                    'data_size_mb' => round($table->data_size_mb, 2),
                    'index_size_mb' => round($table->index_size_mb, 2),
                ];
            }, $tables),
        ];
    }

    /**
     * Get MySQL max size from disk space.
     */
    protected function getMySqlMaxSize(): array
    {
        try {
            // Get data directory
            $dataDir = DB::select("SELECT @@datadir as datadir")[0]->datadir ?? null;

            if ($dataDir && is_dir($dataDir)) {
                $diskFree = disk_free_space($dataDir);
                $diskTotal = disk_total_space($dataDir);

                if ($diskFree !== false && $diskTotal !== false) {
                    $maxSizeBytes = (int) $diskTotal;
                    $usedBytes = $diskTotal - $diskFree;
                    $usagePercentage = round(($usedBytes / $diskTotal) * 100, 2);

                    return [
                        'max_size_bytes' => $maxSizeBytes,
                        'max_size_mb' => round($maxSizeBytes / 1024 / 1024, 2),
                        'max_size_gb' => round($maxSizeBytes / 1024 / 1024 / 1024, 2),
                        'usage_percentage' => $usagePercentage,
                    ];
                }
            }
        } catch (\Exception $e) {
            // Silently fail and return empty array
        }

        return [];
    }

    /**
     * Get database size for PostgreSQL.
     */
    protected function getPostgresDatabaseSize(string $connection): array
    {
        $database = config("database.connections.{$connection}.database");

        // Get total database size
        $databaseSize = DB::select("
            SELECT pg_database_size(?) as size_bytes
        ", [$database])[0] ?? null;

        $sizeBytes = (int) ($databaseSize->size_bytes ?? 0);
        $sizeMb = $sizeBytes / 1024 / 1024;

        // Get individual table sizes
        $tables = DB::select("
            SELECT
                schemaname || '.' || relname as table_name,
                pg_total_relation_size(schemaname||'.'||relname) AS size_bytes,
                pg_total_relation_size(schemaname||'.'||relname) / 1024 / 1024 AS size_mb,
                n_live_tup as table_rows
            FROM pg_stat_user_tables
            ORDER BY pg_total_relation_size(schemaname||'.'||relname) DESC
        ");

        // Try to get max database size
        $maxSize = $this->getPostgresMaxSize();

        return [
            'driver' => 'pgsql',
            'database' => $database,
            'total_size_bytes' => $sizeBytes,
            'total_size_mb' => round($sizeMb, 2),
            'total_size_gb' => round($sizeMb / 1024, 2),
            'max_size_bytes' => $maxSize['max_size_bytes'] ?? null,
            'max_size_mb' => $maxSize['max_size_mb'] ?? null,
            'max_size_gb' => $maxSize['max_size_gb'] ?? null,
            'usage_percentage' => $maxSize['usage_percentage'] ?? null,
            'tables' => array_map(function ($table) {
                return [
                    'name' => $table->table_name,
                    'size_bytes' => (int) $table->size_bytes,
                    'size_mb' => round($table->size_mb, 2),
                    'rows' => (int) $table->table_rows,
                ];
            }, $tables),
        ];
    }

    /**
     * Get PostgreSQL max size from disk space.
     */
    protected function getPostgresMaxSize(): array
    {
        try {
            // Get data directory
            $dataDir = DB::select("SHOW data_directory")[0]->data_directory ?? null;

            if ($dataDir && is_dir($dataDir)) {
                $diskFree = disk_free_space($dataDir);
                $diskTotal = disk_total_space($dataDir);

                if ($diskFree !== false && $diskTotal !== false) {
                    $maxSizeBytes = (int) $diskTotal;
                    $usedBytes = $diskTotal - $diskFree;
                    $usagePercentage = round(($usedBytes / $diskTotal) * 100, 2);

                    return [
                        'max_size_bytes' => $maxSizeBytes,
                        'max_size_mb' => round($maxSizeBytes / 1024 / 1024, 2),
                        'max_size_gb' => round($maxSizeBytes / 1024 / 1024 / 1024, 2),
                        'usage_percentage' => $usagePercentage,
                    ];
                }
            }
        } catch (\Exception $e) {
            // Silently fail and return empty array
        }

        return [];
    }

    /**
     * Get database size for SQLite.
     */
    protected function getSqliteDatabaseSize(string $connection): array
    {
        $databasePath = config("database.connections.{$connection}.database");

        $sizeBytes = 0;
        if (file_exists($databasePath)) {
            $sizeBytes = filesize($databasePath);
        }

        $sizeMb = $sizeBytes / 1024 / 1024;

        // Get individual table sizes (SQLite doesn't provide easy per-table size info)
        $tables = DB::select("
            SELECT name as table_name
            FROM sqlite_master
            WHERE type='table' AND name NOT LIKE 'sqlite_%'
            ORDER BY name
        ");

        // Get max size from disk space
        $maxSize = $this->getSqliteMaxSize($databasePath);

        return [
            'driver' => 'sqlite',
            'database' => basename($databasePath),
            'database_path' => $databasePath,
            'total_size_bytes' => $sizeBytes,
            'total_size_mb' => round($sizeMb, 2),
            'total_size_gb' => round($sizeMb / 1024, 2),
            'max_size_bytes' => $maxSize['max_size_bytes'] ?? null,
            'max_size_mb' => $maxSize['max_size_mb'] ?? null,
            'max_size_gb' => $maxSize['max_size_gb'] ?? null,
            'usage_percentage' => $maxSize['usage_percentage'] ?? null,
            'tables' => array_map(function ($table) {
                $count = DB::table($table->table_name)->count();
                return [
                    'name' => $table->table_name,
                    'rows' => $count,
                ];
            }, $tables),
        ];
    }

    /**
     * Get SQLite max size from disk space.
     */
    protected function getSqliteMaxSize(string $databasePath): array
    {
        try {
            if (file_exists($databasePath)) {
                $directory = dirname($databasePath);
                $diskFree = disk_free_space($directory);
                $diskTotal = disk_total_space($directory);

                if ($diskFree !== false && $diskTotal !== false) {
                    $maxSizeBytes = (int) $diskTotal;
                    $usedBytes = $diskTotal - $diskFree;
                    $usagePercentage = round(($usedBytes / $diskTotal) * 100, 2);

                    return [
                        'max_size_bytes' => $maxSizeBytes,
                        'max_size_mb' => round($maxSizeBytes / 1024 / 1024, 2),
                        'max_size_gb' => round($maxSizeBytes / 1024 / 1024 / 1024, 2),
                        'usage_percentage' => $usagePercentage,
                    ];
                }
            }
        } catch (\Exception $e) {
            // Silently fail and return empty array
        }

        return [];
    }

    /**
     * Get generic database size (fallback for other drivers).
     */
    protected function getGenericDatabaseSize(string $connection): array
    {
        $driver = config("database.connections.{$connection}.driver");
        $database = config("database.connections.{$connection}.database");

        // Try to get tables
        $tables = [];
        try {
            $tableNames = DB::select("SHOW TABLES");
            foreach ($tableNames as $tableName) {
                $name = array_values((array) $tableName)[0];
                $count = DB::table($name)->count();
                $tables[] = [
                    'name' => $name,
                    'rows' => $count,
                ];
            }
        } catch (\Exception $e) {
            // Ignore errors for unsupported drivers
        }

        return [
            'driver' => $driver,
            'database' => $database,
            'message' => 'Detailed size information not available for this database driver',
            'tables' => $tables,
        ];
    }
}
