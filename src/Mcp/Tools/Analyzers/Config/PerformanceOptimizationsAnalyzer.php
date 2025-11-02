<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Mcp\Tools\Analyzers\Config;

use Illuminate\Support\Facades\File;

final class PerformanceOptimizationsAnalyzer
{
    public function analyze(array &$optimizations, string $environment): void
    {
        // Check if config is cached
        $configCached = File::exists(base_path('bootstrap/cache/config.php'));
        $optimizations[] = [
            'type' => 'config_cache',
            'status' => $configCached ? 'enabled' : 'disabled',
            'command' => 'php artisan config:cache',
            'benefit' => 'Reduces config loading time by ~50-70%',
            'recommended_for' => $environment === 'production' ? 'critical' : 'optional',
        ];

        // Check if routes are cached
        $routesCached = File::exists(base_path('bootstrap/cache/routes-v7.php'));
        $optimizations[] = [
            'type' => 'route_cache',
            'status' => $routesCached ? 'enabled' : 'disabled',
            'command' => 'php artisan route:cache',
            'benefit' => 'Significantly speeds up route registration',
            'recommended_for' => $environment === 'production' ? 'critical' : 'optional',
        ];

        // Check if views are compiled
        $viewsCached = File::exists(storage_path('framework/views')) &&
                      count(File::files(storage_path('framework/views'))) > 0;
        $optimizations[] = [
            'type' => 'view_cache',
            'status' => $viewsCached ? 'enabled' : 'disabled',
            'command' => 'php artisan view:cache',
            'benefit' => 'Pre-compiles all Blade templates',
            'recommended_for' => $environment === 'production' ? 'recommended' : 'optional',
        ];

        // Check if events are cached (Laravel 11+)
        if (File::exists(base_path('bootstrap/cache/events.php'))) {
            $eventsCached = true;
        } else {
            $eventsCached = false;
        }
        $optimizations[] = [
            'type' => 'event_cache',
            'status' => $eventsCached ? 'enabled' : 'disabled',
            'command' => 'php artisan event:cache',
            'benefit' => 'Caches automatically discovered events',
            'recommended_for' => $environment === 'production' ? 'recommended' : 'optional',
        ];

        // Check opcache status (if available)
        if (function_exists('opcache_get_status')) {
            $opcacheStatus = @opcache_get_status();
            $optimizations[] = [
                'type' => 'opcache',
                'status' => $opcacheStatus !== false ? 'enabled' : 'disabled',
                'benefit' => 'Dramatically improves PHP performance',
                'recommended_for' => $environment === 'production' ? 'critical' : 'recommended',
            ];
        }

        // Check Laravel Boost installation
        $composerPath = base_path('composer.json');
        if (File::exists($composerPath)) {
            $composerData = json_decode(File::get($composerPath), true);
            $installedPackages = array_merge(
                array_keys($composerData['require'] ?? []),
                array_keys($composerData['require-dev'] ?? [])
            );

            $boostInstalled = in_array('laravel/boost', $installedPackages);

            $optimizations[] = [
                'type' => 'laravel_boost',
                'status' => $boostInstalled ? 'enabled' : 'disabled',
                'command' => $boostInstalled ? null : 'composer require laravel/boost',
                'benefit' => 'Preloads routes/config for 2-5x faster application boot time',
                'recommended_for' => 'critical',
                'note' => $boostInstalled ? 'Boost automatically optimizes on each request' : 'Laravel Boost is essential for production performance',
            ];
        }
    }
}
