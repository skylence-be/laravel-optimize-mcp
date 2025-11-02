<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MCP Server Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the Laravel Optimize MCP server.
    |
    */

    'server' => [
        'name' => 'Laravel Optimize',
        'version' => '1.0.0',
    ],

    /*
    |--------------------------------------------------------------------------
    | Enabled Tools
    |--------------------------------------------------------------------------
    |
    | The tools that should be registered with the MCP server.
    |
    */

    'tools' => [
        'configuration-analyzer' => true,
        'database-size-inspector' => true,
        'project-structure-analyzer' => false, // Disabled for HTTP
        'package-advisor' => false, // Disabled for HTTP
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP MCP API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the HTTP MCP API endpoints.
    |
    */

    'http' => [
        'enabled' => true,
        'prefix' => 'optimize-mcp',
        'middleware' => [
            \Skylence\OptimizeMcp\Http\Middleware\AuthenticateMcp::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for MCP server logging.
    |
    */

    'logging' => [
        'enabled' => false,
        'channel' => 'stack',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication & Authorization
    |--------------------------------------------------------------------------
    |
    | Configure access control for the HTTP MCP endpoints. When enabled,
    | requests must include a valid bearer token or X-MCP-Token header.
    |
    | Generate a secure token: php artisan tinker --execute="echo bin2hex(random_bytes(32))"
    |
    */

    'auth' => [
        'enabled' => env('OPTIMIZE_MCP_AUTH_ENABLED', true),
        'token' => env('OPTIMIZE_MCP_API_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Installation Preferences
    |--------------------------------------------------------------------------
    |
    | Store user preferences from the installation process. These are used
    | to remember which code editors you selected during installation.
    |
    */

    'installation' => [
        'editors' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | Configure automatic database size monitoring, growth tracking, and
    | alerting. When enabled, the system will periodically check database
    | size, calculate growth rates, predict when the database will be full,
    | and send email notifications when thresholds are exceeded.
    |
    | To enable: Set 'enabled' to true and run: php artisan migrate
    | Schedule: Add the monitoring command to your Kernel.php schedule
    |
    */

    'database_monitoring' => [
        // Enable or disable database monitoring feature
        'enabled' => env('OPTIMIZE_MCP_DB_MONITORING', false),

        // How often to check database size (in schedule)
        // Options: 'hourly', 'daily', 'twiceDaily', 'weekly'
        'frequency' => env('OPTIMIZE_MCP_DB_MONITORING_FREQUENCY', 'daily'),

        // Threshold percentages for alerting
        'warning_threshold' => env('OPTIMIZE_MCP_DB_WARNING_THRESHOLD', 80),
        'critical_threshold' => env('OPTIMIZE_MCP_DB_CRITICAL_THRESHOLD', 90),

        // How long to keep historical logs (in days)
        'retention_days' => env('OPTIMIZE_MCP_DB_RETENTION_DAYS', 90),

        // Email notification settings
        'notifications' => [
            // Enable email notifications
            'enabled' => env('OPTIMIZE_MCP_DB_NOTIFICATIONS', true),

            // Email addresses to notify (can be comma-separated in .env)
            'recipients' => array_filter(
                explode(',', env('OPTIMIZE_MCP_DB_NOTIFICATION_EMAILS', ''))
            ),

            // Send notifications for warning threshold
            'notify_on_warning' => true,

            // Send notifications for critical threshold
            'notify_on_critical' => true,

            // Only send notification once per threshold level
            // (won't spam if already notified at this level)
            'notify_once_per_level' => true,
        ],

        // Prediction settings
        'prediction' => [
            // Minimum number of logs required for prediction
            'min_data_points' => 2,

            // Number of days to look back for growth calculation
            'lookback_days' => 30,

            // Send prediction notifications when database will be full in X days
            'notify_days_before_full' => [30, 14, 7, 3, 1],
        ],
    ],
];
