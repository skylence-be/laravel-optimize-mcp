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
        'ping' => true,
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
        'middleware' => [],
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
];
