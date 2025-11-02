<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Mcp\Tools\Analyzers\Config;

final class CacheConfigAnalyzer extends AbstractConfigAnalyzer
{
    public function analyze(array &$issues, array &$recommendations, string $environment): void
    {
        $cacheDriver = config('cache.default');

        // Check cache driver for production
        if ($environment === 'production' && in_array($cacheDriver, ['array', 'file'])) {
            $issues[] = [
                'severity' => 'warning',
                'category' => 'performance',
                'config' => 'cache.default',
                'current' => $cacheDriver,
                'message' => "Cache driver '{$cacheDriver}' is not optimal for production",
                'fix' => 'Use redis driver (works with Valkey or Redis) or memcached. Set CACHE_DRIVER=redis in .env',
            ];
        }

        // Recommend redis for better performance
        if ($cacheDriver === 'file' && $environment === 'production') {
            $recommendations[] = [
                'category' => 'performance',
                'config' => 'cache.default',
                'message' => 'Switch from file to redis cache driver (supports Valkey/Redis)',
                'benefit' => 'Significant performance improvement, especially under load',
            ];
        }
    }
}
