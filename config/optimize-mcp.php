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
];
