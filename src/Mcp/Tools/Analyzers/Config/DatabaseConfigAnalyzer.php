<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Mcp\Tools\Analyzers\Config;

final class DatabaseConfigAnalyzer extends AbstractConfigAnalyzer
{
    public function analyze(array &$issues, array &$recommendations, string $environment): void
    {
        $dbConnection = config('database.default');
        $dbConfig = config("database.connections.{$dbConnection}");

        // Check if database monitoring is enabled
        if (!config('optimize-mcp.database_monitoring.enabled', false)) {
            $recommendations[] = [
                'category' => 'monitoring',
                'config' => 'optimize-mcp.database_monitoring.enabled',
                'message' => 'Enable database monitoring to track size, growth, and predict capacity issues',
                'benefit' => 'Proactive alerts before database fills up, track table growth trends, and get actionable recommendations',
                'setup' => 'Add OPTIMIZE_MCP_DB_MONITORING=true to .env, run migrations, and schedule the monitoring command',
            ];
        } else {
            // If monitoring is enabled, check if notifications are configured
            $recipients = config('optimize-mcp.database_monitoring.notifications.recipients', []);
            if (empty($recipients) && config('optimize-mcp.database_monitoring.notifications.enabled', true)) {
                $recommendations[] = [
                    'category' => 'monitoring',
                    'config' => 'optimize-mcp.database_monitoring.notifications.recipients',
                    'message' => 'Configure email recipients for database monitoring alerts',
                    'benefit' => 'Get notified when database reaches warning/critical thresholds',
                    'setup' => 'Add OPTIMIZE_MCP_DB_NOTIFICATION_EMAILS=dev@example.com,ops@example.com to .env',
                ];
            }
        }

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
