# Laravel Optimize MCP

Laravel Optimize MCP provides optimization tools and utilities for AI-assisted development through the Model Context Protocol (MCP).

## Installation

You can install the package via Composer:

```bash
composer require laravel/optimize-mcp
```

Run the installation command:

```bash
php artisan optimize-mcp:install
```

This will publish the configuration file to `config/optimize-mcp.php`.

## Configuration

The package automatically registers its MCP server routes. No additional configuration is required!

## Available Tools

### Ping

A simple ping tool to test the MCP server connection.

**Parameters:**
- `message` (optional, default: "pong"): Custom message to include in the response
- `include_timestamp` (optional, default: true): Whether to include the current timestamp
- `include_app_info` (optional, default: false): Whether to include application information

**Example Response:**
```json
{
  "status": "success",
  "message": "pong",
  "timestamp": "2025-11-02T02:39:00+00:00",
  "app": {
    "name": "Laravel",
    "environment": "local",
    "laravel_version": "12.0.0",
    "php_version": "8.3.0"
  }
}
```

### EchoMessage

Echo back any message you send - useful for testing MCP connectivity.

**Parameters:**
- `message` (required): The message to echo back

**Example Response:**
```
Echo: Your message here
```

### PackageAdvisor

Analyze your Laravel project and get comprehensive package recommendations to improve your development workflow.

**Parameters:**
- `add_to_composer` (optional, default: false): Automatically add recommended packages to composer.json
- `add_to_package_json` (optional, default: false): Automatically add recommended npm/pnpm packages to package.json

**Example Response:**
```json
{
  "total_packages": 15,
  "recommendations": {
    "nunomaduro/larastan": "Static analysis tool for Laravel",
    "barryvdh/laravel-ide-helper": "Generate IDE helper files",
    "laravel/scout": "Full-text search for Eloquent models"
  },
  "added_to_composer": {
    "require": ["nunomaduro/larastan", "laravel/scout"],
    "require-dev": ["barryvdh/laravel-ide-helper"]
  },
  "added_to_package_json": {
    "dependencies": ["@tailwindcss/forms", "alpinejs", "vue"],
    "devDependencies": ["autoprefixer", "prettier", "typescript"]
  },
  "package_manager": "npm"
}
```

**Features:**
- Recommends all useful packages from the get-go (no stage detection needed)
- Covers essential, testing, auth, database, monitoring, and code quality packages
- Recommends both PHP (Composer) and JavaScript (npm/pnpm) packages
- Can automatically modify composer.json and package.json files
- Detects whether you're using npm or pnpm
- Provides helpful tips about using pnpm for local development
- Includes warnings about Laravel Forge compatibility with pnpm

For more details on all available tools, see [TOOLS.md](TOOLS.md).

## Testing the Server

You can test the MCP server using the MCP Inspector:

```bash
# For local servers
php artisan mcp:inspector optimize

# For web servers
php artisan mcp:inspector mcp/optimize
```

## Usage with MCP Clients

Once installed and configured, you can connect to the server from any MCP-compatible client (like Claude Desktop, Cursor, VS Code, etc.).

### Local Server Configuration

For local servers, add the following to your MCP client configuration:

```json
{
  "mcpServers": {
    "laravel-optimize": {
      "command": "php",
      "args": ["artisan", "mcp:start", "optimize"]
    }
  }
}
```

### Web Server Configuration

For web servers, configure your MCP client to connect to the HTTP endpoint:

```
http://your-app.test/mcp/optimize
```

## Creating Custom Tools

You can create additional tools by extending the `Laravel\Mcp\Server\Tool` class:

```php
<?php

namespace App\Mcp\Tools;

use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class MyCustomTool extends Tool
{
    protected string $description = 'Description of your tool';

    public function handle(Request $request): Response
    {
        // Your tool logic here
        return Response::json(['result' => 'success']);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'param1' => $schema->string()
                ->description('Parameter description')
                ->required(),
        ];
    }
}
```

Then register your custom tool in the `config/optimize-mcp.php` configuration file or directly in your server class.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
