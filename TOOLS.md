# Available Tools

## Overview

This package provides MCP tools for Laravel optimization and testing. All tools are automatically discovered from the `src/Mcp/Tools` directory.

## Current Tools

### 1. Ping Tool

**Name:** `ping`

**Description:** A simple ping tool to test the MCP server connection. Returns a pong response with optional message and timestamp.

**Parameters:**
- `message` (string, optional, default: "pong") - Custom message to include in the response
- `include_timestamp` (boolean, optional, default: true) - Whether to include the current timestamp
- `include_app_info` (boolean, optional, default: false) - Whether to include application information

**Example Request:**
```json
{
  "name": "ping",
  "arguments": {
    "message": "Hello!",
    "include_timestamp": true,
    "include_app_info": true
  }
}
```

**Example Response:**
```json
{
  "status": "success",
  "message": "Hello!",
  "timestamp": "2025-11-02T02:04:45+00:00",
  "app": {
    "name": "Laravel",
    "environment": "local",
    "laravel_version": "12.36.1",
    "php_version": "8.4.14"
  }
}
```

---

### 2. EchoMessage Tool

**Name:** `echo-message`

**Description:** Echo back any message you send.

**Parameters:**
- `message` (string, required) - The message to echo back

**Example Request:**
```json
{
  "name": "echo-message",
  "arguments": {
    "message": "Testing the echo tool!"
  }
}
```

**Example Response:**
```text
Echo: Testing the echo tool!
```

---

### 3. PackageAdvisor Tool

**Name:** `package-advisor`

**Description:** Analyze Laravel project and recommend all useful packages to improve development workflow and production performance.

**Parameters:**
- `add_to_composer` (boolean, optional, default: false) - Whether to add recommended packages to composer.json (requires manual composer install after)
- `add_to_package_json` (boolean, optional, default: false) - Whether to add recommended npm/pnpm packages to package.json (requires manual npm/pnpm install after)

**Example Request:**
```json
{
  "name": "package-advisor",
  "arguments": {
    "add_to_composer": true,
    "add_to_package_json": true
  }
}
```

**Example Response:**
```json
{
  "summary": "📦 Laravel Package Recommendations...",
  "total_packages": 15,
  "recommendations": {
    "nunomaduro/larastan": "Static analysis tool for Laravel",
    "barryvdh/laravel-ide-helper": "Generate IDE helper files for better autocomplete",
    "laravel/scout": "Full-text search for Eloquent models"
  },
  "outdated_patterns": [
    {
      "package": "laravel/telescope",
      "issue": "Production Performance Risk",
      "recommendation": "Move to require-dev or use Laravel Pulse for production"
    }
  ],
  "installed_packages": ["php", "laravel/framework", ...],
  "added_to_composer": {
    "require": ["nunomaduro/larastan", "laravel/scout"],
    "require-dev": ["barryvdh/laravel-ide-helper"]
  },
  "composer_updated": true,
  "added_to_package_json": {
    "dependencies": ["@tailwindcss/forms", "alpinejs", "vue"],
    "devDependencies": ["autoprefixer", "prettier"]
  },
  "package_json_updated": true,
  "package_manager": "npm"
}
```

**Features:**
- Recommends ALL useful packages from the get-go - no complex stage detection
- Covers essential tools, testing, auth, database, monitoring, and code quality
- Recommends both PHP (Composer) and JavaScript (npm/pnpm) packages
- Identifies outdated or problematic packages (e.g., Telescope in production)
- Provides installation commands for missing packages
- Can automatically add packages to composer.json with `add_to_composer=true`
- Can automatically add packages to package.json with `add_to_package_json=true`
- Detects package manager (pnpm vs npm) and provides appropriate install commands
- Recommends using pnpm for local development with Laravel Forge compatibility notes

---

## Testing Tools

### Via Test Script

All tools have been tested and verified to work correctly:

```bash
cd /path/to/laravel
php artisan mcp:start optimize
```

Then use an MCP client to connect and test the tools.

### Via Claude Code

If using Claude Code with the MCP integration:

1. Ensure `.mcp.json` is configured:
```json
{
  "mcpServers": {
    "laravel-optimize-mcp": {
      "command": "php",
      "args": ["artisan", "mcp:start", "optimize"]
    }
  }
}
```

2. Restart Claude Code or reload the MCP connection

3. Test the tools:
   - "Use the ping tool with message 'hello' and include app info"
   - "Use the echo-message tool with message 'test'"

### Via MCP Inspector

For manual testing and debugging:

```bash
php artisan mcp:inspector optimize
```

This will open the MCP Inspector in your browser where you can:
- View all registered tools
- Test tools with custom parameters
- See request/response details
- Debug any issues

---

## Adding New Tools

To add a new tool:

1. Create a new tool class in `src/Mcp/Tools/`:
```php
<?php

namespace Skylence\OptimizeMcp\Mcp\Tools;

use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

final class MyNewTool extends Tool
{
    protected string $description = 'Description of your tool';

    public function schema(JsonSchema $schema): array
    {
        return [
            'param1' => $schema->string()
                ->description('Parameter description')
                ->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $param1 = $request->string('param1');

        // Your tool logic here

        return Response::json(['result' => 'success']);
    }
}
```

2. The tool will be automatically discovered and registered with the MCP server

3. Test your new tool using any of the methods above

---

## Tool Annotations

You can annotate tools to provide additional metadata to MCP clients:

- `#[IsReadOnly]` - Indicates the tool does not modify its environment
- `#[IsDestructive]` - Indicates the tool may perform destructive updates
- `#[IsIdempotent]` - Indicates repeated calls with same arguments have no additional effect
- `#[IsOpenWorld]` - Indicates the tool may interact with external entities

Example:
```php
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsReadOnly]
#[IsIdempotent]
final class MyTool extends Tool
{
    // ...
}
```

---

## Test Results

✅ **Ping Tool**: Successfully tested
- Returns JSON response with status, message, timestamp, and app info
- All parameters work as expected

✅ **EchoMessage Tool**: Successfully tested
- Correctly echoes back the provided message
- Simple and reliable for testing MCP connectivity

✅ **PackageAdvisor Tool**: Successfully tested
- Auto-detects project stage correctly (starter/mid/late)
- Provides relevant package recommendations
- Identifies Telescope optimization issues
- Returns comprehensive analysis with installation commands

All tools are production-ready and can be used with any MCP-compatible client.
