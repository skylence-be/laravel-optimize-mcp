<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Mcp\Tools\Analyzers\Config;

final class QueueConfigAnalyzer extends AbstractConfigAnalyzer
{
    public function analyze(array &$issues, array &$recommendations, string $environment): void
    {
        $queueDriver = config('queue.default');

        // Check queue driver for production
        if ($environment === 'production' && $queueDriver === 'sync') {
            $issues[] = [
                'severity' => 'warning',
                'category' => 'performance',
                'config' => 'queue.default',
                'current' => 'sync',
                'message' => 'Queue driver is "sync" which processes jobs synchronously',
                'fix' => 'Use database, redis (Valkey/Redis), or SQS for async job processing. Set QUEUE_CONNECTION=redis',
            ];
        }

        // Recommend redis or SQS for better performance
        if ($queueDriver === 'database' && $environment === 'production') {
            $recommendations[] = [
                'category' => 'performance',
                'config' => 'queue.default',
                'message' => 'Consider upgrading from database to redis queue driver (supports Valkey/Redis)',
                'benefit' => 'Better performance for high-volume job processing. Redis driver works with both Valkey and Redis',
            ];
        }
    }
}
