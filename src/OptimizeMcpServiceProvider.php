<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp;

use Illuminate\Support\ServiceProvider;
use Skylence\OptimizeMcp\Console\InstallCommand;

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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/optimize-mcp.php' => config_path('optimize-mcp.php'),
            ], 'optimize-mcp-config');

            $this->commands([
                InstallCommand::class,
            ]);
        }
    }
}
