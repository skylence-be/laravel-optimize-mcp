<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Skylence\OptimizeMcp\Console\InstallCommand;
use Skylence\OptimizeMcp\Console\McpCommand;
use Skylence\OptimizeMcp\Console\MonitorDatabaseSizeCommand;
use Skylence\OptimizeMcp\Console\PruneDatabaseLogsCommand;

class OptimizeMcpServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/optimize-mcp.php',
            'optimize-mcp'
        );

        // Register Logger singleton
        $this->app->singleton(\Skylence\OptimizeMcp\Support\Logger::class, function ($app) {
            return new \Skylence\OptimizeMcp\Support\Logger(
                enabled: config('optimize-mcp.logging.enabled', false),
                channel: config('optimize-mcp.logging.channel', 'stack')
            );
        });

        // Register HTTP Server wrapper singleton
        $this->app->singleton(\Skylence\OptimizeMcp\Support\OptimizeServerHttp::class, function ($app) {
            return new \Skylence\OptimizeMcp\Support\OptimizeServerHttp();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Load MCP routes
        $this->loadRoutesFrom(__DIR__.'/../routes/ai.php');

        // Load HTTP MCP routes with prefix
        Route::prefix(config('optimize-mcp.http.prefix', 'api/mcp'))
            ->middleware(config('optimize-mcp.http.middleware', []))
            ->group(__DIR__.'/../routes/http.php');

        // Register commands (needed for both console and HTTP contexts)
        // HTTP MCP tools use Artisan::call() which requires commands to be registered
        $this->commands([
            InstallCommand::class,
            McpCommand::class,
            MonitorDatabaseSizeCommand::class,
            PruneDatabaseLogsCommand::class,
        ]);

        if ($this->app->runningInConsole()) {
            // Load package migrations (only if monitoring is enabled)
            if (config('optimize-mcp.database_monitoring.enabled', false)) {
                $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
            }

            // Publish config
            $this->publishes([
                __DIR__.'/../config/optimize-mcp.php' => config_path('optimize-mcp.php'),
            ], 'optimize-mcp-config');
        }
    }
}
