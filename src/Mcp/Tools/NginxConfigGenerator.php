<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Mcp\Tools;

use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

final class NginxConfigGenerator extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'Generate production-ready nginx configuration snippets for rate limiting, security headers, SSL, and Laravel optimization';

    /**
     * Get the tool's input schema.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'config_type' => $schema->string()
                ->description('Type of configuration to generate')
                ->enum(['rate-limiting', 'security-headers', 'ssl-hardening', 'laravel-optimization', 'full-site', 'bot-blocking'])
                ->default('rate-limiting'),

            'server_name' => $schema->string()
                ->description('Server name/domain (e.g., example.com)')
                ->default('example.com'),

            'rate_limit_general' => $schema->string()
                ->description('General rate limit (e.g., 10r/s, 100r/m)')
                ->default('10r/s'),

            'rate_limit_login' => $schema->string()
                ->description('Login endpoint rate limit (e.g., 5r/m)')
                ->default('5r/m'),

            'rate_limit_api' => $schema->string()
                ->description('API rate limit (e.g., 100r/m)')
                ->default('100r/m'),

            'include_hsts' => $schema->boolean()
                ->description('Include HSTS (Strict-Transport-Security) header')
                ->default(true),

            'hsts_max_age' => $schema->integer()
                ->description('HSTS max-age in seconds')
                ->default(31536000),

            'ssl_protocols' => $schema->string()
                ->description('SSL/TLS protocols to enable')
                ->default('TLSv1.2 TLSv1.3'),

            'worker_connections' => $schema->integer()
                ->description('Worker connections limit')
                ->default(2048),

            'php_fpm_socket' => $schema->string()
                ->description('PHP-FPM socket path or address')
                ->default('unix:/var/run/php/php8.3-fpm.sock'),
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $params = $request->all();
        $configType = $params['config_type'] ?? 'rate-limiting';

        $config = match ($configType) {
            'rate-limiting' => $this->generateRateLimitingConfig($params),
            'security-headers' => $this->generateSecurityHeadersConfig($params),
            'ssl-hardening' => $this->generateSslHardeningConfig($params),
            'laravel-optimization' => $this->generateLaravelOptimizationConfig($params),
            'bot-blocking' => $this->generateBotBlockingConfig($params),
            'full-site' => $this->generateFullSiteConfig($params),
            default => ['error' => 'Unknown config type'],
        };

        return Response::json([
            'config_type' => $configType,
            'config' => $config['content'] ?? '',
            'instructions' => $config['instructions'] ?? '',
            'location' => $config['location'] ?? '',
        ]);
    }

    /**
     * Generate rate limiting configuration.
     */
    protected function generateRateLimitingConfig(array $params): array
    {
        $rateGeneral = $params['rate_limit_general'] ?? '10r/s';
        $rateLogin = $params['rate_limit_login'] ?? '5r/m';
        $rateApi = $params['rate_limit_api'] ?? '100r/m';

        $config = <<<NGINX
# ==============================================================================
# Rate Limiting Configuration
# ==============================================================================
# Protects against brute-force, DDoS, and API abuse
# Add this to the http {} block in nginx.conf
# ==============================================================================

# General rate limiting - prevents basic DDoS
limit_req_zone \$binary_remote_addr zone=general:10m rate=$rateGeneral;

# Login endpoint protection - prevents brute-force attacks
limit_req_zone \$binary_remote_addr zone=login:10m rate=$rateLogin;

# API rate limiting - prevents API abuse
limit_req_zone \$binary_remote_addr zone=api:10m rate=$rateApi;

# Connection limiting - limits concurrent connections per IP
limit_conn_zone \$binary_remote_addr zone=addr:10m;

# ==============================================================================
# Apply rate limits in your server {} block:
# ==============================================================================

server {
    # Apply general rate limiting to all requests
    # burst=20 allows temporary burst of 20 requests
    # nodelay processes burst requests immediately without delay
    limit_req zone=general burst=20 nodelay;

    # Limit concurrent connections per IP
    limit_conn addr 10;

    # Strict rate limiting for authentication endpoints
    location ~ ^/(login|api/login|admin/login|api/auth) {
        limit_req zone=login burst=5 nodelay;
        # ... rest of your location config
    }

    # API rate limiting
    location /api/ {
        limit_req zone=api burst=50 nodelay;
        # ... rest of your location config
    }

    # Optional: No rate limit for static assets
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        limit_req off;  # Disable rate limiting for static files
        expires 30d;
        add_header Cache-Control "public, immutable";
    }
}

NGINX;

        return [
            'content' => $config,
            'location' => '/etc/nginx/nginx.conf (http block) + site config',
            'instructions' => implode("\n", [
                '1. Add the limit zones to the http {} block in /etc/nginx/nginx.conf',
                '2. Add the server block configurations to your site config',
                '3. Test configuration: nginx -t',
                '4. Reload nginx: systemctl reload nginx',
                '5. Monitor logs for rate limit hits: tail -f /var/log/nginx/error.log',
            ]),
        ];
    }

    /**
     * Generate security headers configuration.
     */
    protected function generateSecurityHeadersConfig(array $params): array
    {
        $includeHsts = $params['include_hsts'] ?? true;
        $hstsMaxAge = $params['hsts_max_age'] ?? 31536000;

        $hstsHeader = $includeHsts
            ? "    add_header Strict-Transport-Security \"max-age=$hstsMaxAge; includeSubDomains; preload\" always;\n"
            : "    # HSTS disabled - enable when SSL is fully deployed\n";

        $config = <<<NGINX
# ==============================================================================
# Security Headers Configuration
# ==============================================================================
# Add this to your server {} block or location / {} block
# ==============================================================================

# Prevent clickjacking attacks
add_header X-Frame-Options "SAMEORIGIN" always;

# Prevent MIME type sniffing
add_header X-Content-Type-Options "nosniff" always;

# Enable XSS filtering (legacy browsers)
add_header X-XSS-Protection "1; mode=block" always;

# Force HTTPS connections (HSTS)
# WARNING: Only enable after SSL is fully working!
$hstsHeader
# Content Security Policy - customize based on your app's needs
# This is a basic policy - adjust based on your requirements
add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self';" always;

# Referrer Policy - control referrer information
add_header Referrer-Policy "strict-origin-when-cross-origin" always;

# Permissions Policy (formerly Feature Policy)
add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;

# Hide nginx version
server_tokens off;

NGINX;

        return [
            'content' => $config,
            'location' => 'Site config (server {} block)',
            'instructions' => implode("\n", [
                '1. Add to your site\'s server {} block',
                '2. Customize Content-Security-Policy based on your app\'s needs',
                '3. Enable HSTS only after SSL is fully working',
                '4. Test with security headers checker: https://securityheaders.com/',
                '5. Test configuration: nginx -t',
                '6. Reload nginx: systemctl reload nginx',
            ]),
        ];
    }

    /**
     * Generate SSL hardening configuration.
     */
    protected function generateSslHardeningConfig(array $params): array
    {
        $sslProtocols = $params['ssl_protocols'] ?? 'TLSv1.2 TLSv1.3';

        $config = <<<NGINX
# ==============================================================================
# SSL/TLS Hardening Configuration
# ==============================================================================
# Modern, secure SSL configuration for production
# ==============================================================================

# Only use modern, secure protocols
ssl_protocols $sslProtocols;

# Use strong ciphers (Mozilla Modern compatibility)
ssl_ciphers 'ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384';

# Server chooses cipher, not client
ssl_prefer_server_ciphers on;

# SSL session cache for performance
ssl_session_cache shared:SSL:50m;
ssl_session_timeout 1d;
ssl_session_tickets off;

# OCSP Stapling - verify certificate validity
ssl_stapling on;
ssl_stapling_verify on;
resolver 8.8.8.8 8.8.4.4 valid=300s;
resolver_timeout 5s;

# Diffie-Hellman parameter for DHE ciphersuites
# Generate with: openssl dhparam -out /etc/nginx/dhparam.pem 2048
ssl_dhparam /etc/nginx/dhparam.pem;

# HTTP to HTTPS redirect (add to port 80 server block)
server {
    listen 80;
    listen [::]:80;
    server_name _;

    # Redirect all HTTP traffic to HTTPS
    return 301 https://\$host\$request_uri;
}

NGINX;

        return [
            'content' => $config,
            'location' => 'Site config (server {} block)',
            'instructions' => implode("\n", [
                '1. Generate DH parameters: openssl dhparam -out /etc/nginx/dhparam.pem 2048',
                '2. Add to your HTTPS server {} block (port 443)',
                '3. Add HTTP redirect to separate server {} block (port 80)',
                '4. Test SSL with: https://www.ssllabs.com/ssltest/',
                '5. Test configuration: nginx -t',
                '6. Reload nginx: systemctl reload nginx',
            ]),
        ];
    }

    /**
     * Generate Laravel-specific optimization configuration.
     */
    protected function generateLaravelOptimizationConfig(array $params): array
    {
        $serverName = $params['server_name'] ?? 'example.com';
        $phpFpmSocket = $params['php_fpm_socket'] ?? 'unix:/var/run/php/php8.3-fpm.sock';

        $config = <<<NGINX
# ==============================================================================
# Laravel Optimization Configuration
# ==============================================================================
# Optimized nginx configuration for Laravel applications
# ==============================================================================

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name $serverName;

    root /var/www/$serverName/public;
    index index.php index.html;

    # SSL certificates (adjust paths as needed)
    ssl_certificate /etc/letsencrypt/live/$serverName/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/$serverName/privkey.pem;

    # Logging
    access_log /var/log/nginx/$serverName-access.log;
    error_log /var/log/nginx/$serverName-error.log;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Hide nginx version
    server_tokens off;

    # File upload size (adjust as needed)
    client_max_body_size 20M;

    # Main Laravel location
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # PHP-FPM configuration
    location ~ \.php$ {
        try_files \$uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass $phpFpmSocket;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;

        # FastCGI buffers (prevents 502 errors)
        fastcgi_buffer_size 128k;
        fastcgi_buffers 256 16k;
        fastcgi_busy_buffers_size 256k;
        fastcgi_temp_file_write_size 256k;
        fastcgi_read_timeout 240;
    }

    # Cache static assets
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot|webp)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # Deny access to hidden files
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Deny access to sensitive files
    location ~ /\.(env|git|gitignore|gitattributes|htaccess) {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Laravel storage - deny access
    location ^~ /storage/app {
        deny all;
    }

    # Robots.txt and favicon.ico
    location = /favicon.ico {
        access_log off;
        log_not_found off;
    }

    location = /robots.txt {
        access_log off;
        log_not_found off;
    }
}

NGINX;

        return [
            'content' => $config,
            'location' => "/etc/nginx/sites-available/$serverName",
            'instructions' => implode("\n", [
                "1. Save to /etc/nginx/sites-available/$serverName",
                "2. Create symlink: ln -s /etc/nginx/sites-available/$serverName /etc/nginx/sites-enabled/",
                '3. Update paths: root, SSL certificates, PHP-FPM socket',
                '4. Test configuration: nginx -t',
                '5. Reload nginx: systemctl reload nginx',
                '6. Set permissions: chown -R www-data:www-data /var/www/' . $serverName,
            ]),
        ];
    }

    /**
     * Generate bot blocking configuration.
     */
    protected function generateBotBlockingConfig(array $params): array
    {
        $config = <<<'NGINX'
# ==============================================================================
# Bot Blocking Configuration
# ==============================================================================
# Blocks malicious bots and scrapers at nginx level
# Add to http {} block in nginx.conf
# ==============================================================================

# Map bad bots based on user agent
map $http_user_agent $bad_bot {
    default 0;

    # Known malicious bots
    ~*MJ12bot 1;
    ~*AhrefsBot 1;
    ~*SemrushBot 1;
    ~*DotBot 1;
    ~*Baiduspider 1;
    ~*YandexBot 1;
    ~*Sogou 1;

    # Scrapers
    ~*HTTrack 1;
    ~*harvest 1;
    ~*extract 1;
    ~*grab 1;
    ~*miner 1;

    # Vulnerability scanners
    ~*nikto 1;
    ~*nmap 1;
    ~*masscan 1;
    ~*nessus 1;
    ~*sqlmap 1;

    # Empty user agents
    "~^$" 1;
}

# ==============================================================================
# Add to your server {} block:
# ==============================================================================

server {
    # Block bad bots
    if ($bad_bot) {
        return 403;
    }

    # Alternatively, return 444 (nginx-specific - closes connection)
    # if ($bad_bot) {
    #     return 444;
    # }

    # Optional: Log blocked bots
    # if ($bad_bot) {
    #     access_log /var/log/nginx/blocked-bots.log;
    #     return 403;
    # }
}

# ==============================================================================
# Whitelist good bots (Google, Bing, etc.) - Optional
# ==============================================================================

map $http_user_agent $limit_bots {
    default 1;

    # Whitelist legitimate crawlers
    ~*Googlebot 0;
    ~*Bingbot 0;
    ~*facebookexternalhit 0;
    ~*LinkedInBot 0;
    ~*Slackbot 0;
    ~*Discordbot 0;
}

# Use in combination with rate limiting:
# limit_req_zone $binary_remote_addr zone=bots:10m rate=1r/s;
#
# server {
#     if ($limit_bots) {
#         limit_req zone=bots burst=5;
#     }
# }

NGINX;

        return [
            'content' => $config,
            'location' => '/etc/nginx/nginx.conf (http block) + site config',
            'instructions' => implode("\n", [
                '1. Add the map directives to http {} block in nginx.conf',
                '2. Add the if ($bad_bot) block to your server {} config',
                '3. Customize bot list based on your needs',
                '4. Consider whitelisting good bots you want to allow',
                '5. Monitor blocked bots in error logs',
                '6. Test configuration: nginx -t',
                '7. Reload nginx: systemctl reload nginx',
            ]),
        ];
    }

    /**
     * Generate full site configuration.
     */
    protected function generateFullSiteConfig(array $params): array
    {
        $rateLimiting = $this->generateRateLimitingConfig($params);
        $securityHeaders = $this->generateSecurityHeadersConfig($params);
        $laravelOptimization = $this->generateLaravelOptimizationConfig($params);
        $botBlocking = $this->generateBotBlockingConfig($params);

        $config = <<<CONFIG
# ==============================================================================
# Full Production-Ready Nginx Configuration
# ==============================================================================

{$rateLimiting['content']}

{$botBlocking['content']}

{$laravelOptimization['content']}

CONFIG;

        return [
            'content' => $config,
            'location' => 'Multiple files',
            'instructions' => implode("\n", [
                '=== INSTALLATION STEPS ===',
                '',
                '1. BACKUP existing configs:',
                '   - cp /etc/nginx/nginx.conf /etc/nginx/nginx.conf.backup',
                '',
                '2. ADD TO /etc/nginx/nginx.conf (http {} block):',
                '   - Rate limiting zones',
                '   - Bot blocking map',
                '',
                '3. CREATE SITE CONFIG:',
                '   - Save Laravel config to /etc/nginx/sites-available/your-domain',
                '   - Enable: ln -s /etc/nginx/sites-available/your-domain /etc/nginx/sites-enabled/',
                '',
                '4. TEST CONFIGURATION:',
                '   - nginx -t',
                '',
                '5. RELOAD NGINX:',
                '   - systemctl reload nginx',
                '',
                '6. VERIFY:',
                '   - Test site access',
                '   - Check security headers: securityheaders.com',
                '   - Monitor logs: tail -f /var/log/nginx/error.log',
            ]),
        ];
    }
}
