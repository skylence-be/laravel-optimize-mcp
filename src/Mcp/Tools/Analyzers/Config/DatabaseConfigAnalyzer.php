<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Mcp\Tools\Analyzers\Config;

final class DatabaseConfigAnalyzer extends AbstractConfigAnalyzer
{
    public function analyze(array &$issues, array &$recommendations, string $environment): void
    {
        $dbConnection = config('database.default');
        $dbConfig = config("database.connections.{$dbConnection}");

        // Check for query log in production
        if ($environment === 'production' && config('database.connections.mysql.options.PDO::MYSQL_ATTR_USE_BUFFERED_QUERY', true) === false) {
            $recommendations[] = [
                'category' => 'performance',
                'config' => 'database.connections.mysql',
                'message' => 'Consider using buffered queries for better memory management',
                'benefit' => 'Reduced memory usage for large result sets',
            ];
        }

        // Check for strict mode
        if ($dbConnection === 'mysql' && !in_array('STRICT_TRANS_TABLES', $dbConfig['modes'] ?? [])) {
            $recommendations[] = [
                'category' => 'data-integrity',
                'config' => 'database.connections.mysql.strict',
                'message' => 'Enable strict mode for better data integrity',
                'benefit' => 'Prevents invalid data insertion and ensures data consistency',
            ];
        }
    }
}
