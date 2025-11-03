# Laravel Optimize MCP - Development Guide

This package provides powerful MCP (Model Context Protocol) tools for analyzing, inspecting, and optimizing Laravel applications.

## Testing

```bash
# Run all tests
composer test

# Run specific test file
composer test tests/Unit/ExampleTest.php

# Run with filter
composer test --filter=testExample
```

## Code Quality

```bash
# Fix code style
composer lint

# Check code style
composer test:lint

# Run static analysis
composer test:types
```

## Development Commands

```bash
# Install package in a Laravel app
php artisan optimize-mcp:install

# Run MCP server (stdio)
php artisan mcp

# Test HTTP MCP endpoints
curl -X POST http://localhost/optimize-mcp/tools/configuration-analyzer \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer your-token" \
  -d '{"environment":"production"}'
```

---

<laravel-optimize-mcp-guidelines>
# Laravel Optimize MCP Guidelines

Laravel Optimize MCP provides powerful Model Context Protocol (MCP) tools for analyzing, inspecting, and optimizing Laravel applications. These tools help you identify performance bottlenecks, security issues, and configuration problems.

## Package Information
This application uses `skylence/laravel-optimize-mcp` which provides:
- Configuration analysis and optimization recommendations
- Database size monitoring and growth tracking
- Log file inspection and rotation management
- Nginx configuration analysis (for production servers)
- Nginx configuration generation (production-ready configs)
- Project structure analysis
- Package recommendations and dependency analysis

## Available MCP Tools

### Configuration Analyzer
**Tool:** `configuration-analyzer`
**Purpose:** Analyze Laravel configuration for performance, security, and optimization opportunities

**Usage:**
```json
{
  "tool": "configuration-analyzer",
  "params": {
    "environment": "production"  // or "staging", "local"
  }
}
```

**What it checks:**
- App configuration (debug mode, APP_KEY, timezone)
- Environment drivers (cache, session, queue, mail)
- Performance optimizations (route caching, config caching, etc.)
- Database configuration
- Telescope configuration (if installed)
- **Log rotation** (checks if daily log rotation is enabled)

### Database Size Inspector
**Tool:** `database-size-inspector`
**Availability:** HTTP MCP only (remote servers)

### Log File Inspector
**Tool:** `log-file-inspector`
**Availability:** HTTP MCP only (remote servers)

### Nginx Config Inspector
**Tool:** `nginx-config-inspector`
**Availability:** HTTP MCP only (remote servers)

### Nginx Config Generator
**Tool:** `nginx-config-generator`
**Availability:** HTTP MCP only (remote servers)

### Project Structure Analyzer
**Tool:** `project-structure-analyzer`
**Availability:** stdio/PHP MCP only (local development)

### Package Advisor
**Tool:** `package-advisor`
**Availability:** stdio/PHP MCP only (local development)

## Best Practices

**During Development (stdio/PHP MCP):**
- Use `configuration-analyzer` to check config before deployment
- Use `project-structure-analyzer` to audit CI/CD and tooling
- Use `package-advisor` to discover useful packages

**On Production Servers (HTTP MCP):**
- Use `database-size-inspector` to monitor database growth
- Use `log-file-inspector` to check log rotation and file sizes
- Use `nginx-config-inspector` to audit server security
- Use `nginx-config-generator` to create/update nginx configs

For complete documentation, see `.ai/optimize-mcp-guidelines.md`
</laravel-optimize-mcp-guidelines>
