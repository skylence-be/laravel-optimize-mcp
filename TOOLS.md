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

### 3. ProjectStructureAnalyzer Tool

**Name:** `project-structure-analyzer`

**Description:** Analyze project structure including composer scripts, GitHub workflows, testing setup, Git hooks, and deployment process.

**Parameters:**
- `include_actions` (boolean, optional, default: false) - Include actionable recommendations with stub file contents and installation commands

**Example Request:**
```json
{
  "name": "project-structure-analyzer",
  "arguments": {
    "include_actions": true
  }
}
```

**Example Response:**
```json
{
  "summary": "📋 Project Structure Analysis...",
  "severity_counts": {
    "critical": 0,
    "warning": 1
  },
  "issues": [
    {
      "severity": "warning",
      "file": ".github/workflows",
      "message": "No CI/CD workflows configured"
    }
  ],
  "good_practices": [
    {
      "category": "composer",
      "message": "Development script configured for concurrent processes",
      "details": "Good practice: single command to run server, queue, and vite"
    }
  ],
  "recommendations": [
    {
      "category": "testing",
      "message": "Add pestphp/pest-plugin-browser for E2E testing",
      "benefit": "Enables browser testing with Playwright"
    },
    {
      "category": "git-hooks",
      "message": "Install GrumPHP or CaptainHook for pre-commit checks",
      "benefit": "Prevents pushing broken code to CI/CD"
    }
  ],
  "actions": [
    {
      "category": "ci-cd",
      "message": "No GitHub Actions workflows found",
      "type": "copy_directory",
      "stub_file": ".github",
      "stub_destination": ".github",
      "files": {
        ".github/workflows/tests.yml": "... 8494 bytes - complete CI/CD workflow ...",
        ".github/workflows/dependabot-auto-merge.yml": "... 2098 bytes - auto-merge workflow ...",
        ".github/actions/setup/action.yml": "... 2736 bytes - custom setup action ...",
        ".github/dependabot.yml": "... 1745 bytes - dependabot config ..."
      }
    },
    {
      "category": "git-hooks",
      "type": "install_and_copy",
      "command": "composer require --dev captainhook/captainhook",
      "stub_file": "captainhook.json",
      "stub_destination": "captainhook.json",
      "stub_contents": "... full captainhook.json ..."
    }
  ]
}
```

**Features:**
- **Composer Scripts**: Analyzes composer.json for test, lint, quality check scripts
- **GitHub Workflows**: Checks for CI/CD automation (.github/workflows)
- **Frontend Setup**: Validates package.json scripts and dev dependencies
- **Testing**: Checks for Pest, PHPUnit, browser testing setup
- **Git Hooks**: Detects GrumPHP, CaptainHook, or custom git hooks
- **Deployment**: Checks for Deployer, Laravel Forge, or other deployment tools
- **Good Practices**: Identifies well-configured development workflows
- **Recommendations**: Suggests missing tools and process improvements
- **Actionable Response** (`include_actions=true`): Returns complete stub file contents and installation commands:
  - Ready-to-use configuration files (GitHub workflows, Git hooks, Deployer, etc.)
  - Composer/npm commands to install recommended packages
  - File destinations for each stub
  - Action types: `install`, `copy_stub`, `install_and_copy`, `merge_json`

---

### 4. ConfigurationAnalyzer Tool

**Name:** `configuration-analyzer`

**Description:** Analyze Laravel configuration for performance, security, and optimization opportunities.

**Parameters:**
- `environment` (string, optional) - Target environment: production, staging, or local (will use APP_ENV if not provided)

**Example Request:**
```json
{
  "name": "configuration-analyzer",
  "arguments": {
    "environment": "production"
  }
}
```

**Example Response:**
```json
{
  "summary": "⚙️ Laravel Configuration Analysis...",
  "environment": "production",
  "severity_counts": {
    "critical": 1,
    "warning": 2,
    "info": 0
  },
  "issues": [
    {
      "severity": "critical",
      "category": "security",
      "config": "app.debug",
      "message": "Debug mode is enabled in production!",
      "fix": "Set APP_DEBUG=false in .env"
    }
  ],
  "recommendations": [
    {
      "config": "cache.default",
      "message": "Consider using Redis for caching",
      "benefit": "Better performance and scalability"
    }
  ],
  "optimizations": [
    {
      "type": "opcache",
      "status": "disabled",
      "recommended_for": "critical",
      "benefit": "Dramatically improves PHP performance"
    }
  ]
}
```

**Features:**
- Analyzes critical app settings (debug mode, environment, timezone)
- Checks cache/session/queue drivers for production readiness
- Identifies database configuration issues
- Analyzes Telescope configuration and performance impact
- Recommends performance optimizations (OPcache, route caching, config caching)
- Environment-specific recommendations (production vs staging vs local)
- Security checks (debug mode, sensitive data exposure)

---

### 5. PackageAdvisor Tool

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
