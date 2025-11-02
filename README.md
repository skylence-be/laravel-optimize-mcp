# Laravel Optimize MCP

Laravel Optimize MCP provides optimization tools and utilities for AI-assisted development through the Model Context Protocol (MCP).

## Features

- **Dual Server Mode**: Supports both PHP stdio (local) and HTTP (remote) MCP servers
- **Configuration Analysis**: Analyze Laravel configuration for performance and security
- **Token Authentication**: Secure HTTP endpoints with bearer token authentication
- **Project Structure Analysis**: Analyze and optimize development workflow
- **Package Recommendations**: Get smart package suggestions for your project

## Installation

You can install the package via Composer:

```bash
composer require skylence/laravel-optimize-mcp
```

Run the installation command:

```bash
php artisan optimize-mcp:install
```

This will publish the configuration file to `config/optimize-mcp.php`.

## Configuration

### PHP Stdio Server (Local Development)

The package automatically registers MCP stdio routes for local development. Use the server key `php-laravel-optimize` in your Claude Desktop or MCP client configuration:

```json
{
  "mcpServers": {
    "php-laravel-optimize": {
      "command": "php",
      "args": ["artisan", "mcp:start", "optimize"]
    }
  }
}
```

### HTTP Server (Remote Access)

For HTTP access, configure your `.env` file:

```env
# Enable/disable authentication (defaults to true)
OPTIMIZE_MCP_AUTH_ENABLED=true

# Generate a secure token: php artisan tinker --execute="echo bin2hex(random_bytes(32))"
OPTIMIZE_MCP_API_TOKEN=your-secure-token-here
```

Use the server key `http-laravel-optimize` in your `.mcp.json` or MCP client configuration:

```json
{
  "mcpServers": {
    "http-laravel-optimize": {
      "url": "https://your-app.com/optimize-mcp",
      "headers": {
        "X-MCP-Token": "your-secure-token-here"
      }
    }
  }
}
```

Alternatively, you can use Bearer token authentication:

```json
{
  "mcpServers": {
    "http-laravel-optimize": {
      "url": "https://your-app.com/optimize-mcp",
      "headers": {
        "Authorization": "Bearer your-secure-token-here"
      }
    }
  }
}
```

### Configuration Options

Edit `config/optimize-mcp.php` to customize:

```php
return [
    // Enable/disable specific tools
    'tools' => [
        'configuration-analyzer' => true,
        'project-structure-analyzer' => false, // Disabled for HTTP by default
        'package-advisor' => false, // Disabled for HTTP by default
    ],

    // HTTP endpoint configuration
    'http' => [
        'enabled' => true,
        'prefix' => 'optimize-mcp',
        'middleware' => [
            \Skylence\OptimizeMcp\Http\Middleware\AuthenticateMcp::class,
        ],
    ],

    // Authentication settings
    'auth' => [
        'enabled' => env('OPTIMIZE_MCP_AUTH_ENABLED', true),
        'token' => env('OPTIMIZE_MCP_API_TOKEN'),
    ],
];
```

## Available Tools

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

### Testing PHP Stdio Server

You can test the local MCP server using the MCP Inspector:

```bash
php artisan mcp:inspector optimize
```

### Testing HTTP Server

Test HTTP endpoints with curl:

```bash
# Without authentication (if disabled)
curl -X POST http://localhost/optimize-mcp \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{}}'

# With X-MCP-Token header
curl -X POST http://localhost/optimize-mcp \
  -H "Content-Type: application/json" \
  -H "X-MCP-Token: your-token-here" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{}}'

# With Bearer token
curl -X POST http://localhost/optimize-mcp \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer your-token-here" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{}}'
```

## Security

### Token Authentication

HTTP endpoints are secured with token authentication by default. Configure in your `.env`:

```env
OPTIMIZE_MCP_AUTH_ENABLED=true
OPTIMIZE_MCP_API_TOKEN=your-secure-token
```

Generate a secure token:

```bash
php artisan tinker --execute="echo bin2hex(random_bytes(32))"
```

### Disabling Authentication

For local development only, you can disable authentication:

```env
OPTIMIZE_MCP_AUTH_ENABLED=false
```

**Warning**: Never disable authentication in production environments.

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
