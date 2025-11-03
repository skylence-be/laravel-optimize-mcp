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

**Example output:**
- Critical issues (e.g., debug mode enabled in production)
- Warnings (e.g., using file cache driver in production)
- Recommendations (e.g., enable opcache, use Redis for cache)
- Missing performance optimizations

### Database Size Inspector
**Tool:** `database-size-inspector`
**Purpose:** Inspect database size and table-by-table breakdown with growth tracking
**Availability:** HTTP MCP only (remote servers)

**Usage:**
```json
{
  "tool": "database-size-inspector",
  "params": {
    "format": "summary"  // or "detailed"
  }
}
```

**Supports:**
- MySQL/MariaDB (full size breakdown: data + indexes)
- PostgreSQL
- SQLite
- Generic fallback for other databases

**Provides:**
- Total database size (MB/GB)
- Disk usage percentage
- Table sizes sorted by size
- Row counts per table
- Growth trends (if monitoring enabled)
- Recommendations for cleanup/optimization

### Log File Inspector
**Tool:** `log-file-inspector`
**Purpose:** Inspect log file sizes and check log rotation configuration
**Availability:** HTTP MCP only (remote servers)

**Usage:**
```json
{
  "tool": "log-file-inspector",
  "params": {
    "format": "summary"  // or "detailed"
  }
}
```

**Detects:**
- All log files in storage/logs
- File sizes and ages
- Log rotation configuration (Laravel daily driver, logrotate)
- Large log files (>50MB warning, >100MB critical)
- Daily log rotation patterns

**Recommendations:**
- Enable daily log rotation if not configured
- Set LOG_CHANNEL=daily in .env
- Configure retention period with LOG_DAILY_DAYS

### Nginx Config Inspector
**Tool:** `nginx-config-inspector`
**Purpose:** Analyze nginx configuration for security, performance, and Laravel optimization
**Availability:** HTTP MCP only (remote servers)

**Usage:**
```json
{
  "tool": "nginx-config-inspector",
  "params": {
    "format": "summary"  // or "detailed"
  }
}
```

**Analyzes:**
- Worker processes and connections
- Gzip compression
- Security headers (HSTS, CSP, X-Frame-Options, etc.)
- SSL/TLS protocols and ciphers
- **Rate limiting** (limit_req_zone, limit_conn_zone)
- **Bot blocking** (user-agent filtering)
- **IP filtering** and geographic blocking
- PHP-FPM configuration
- Static file caching
- Laravel-specific optimizations

**Security checks:**
- Rate limiting zones configured
- Connection limiting per IP
- Bot blocking rules (malicious crawlers)
- IP blacklisting/whitelisting
- Geographic blocking

**Recommendations:**
- Add missing rate limiting to prevent DDoS/brute-force
- Enable security headers
- Configure bot blocking
- Optimize worker processes and connections

### Nginx Config Generator
**Tool:** `nginx-config-generator`
**Purpose:** Generate production-ready nginx configurations
**Availability:** HTTP MCP only (remote servers)

**Config Types:**
1. **`rate-limiting`** - DDoS and brute-force protection
2. **`security-headers`** - Complete security header suite
3. **`ssl-hardening`** - Modern SSL/TLS configuration
4. **`laravel-optimization`** - Complete Laravel site config
5. **`bot-blocking`** - Block malicious bots and scrapers
6. **`full-site`** - Combined complete configuration

**Usage:**
```json
{
  "tool": "nginx-config-generator",
  "params": {
    "config_type": "rate-limiting",
    "server_name": "example.com",
    "rate_limit_general": "10r/s",
    "rate_limit_login": "5r/m",
    "rate_limit_api": "100r/m",
    "include_hsts": true,
    "ssl_protocols": "TLSv1.2 TLSv1.3",
    "php_fpm_socket": "unix:/var/run/php/php8.3-fpm.sock"
  }
}
```

**Returns:**
- Production-ready nginx configuration
- Step-by-step installation instructions
- File locations and testing commands
- Verification steps

### Project Structure Analyzer
**Tool:** `project-structure-analyzer`
**Purpose:** Analyze project structure including CI/CD, testing, Git hooks, deployment
**Availability:** stdio/PHP MCP only (local development)

**Usage:**
```json
{
  "tool": "project-structure-analyzer",
  "params": {
    "include_actions": true  // Include stub files and installation commands
  }
}
```

**Analyzes:**
- Composer scripts (build, format, test, etc.)
- GitHub Actions workflows
- package.json scripts
- Testing setup (PHPUnit, Pest, browser testing)
- Git hooks (CaptainHook, GrumPHP)
- Deployment processes (Deployer, Envoyer)

**Provides:**
- Issues (critical and warnings)
- Recommendations with benefits
- Actionable items with stub files (if include_actions=true)
- Installation commands for missing tools

### Package Advisor
**Tool:** `package-advisor`
**Purpose:** Suggest useful Laravel packages to improve development and performance
**Availability:** stdio/PHP MCP only (local development)

**Usage:**
```json
{
  "tool": "package-advisor",
  "params": {
    "add_to_composer": false,  // Auto-install to composer.json
    "add_to_package_json": false  // Auto-install to package.json
  }
}
```

**Recommends:**
- Essential packages (Laravel Pint, Boost, Larastan, IDE Helper)
- Testing tools (Pest v4 with Laravel and browser plugins)
- Monitoring (Pulse for production, Telescope for dev)
- Code quality tools (Rector, PHPStan)
- Frontend packages (Tailwind plugins, Alpine.js, Prettier)

**Detects:**
- Outdated/abandoned packages
- Telescope in production (performance risk)
- Missing MCP integrations

## Tool Context Awareness

**HTTP MCP Tools** (Remote servers only):
- database-size-inspector
- log-file-inspector
- nginx-config-inspector
- nginx-config-generator

**stdio/PHP MCP Tools** (Local development only):
- project-structure-analyzer
- package-advisor

**Universal Tools** (Both contexts):
- configuration-analyzer

## Best Practices

### When to Use Each Tool

**During Development (stdio/PHP MCP):**
- Use `configuration-analyzer` to check config before deployment
- Use `project-structure-analyzer` to audit CI/CD and tooling
- Use `package-advisor` to discover useful packages

**On Production Servers (HTTP MCP):**
- Use `database-size-inspector` to monitor database growth
- Use `log-file-inspector` to check log rotation and file sizes
- Use `nginx-config-inspector` to audit server security
- Use `nginx-config-generator` to create/update nginx configs

### Security and Performance

**Rate Limiting:**
- Always implement nginx-level rate limiting for production
- Recommended: 10r/s general, 5r/m for login, 100r/m for API
- Protects against brute-force and DDoS attacks

**Bot Blocking:**
- Block malicious crawlers at nginx level (saves PHP/Laravel resources)
- Whitelist legitimate bots (Google, Bing, Facebook)
- Reduces server load from malicious scrapers

**Log Rotation:**
- Enable daily log rotation with LOG_CHANNEL=daily
- Set retention period with LOG_DAILY_DAYS (default: 14)
- Prevents disk space issues from growing log files

**Database Monitoring:**
- Monitor database size growth trends
- Set up alerts for high disk usage (>80% warning, >90% critical)
- Regularly prune old data (Telescope, logs, caches)

## Configuration

**Environment Variables:**
```env
# Enable database monitoring
OPTIMIZE_MCP_DB_MONITORING=true
OPTIMIZE_MCP_DB_MONITORING_FREQUENCY=daily
OPTIMIZE_MCP_DB_WARNING_THRESHOLD=80
OPTIMIZE_MCP_DB_CRITICAL_THRESHOLD=90

# Logging
OPTIMIZE_MCP_LOGGING_ENABLED=false
OPTIMIZE_MCP_LOGGING_CHANNEL=stack

# HTTP MCP Authentication
OPTIMIZE_MCP_AUTH_ENABLED=true
OPTIMIZE_MCP_API_TOKEN=your-secure-token-here
```

**Artisan Commands:**
```bash
# Install package
php artisan optimize-mcp:install

# Monitor database size (if monitoring enabled)
php artisan optimize-mcp:monitor-database-size

# Prune old database logs
php artisan optimize-mcp:prune-database-logs --days=90
```

## Integration Examples

### Check Configuration Before Deployment
```json
{
  "tool": "configuration-analyzer",
  "params": {
    "environment": "production"
  }
}
```

### Monitor Production Database
```json
{
  "tool": "database-size-inspector",
  "params": {
    "format": "summary"
  }
}
```

### Generate Rate Limiting Config
```json
{
  "tool": "nginx-config-generator",
  "params": {
    "config_type": "rate-limiting",
    "server_name": "example.com",
    "rate_limit_general": "10r/s",
    "rate_limit_login": "5r/m"
  }
}
```

### Audit Project Structure
```json
{
  "tool": "project-structure-analyzer",
  "params": {
    "include_actions": true
  }
}
```

## Common Issues and Solutions

### "Tool is only available for HTTP MCP"
**Problem:** Trying to use database-size-inspector, log-file-inspector, or nginx tools in stdio/PHP MCP
**Solution:** These tools require HTTP MCP context. Use them on remote servers via HTTP MCP API.

### "No rate limiting configured"
**Problem:** nginx-config-inspector reports missing rate limiting
**Solution:** Use nginx-config-generator with config_type="rate-limiting" to generate configuration

### "Log files growing too large"
**Problem:** log-file-inspector shows large log files
**Solution:** Enable daily log rotation with LOG_CHANNEL=daily in .env

### "Database growth warning"
**Problem:** database-size-inspector shows high disk usage
**Solution:** Review growth trends, prune old data, consider archival strategies

## Documentation

For more information, visit:
- GitHub: https://github.com/skylence-be/laravel-optimize-mcp
- Documentation: Check README.md in package root
- MCP Protocol: https://modelcontextprotocol.io/
