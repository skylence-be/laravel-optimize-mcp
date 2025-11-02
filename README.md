# Laravel Optimize MCP

Optimize your Laravel project with AI assistance through the Model Context Protocol (MCP).

## Installation

Install via Composer:

```bash
composer require skylence/laravel-optimize-mcp
```

Run the installation command and follow the prompts:

```bash
php artisan optimize-mcp:install
```

The installer will:
- Automatically detect and configure your code editor (Cursor, Claude Code, VS Code, or PhpStorm)
- Ask if you want HTTP access for staging/production servers
- Generate a secure token and configuration instructions for remote access

## Usage

Once installed, ask your AI assistant to optimize your Laravel project:

**"Analyze my Laravel project and help me optimize it"**

Your AI will use the installed MCP tools to:
- Analyze your configuration for performance and security issues
- Review your project structure and development workflow
- Recommend useful packages and improvements
- Provide actionable recommendations with code snippets

## Remote Access for Staging/Production

Want to analyze your staging or production environment? The installer can configure this for you automatically, or you can set it up manually by adding these to your `.env` file:

```env
# Enable secure HTTP access
OPTIMIZE_MCP_AUTH_ENABLED=true

# Generate token: php artisan tinker --execute="echo bin2hex(random_bytes(32))"
OPTIMIZE_MCP_API_TOKEN=your-secure-token-here
```

Then ask your AI to connect to your remote server:

**"Connect to my production Laravel server at https://myapp.com and analyze the .env configuration"**

This allows you to check production environment variables, cache/session drivers, and security settings without SSH access.

## What's Included

- **Configuration Analyzer**: Checks your Laravel config for performance and security
- **Project Structure Analyzer**: Reviews your composer scripts, CI/CD, testing setup, and more
- **Package Advisor**: Recommends useful packages for your project

## Advanced Configuration

For remote access, HTTP endpoints, and custom tool configuration, see the [full documentation](https://github.com/skylence/laravel-optimize-mcp).

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
