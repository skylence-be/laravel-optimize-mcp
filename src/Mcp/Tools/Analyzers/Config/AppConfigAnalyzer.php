<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Mcp\Tools\Analyzers\Config;

final class AppConfigAnalyzer extends AbstractConfigAnalyzer
{
    public function analyze(array &$issues, array &$recommendations, string $environment): void
    {
        // Check debug mode
        if (config('app.debug') === true && $environment === 'production') {
            $issues[] = [
                'severity' => 'critical',
                'category' => 'security',
                'config' => 'app.debug',
                'current' => 'true',
                'message' => 'Debug mode is enabled in production! This exposes sensitive information.',
                'fix' => 'Set APP_DEBUG=false in .env',
            ];
        }

        // Check APP_ENV
        if (config('app.env') === 'local' && $environment === 'production') {
            $issues[] = [
                'severity' => 'critical',
                'category' => 'configuration',
                'config' => 'app.env',
                'current' => config('app.env'),
                'message' => 'APP_ENV is set to "local" but should be "production"',
                'fix' => 'Set APP_ENV=production in .env',
            ];
        }

        // Check APP_KEY
        if (empty(config('app.key'))) {
            $issues[] = [
                'severity' => 'critical',
                'category' => 'security',
                'config' => 'app.key',
                'current' => 'empty',
                'message' => 'Application key is not set',
                'fix' => 'Run: php artisan key:generate',
            ];
        }

        // Check timezone configuration
        if (config('app.timezone') === 'UTC' && $environment !== 'production') {
            $recommendations[] = [
                'category' => 'configuration',
                'config' => 'app.timezone',
                'message' => 'Consider setting your local timezone in APP_TIMEZONE',
                'benefit' => 'Better datetime handling for your region',
            ];
        }
    }
}
