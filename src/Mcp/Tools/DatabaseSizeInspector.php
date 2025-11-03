<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Mcp\Tools;

use Illuminate\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
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
                'message' => 'DatabaseSizeInspector is only available for HTTP MCP (remote servers). For local development, this information is not typically needed.',
            ]);
        }

        $params = $request->all();
        $format = $params['format'] ?? 'summary';

        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        try {
            $data = match ($driver) {
                'mysql', 'mariadb' => $this->getMySqlDatabaseSize($connection),
                'pgsql' => $this->getPostgresDatabaseSize($connection),
                'sqlite' => $this->getSqliteDatabaseSize($connection),
                default => $this->getGenericDatabaseSize($connection),
            };

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

    /**
     * Build human-readable summary.
     */
    private function buildSummary(array $data): string
    {
        $lines = [];
        $lines[] = '📊 Database Size Inspection';
        $lines[] = '';
        $lines[] = "Database: {$data['database']}";
        $lines[] = "Driver: {$data['driver']}";
        $lines[] = '';

        // Total size
        $lines[] = '💾 Total Size:';
        $lines[] = "  • {$data['total_size_mb']} MB ({$data['total_size_gb']} GB)";
        $lines[] = "  • {$data['total_size_bytes']} bytes";

        // Max size and usage (if available)
        if (isset($data['max_size_gb']) && $data['max_size_gb'] !== null) {
            $lines[] = '';
            $lines[] = '📈 Disk Usage:';
            $lines[] = "  • Max Available: {$data['max_size_gb']} GB ({$data['max_size_mb']} MB)";
            $lines[] = "  • Usage: {$data['usage_percentage']}%";

            // Add visual indicator
            $usage = $data['usage_percentage'];
            if ($usage >= 90) {
                $lines[] = '  • Status: 🚨 CRITICAL - Immediate action required';
            } elseif ($usage >= 80) {
                $lines[] = '  • Status: ⚠️ WARNING - Action recommended';
            } elseif ($usage >= 70) {
                $lines[] = '  • Status: 🟡 NOTICE - Monitor closely';
            } else {
                $lines[] = '  • Status: ✅ HEALTHY';
            }
        }

        $lines[] = '';

        // Tables
        $tables = $data['tables'] ?? [];
        if (! empty($tables)) {
            $lines[] = '📋 Tables ('.count($tables).'):';
            $lines[] = '';

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
                $lines[] = '';
            }

            if (count($tables) > 10) {
                $remaining = count($tables) - 10;
                $lines[] = "  ... and {$remaining} more tables";
                $lines[] = '';
            }
        }

        // Insights
        if (! empty($tables)) {
            $totalRows = array_sum(array_column($tables, 'rows'));
            $largestTable = $tables[0] ?? null;

            $lines[] = '🔍 Insights:';
            if ($totalRows > 0) {
                $lines[] = '  • Total rows across all tables: '.number_format($totalRows);
            }
            if ($largestTable) {
                $lines[] = "  • Largest table: {$largestTable['name']} ({$largestTable['size_mb']} MB)";
            }
        }

        // Growth insights (if monitoring is enabled)
        if (config('optimize-mcp.database_monitoring.enabled', false)) {
            $growthInsights = $this->getGrowthInsights($data['database'] ?? null);
            if (! empty($growthInsights)) {
                $lines[] = '';
                $lines[] = '📈 Growth Trends (from monitoring):';
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
        if (! $database) {
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
            $insights[] = '  🚀 Fastest Growing Tables:';
            foreach ($fastestGrowing as $table) {
                $growthSign = $table->growth_percentage >= 0 ? '+' : '';
                $insights[] = "     • {$table->table_name}: {$growthSign}{$table->growth_percentage}% ({$growthSign}{$table->growth_mb} MB)";
                if ($table->row_growth && $table->row_growth > 0) {
                    $rowPercentage = $table->row_growth_percentage !== null
                        ? "{$growthSign}{$table->row_growth_percentage}%"
                        : 'from 0 rows';
                    $insights[] = '       Rows: '.$growthSign.number_format($table->row_growth)." ({$rowPercentage})";
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
            $insights[] = '';
            $insights[] = '  📊 Largest Absolute Growth:';
            foreach ($largestGrowth as $table) {
                $growthSign = $table->growth_mb >= 0 ? '+' : '';
                $insights[] = "     • {$table->table_name}: {$growthSign}{$table->growth_mb} MB";
            }
        }

        // Add recommendations based on growth
        if ($fastestGrowing->isNotEmpty()) {
            $insights[] = '';
            $insights[] = '  💡 Recommendations:';

            $topGrowing = $fastestGrowing->first();
            if ($topGrowing->growth_percentage > 50) {
                $insights[] = "     • Consider archiving old data in {$topGrowing->table_name}";
            }

            // Check if it's a known table type
            $tableName = $topGrowing->table_name;
            if (str_contains($tableName, 'telescope')) {
                $insights[] = '     • Run: php artisan telescope:prune to clean old entries';
            } elseif (str_contains($tableName, 'log')) {
                $insights[] = "     • Implement log rotation or archival for {$tableName}";
            } elseif (str_contains($tableName, 'cache') || str_contains($tableName, 'session')) {
                $insights[] = '     • Review cache/session retention policies';
            }
        }

        return $insights;
    }
}
