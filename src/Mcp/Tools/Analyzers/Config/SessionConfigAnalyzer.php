<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Mcp\Tools\Analyzers\Config;

final class SessionConfigAnalyzer extends AbstractConfigAnalyzer
{
    public function analyze(array &$issues, array &$recommendations, string $environment): void
    {
        $sessionDriver = config('session.driver');

        // Check session driver for production
        if ($environment === 'production' && $sessionDriver === 'file') {
            $recommendations[] = [
                'category' => 'performance',
                'config' => 'session.driver',
                'message' => "Session driver is 'file', consider database or redis (Valkey/Redis) for multi-server setups",
                'benefit' => 'Better scalability and session persistence across servers. Redis driver works with both Valkey and Redis',
            ];
        }

        // Check secure cookies in production
        if ($environment === 'production' && config('session.secure') === false) {
            $issues[] = [
                'severity' => 'warning',
                'category' => 'security',
                'config' => 'session.secure',
                'current' => 'false',
                'message' => 'Session cookies should be secure in production (HTTPS only)',
                'fix' => 'Set SESSION_SECURE_COOKIE=true in .env',
            ];
        }

        // Check same_site cookie setting
        if (config('session.same_site') === null) {
            $recommendations[] = [
                'category' => 'security',
                'config' => 'session.same_site',
                'message' => 'Set same_site cookie attribute for CSRF protection',
                'benefit' => 'Better protection against CSRF attacks',
            ];
        }
    }
}
