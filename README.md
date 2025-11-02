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

### ProjectStructureAnalyzer

Analyze your project structure including composer scripts, GitHub workflows, testing setup, Git hooks, and deployment process.

**Parameters:**
- `include_actions` (optional, default: false): Include actionable recommendations with stub file contents and installation commands

**Example Response:**
```json
{
  "severity_counts": {
    "critical": 0,
    "warning": 0
  },
  "issues": [],
  "good_practices": [
    {
      "category": "composer",
      "message": "Development script configured for concurrent processes",
      "details": "Good practice: single command to run server, queue, and vite"
    }
  ],
  "recommendations": [
    {
      "category": "ci-cd",
      "message": "No GitHub Actions workflows found",
      "benefit": "Add CI/CD workflows for automated testing and quality checks"
    }
  ]
}
```

**Features:**
- Analyzes composer.json scripts (test, lint, quality checks)
- Checks for GitHub Actions workflows
- Validates package.json frontend setup
- Analyzes testing setup (Pest, PHPUnit, browser testing)
- Checks for Git hooks (GrumPHP, CaptainHook)
- Validates deployment process (Deployer, Laravel Forge)
- **NEW**: With `include_actions=true`, returns actionable recommendations including:
  - Installation commands for recommended packages
  - Complete stub file contents ready to copy (GitHub workflows, CaptainHook config, Deployer config, etc.)
  - Composer scripts to merge into your composer.json
  - Specific file destinations for each stub

**Available Stub Files:**
- `.github/` - Complete GitHub configuration directory including:
  - `.github/workflows/tests.yml` - Main CI/CD workflow with parallel testing, Pint, PHPStan, Rector, type coverage
  - `.github/workflows/dependabot-auto-merge.yml` - Auto-merge workflow for Dependabot PRs
  - `.github/actions/setup/action.yml` - Custom GitHub Action for PHP, Composer, and pnpm setup
  - `.github/dependabot.yml` - Dependabot configuration for automated dependency updates
- `captainhook.json` - Git hooks configuration with pre-commit checks
- `deploy.php` - Deployer configuration for zero-downtime deployments
- `pint.json` - Laravel Pint code style configuration
- `phpstan.neon` - PHPStan static analysis configuration
- `rector.php` - Rector automated refactoring configuration
- `composer-scripts.json` - Recommended composer scripts for testing and quality

### ConfigurationAnalyzer

Analyze your Laravel configuration for performance, security, and optimization opportunities.

**Parameters:**
- `environment` (optional): Target environment (production/staging/local) - defaults to APP_ENV

**Example Response:**
```json
{
  "environment": "production",
  "severity_counts": {
    "critical": 0,
    "warning": 2,
    "info": 0
  },
  "issues": [],
  "recommendations": [
    {
      "config": "cache.default",
      "message": "Consider using Redis for better performance",
      "benefit": "Faster cache operations"
    }
  ]
}
```

**Features:**
- Analyzes app configuration (debug mode, environment, timezone)
- Checks cache, session, queue, and database drivers for production readiness
- Identifies Telescope configuration issues
- Recommends performance optimizations (OPcache, route/config caching)
- Security checks (debug mode in production, driver configurations)
- Environment-specific recommendations

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
    "larastan/larastan": "Static analysis tool for Laravel",
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
