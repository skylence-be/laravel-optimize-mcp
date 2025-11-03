<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Mcp\Tools;

use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
final class NginxConfigInspector extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'Analyze nginx web server configuration and provide security, performance, and Laravel-specific recommendations';

    /**
     * Common nginx config locations to search.
     */
    protected array $configPaths = [
        '/etc/nginx/nginx.conf',
        '/usr/local/etc/nginx/nginx.conf',
        '/opt/nginx/conf/nginx.conf',
        'C:\nginx\conf\nginx.conf',
        'C:\Program Files\nginx\conf\nginx.conf',
    ];

    /**
     * Common site config directories.
     */
    protected array $sitePaths = [
        '/etc/nginx/sites-enabled',
        '/etc/nginx/sites-available',
        '/etc/nginx/conf.d',
        '/usr/local/etc/nginx/sites-enabled',
        '/usr/local/etc/nginx/conf.d',
    ];

    /**
     * Get the tool's input schema.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'format' => $schema->string()
                ->description('Output format: summary (human-readable) or detailed (full JSON)')
                ->enum(['summary', 'detailed'])
                ->default('summary'),
        ];
    }

    /**
     * Check if running in HTTP context (vs stdio).
     */
    protected function isHttpContext(): bool
    {
        if (! app()->bound('request')) {
            return false;
        }

        try {
            $request = app('request');

            return $request instanceof \Illuminate\Http\Request && ! app()->runningInConsole();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        // This tool is only available for HTTP MCP (remote servers)
        if (! $this->isHttpContext()) {
            return Response::json([
                'error' => true,
                'message' => 'NginxConfigInspector is only available for HTTP MCP (remote servers). For local development, nginx configuration is typically not needed.',
            ]);
        }

        $params = $request->all();
        $format = $params['format'] ?? 'summary';

        try {
            $data = $this->analyzeNginxConfiguration();

            if ($format === 'detailed') {
                return Response::json($data);
            }

            // Build human-readable summary
            $summary = $this->buildSummary($data);

            return Response::json([
                'summary' => $summary,
                'nginx_detected' => $data['nginx_detected'] ?? false,
                'config_path' => $data['config_path'] ?? null,
                'total_sites' => count($data['sites'] ?? []),
                'critical_issues' => $this->countSeverity($data, 'critical'),
                'warnings' => $this->countSeverity($data, 'warning'),
            ]);
        } catch (\Exception $e) {
            return Response::json([
                'error' => true,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Analyze nginx configuration.
     */
    protected function analyzeNginxConfiguration(): array
    {
        $analysis = [
            'nginx_detected' => false,
            'config_path' => null,
            'sites' => [],
            'issues' => [],
            'recommendations' => [],
            'good_practices' => [],
            'security' => [],
            'performance' => [],
        ];

        // Try to find nginx config
        $mainConfig = $this->findMainConfig();
        if (!$mainConfig) {
            $analysis['recommendations'][] = [
                'category' => 'detection',
                'message' => 'Could not find nginx configuration files',
                'details' => 'Searched common locations: '.implode(', ', $this->configPaths),
                'fix' => 'Ensure nginx is installed and PHP has read permissions to config files',
            ];

            return $analysis;
        }

        $analysis['nginx_detected'] = true;
        $analysis['config_path'] = $mainConfig;

        // Read and analyze main config
        $configContent = file_get_contents($mainConfig);
        $this->analyzeMainConfig($configContent, $analysis);

        // Find and analyze site configs
        $this->analyzeSiteConfigs($analysis);

        return $analysis;
    }

    /**
     * Find the main nginx config file.
     */
    protected function findMainConfig(): ?string
    {
        foreach ($this->configPaths as $path) {
            if (file_exists($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Analyze main nginx configuration.
     */
    protected function analyzeMainConfig(string $content, array &$analysis): void
    {
        // Worker processes
        if (preg_match('/worker_processes\s+(\d+|auto);/i', $content, $matches)) {
            $workerProcesses = $matches[1];
            if ($workerProcesses === 'auto') {
                $analysis['good_practices'][] = [
                    'category' => 'performance',
                    'message' => 'Worker processes set to auto',
                    'details' => 'Nginx will automatically set worker processes based on CPU cores',
                ];
            } else {
                $cpuCores = $this->getCpuCores();
                if ($cpuCores && (int) $workerProcesses !== $cpuCores) {
                    $analysis['recommendations'][] = [
                        'category' => 'performance',
                        'message' => "Worker processes ($workerProcesses) doesn't match CPU cores ($cpuCores)",
                        'fix' => "Set worker_processes to 'auto' or $cpuCores;",
                    ];
                }
            }
        } else {
            $analysis['recommendations'][] = [
                'category' => 'performance',
                'message' => 'Worker processes not configured',
                'fix' => "Add 'worker_processes auto;' to nginx.conf",
            ];
        }

        // Worker connections
        if (preg_match('/worker_connections\s+(\d+);/i', $content, $matches)) {
            $workerConnections = (int) $matches[1];
            if ($workerConnections < 1024) {
                $analysis['recommendations'][] = [
                    'category' => 'performance',
                    'message' => "Worker connections ($workerConnections) is low",
                    'fix' => 'Consider increasing to at least 1024 for better concurrency',
                ];
            } elseif ($workerConnections >= 2048) {
                $analysis['good_practices'][] = [
                    'category' => 'performance',
                    'message' => "Worker connections optimized ($workerConnections)",
                    'details' => 'High connection limit for better concurrency',
                ];
            }
        }

        // Gzip compression
        if (preg_match('/gzip\s+on;/i', $content)) {
            $analysis['good_practices'][] = [
                'category' => 'performance',
                'message' => 'Gzip compression enabled',
                'details' => 'Reduces bandwidth usage and improves load times',
            ];

            // Check gzip types
            if (preg_match('/gzip_types\s+(.+?);/i', $content, $matches)) {
                $analysis['performance'][] = [
                    'setting' => 'gzip_types',
                    'value' => trim($matches[1]),
                    'status' => 'configured',
                ];
            } else {
                $analysis['recommendations'][] = [
                    'category' => 'performance',
                    'message' => 'Gzip types not specified',
                    'fix' => 'Add gzip_types directive to compress more file types (text/css application/javascript application/json)',
                ];
            }
        } else {
            $analysis['recommendations'][] = [
                'category' => 'performance',
                'message' => 'Gzip compression disabled',
                'fix' => 'Enable gzip compression: gzip on; gzip_types text/plain text/css application/json application/javascript;',
            ];
        }

        // Client body size
        if (preg_match('/client_max_body_size\s+(\d+[KMG]?);/i', $content, $matches)) {
            $analysis['performance'][] = [
                'setting' => 'client_max_body_size',
                'value' => $matches[1],
                'status' => 'configured',
            ];
        } else {
            $analysis['recommendations'][] = [
                'category' => 'configuration',
                'message' => 'Client max body size not configured (defaults to 1m)',
                'fix' => 'Set client_max_body_size based on your needs (e.g., client_max_body_size 20M;)',
            ];
        }

        // Security headers - check if they're commonly set in http block
        $this->checkSecurityHeaders($content, $analysis);

        // Logging
        if (preg_match('/access_log\s+off;/i', $content)) {
            $analysis['issues'][] = [
                'severity' => 'warning',
                'category' => 'logging',
                'message' => 'Access logging is disabled globally',
                'fix' => 'Enable access logging for monitoring and debugging',
            ];
        }

        // Buffer sizes
        if (preg_match('/client_body_buffer_size\s+(\d+[KMG]?);/i', $content, $matches)) {
            $analysis['performance'][] = [
                'setting' => 'client_body_buffer_size',
                'value' => $matches[1],
                'status' => 'configured',
            ];
        }

        // Keepalive
        if (preg_match('/keepalive_timeout\s+(\d+);/i', $content, $matches)) {
            $timeout = (int) $matches[1];
            if ($timeout < 10) {
                $analysis['recommendations'][] = [
                    'category' => 'performance',
                    'message' => "Keepalive timeout very short ($timeout seconds)",
                    'fix' => 'Consider increasing to 30-65 seconds for better connection reuse',
                ];
            }
        }

        // HTTP/2
        $analysis['http2_detected'] = preg_match('/http2\s+on;/i', $content) || preg_match('/listen\s+.*http2/i', $content);
    }

    /**
     * Check for security headers in config.
     */
    protected function checkSecurityHeaders(string $content, array &$analysis): void
    {
        $securityHeaders = [
            'X-Frame-Options' => 'Prevents clickjacking attacks',
            'X-Content-Type-Options' => 'Prevents MIME type sniffing',
            'X-XSS-Protection' => 'Enables XSS filtering',
            'Strict-Transport-Security' => 'Forces HTTPS connections (HSTS)',
            'Content-Security-Policy' => 'Restricts resource loading to prevent XSS',
        ];

        foreach ($securityHeaders as $header => $purpose) {
            if (preg_match('/'.preg_quote($header, '/').'/i', $content)) {
                $analysis['good_practices'][] = [
                    'category' => 'security',
                    'message' => "$header header configured",
                    'details' => $purpose,
                ];
            } else {
                $analysis['recommendations'][] = [
                    'category' => 'security',
                    'message' => "$header header not set",
                    'fix' => "Add security header: add_header $header \"...\";",
                    'benefit' => $purpose,
                ];
            }
        }

        // Check if server tokens are hidden
        if (preg_match('/server_tokens\s+off;/i', $content)) {
            $analysis['good_practices'][] = [
                'category' => 'security',
                'message' => 'Server tokens disabled',
                'details' => 'Hides nginx version from response headers',
            ];
        } else {
            $analysis['recommendations'][] = [
                'category' => 'security',
                'message' => 'Server tokens not disabled',
                'fix' => 'Add server_tokens off; to hide nginx version',
            ];
        }
    }

    /**
     * Analyze site-specific configurations.
     */
    protected function analyzeSiteConfigs(array &$analysis): void
    {
        foreach ($this->sitePaths as $sitePath) {
            if (! is_dir($sitePath) || ! is_readable($sitePath)) {
                continue;
            }

            $files = scandir($sitePath);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                $filePath = $sitePath.DIRECTORY_SEPARATOR.$file;
                if (! is_file($filePath) || ! is_readable($filePath)) {
                    continue;
                }

                // Skip symlinks to avoid duplicates
                if (is_link($filePath)) {
                    continue;
                }

                $siteConfig = file_get_contents($filePath);
                $siteAnalysis = $this->analyzeSiteConfig($file, $siteConfig);
                $analysis['sites'][] = $siteAnalysis;
            }
        }
    }

    /**
     * Analyze individual site configuration.
     */
    protected function analyzeSiteConfig(string $filename, string $content): array
    {
        $analysis = [
            'filename' => $filename,
            'issues' => [],
            'recommendations' => [],
            'good_practices' => [],
        ];

        // Server name
        if (preg_match('/server_name\s+([^;]+);/i', $content, $matches)) {
            $analysis['server_name'] = trim($matches[1]);
        }

        // SSL/TLS configuration
        if (preg_match('/ssl_certificate\s+/i', $content)) {
            $analysis['ssl_enabled'] = true;

            // Check SSL protocols
            if (preg_match('/ssl_protocols\s+([^;]+);/i', $content, $matches)) {
                $protocols = trim($matches[1]);
                if (preg_match('/TLSv1\.3/', $protocols)) {
                    $analysis['good_practices'][] = 'TLS 1.3 enabled';
                }
                if (preg_match('/SSLv|TLSv1[^.2-3]/', $protocols)) {
                    $analysis['issues'][] = [
                        'severity' => 'critical',
                        'message' => 'Insecure SSL/TLS protocols enabled (SSLv3, TLSv1.0, TLSv1.1)',
                        'fix' => 'Use only TLSv1.2 and TLSv1.3: ssl_protocols TLSv1.2 TLSv1.3;',
                    ];
                }
            } else {
                $analysis['recommendations'][] = 'Explicitly set SSL protocols to TLSv1.2 and TLSv1.3';
            }

            // Check SSL ciphers
            if (preg_match('/ssl_ciphers\s+/i', $content)) {
                $analysis['good_practices'][] = 'SSL ciphers configured';
            } else {
                $analysis['recommendations'][] = 'Configure strong SSL ciphers';
            }

            // HTTPS redirect
            if (preg_match('/listen\s+80/i', $content) && ! preg_match('/return\s+301\s+https/i', $content)) {
                $analysis['recommendations'][] = 'Add HTTP to HTTPS redirect for security';
            }
        } else {
            $analysis['ssl_enabled'] = false;
            $analysis['recommendations'][] = [
                'category' => 'security',
                'message' => 'SSL/TLS not configured',
                'fix' => 'Configure SSL certificates and enable HTTPS',
            ];
        }

        // PHP-FPM configuration for Laravel
        if (preg_match('/fastcgi_pass\s+/i', $content)) {
            $analysis['php_fpm_detected'] = true;

            // Check for try_files with proper Laravel configuration
            if (preg_match('/try_files.*\$uri.*\/index\.php/i', $content)) {
                $analysis['good_practices'][] = 'Laravel-friendly URL rewriting configured';
            } else {
                $analysis['recommendations'][] = [
                    'category' => 'laravel',
                    'message' => 'URL rewriting may not be optimized for Laravel',
                    'fix' => 'Use: try_files $uri $uri/ /index.php?$query_string;',
                ];
            }

            // Check fastcgi buffers
            if (! preg_match('/fastcgi_buffer/i', $content)) {
                $analysis['recommendations'][] = [
                    'category' => 'performance',
                    'message' => 'FastCGI buffers not configured',
                    'fix' => 'Add fastcgi_buffer_size and fastcgi_buffers directives',
                ];
            }
        }

        // Static file caching
        if (preg_match('/location\s+~\*\s+.*\.(jpg|jpeg|png|gif|ico|css|js)/i', $content)) {
            if (preg_match('/expires\s+/i', $content)) {
                $analysis['good_practices'][] = 'Static file caching configured';
            } else {
                $analysis['recommendations'][] = [
                    'category' => 'performance',
                    'message' => 'No cache headers for static files',
                    'fix' => 'Add expires directive for static assets (expires 30d;)',
                ];
            }
        }

        // Rate limiting
        if (preg_match('/limit_req_zone|limit_req\s+/i', $content)) {
            $analysis['good_practices'][] = 'Rate limiting configured';
        }

        return $analysis;
    }

    /**
     * Get number of CPU cores (best effort).
     */
    protected function getCpuCores(): ?int
    {
        try {
            if (PHP_OS_FAMILY === 'Windows') {
                $output = shell_exec('wmic cpu get NumberOfCores');
                if ($output && preg_match('/\d+/', $output, $matches)) {
                    return (int) $matches[0];
                }
            } else {
                $output = shell_exec('nproc 2>/dev/null || grep -c ^processor /proc/cpuinfo 2>/dev/null');
                if ($output) {
                    return (int) trim($output);
                }
            }
        } catch (\Exception $e) {
            // Ignore
        }

        return null;
    }

    /**
     * Count issues by severity.
     */
    private function countSeverity(array $data, string $severity): int
    {
        $count = 0;

        // Main config issues
        $count += count(array_filter($data['issues'] ?? [], fn ($i) => ($i['severity'] ?? null) === $severity));

        // Site config issues
        foreach ($data['sites'] ?? [] as $site) {
            foreach ($site['issues'] ?? [] as $issue) {
                if (($issue['severity'] ?? null) === $severity) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Build human-readable summary.
     */
    private function buildSummary(array $data): string
    {
        $lines = [];
        $lines[] = '🌐 Nginx Configuration Analysis';
        $lines[] = '';

        if (! $data['nginx_detected']) {
            $lines[] = '❌ Nginx not detected or configuration not accessible';
            $lines[] = '';

            if (! empty($data['recommendations'])) {
                $lines[] = '💡 Notes:';
                foreach ($data['recommendations'] as $rec) {
                    $lines[] = "  • {$rec['message']}";
                    if (isset($rec['fix'])) {
                        $lines[] = "    → {$rec['fix']}";
                    }
                }
            }

            return implode("\n", $lines);
        }

        $lines[] = '✅ Nginx configuration found';
        $lines[] = "📁 Config Path: {$data['config_path']}";
        $lines[] = '';

        // Critical issues
        $criticalIssues = array_filter($data['issues'] ?? [], fn ($i) => ($i['severity'] ?? null) === 'critical');
        if (! empty($criticalIssues)) {
            $lines[] = '🚨 CRITICAL ISSUES ('.count($criticalIssues).'):';
            foreach ($criticalIssues as $issue) {
                $lines[] = "  • {$issue['message']}";
                if (isset($issue['fix'])) {
                    $lines[] = "    Fix: {$issue['fix']}";
                }
            }
            $lines[] = '';
        }

        // Warnings
        $warnings = array_filter($data['issues'] ?? [], fn ($i) => ($i['severity'] ?? null) === 'warning');
        if (! empty($warnings)) {
            $lines[] = '⚠️ WARNINGS ('.count($warnings).'):';
            foreach ($warnings as $warning) {
                $lines[] = "  • {$warning['message']}";
                if (isset($warning['fix'])) {
                    $lines[] = "    Fix: {$warning['fix']}";
                }
            }
            $lines[] = '';
        }

        // Good practices
        if (! empty($data['good_practices'])) {
            $lines[] = '✅ GOOD PRACTICES ('.count($data['good_practices']).'):';
            foreach (array_slice($data['good_practices'], 0, 5) as $practice) {
                $message = is_array($practice) ? $practice['message'] : $practice;
                $lines[] = "  • $message";
            }
            if (count($data['good_practices']) > 5) {
                $lines[] = '  ... and '.(count($data['good_practices']) - 5).' more';
            }
            $lines[] = '';
        }

        // Performance settings
        if (! empty($data['performance'])) {
            $lines[] = '🚀 PERFORMANCE SETTINGS:';
            foreach ($data['performance'] as $perf) {
                $lines[] = "  • {$perf['setting']}: {$perf['value']}";
            }
            $lines[] = '';
        }

        // Recommendations
        if (! empty($data['recommendations'])) {
            $lines[] = '💡 RECOMMENDATIONS ('.count($data['recommendations']).'):';
            foreach (array_slice($data['recommendations'], 0, 8) as $rec) {
                if (is_array($rec)) {
                    $category = isset($rec['category']) ? "[{$rec['category']}] " : '';
                    $lines[] = "  • $category{$rec['message']}";
                    if (isset($rec['fix'])) {
                        $lines[] = "    → {$rec['fix']}";
                    }
                } else {
                    $lines[] = "  • $rec";
                }
            }
            if (count($data['recommendations']) > 8) {
                $lines[] = '  ... and '.(count($data['recommendations']) - 8).' more';
            }
            $lines[] = '';
        }

        // Site configurations
        if (! empty($data['sites'])) {
            $lines[] = '🔧 SITE CONFIGURATIONS ('.count($data['sites']).'):';
            $lines[] = '';

            foreach (array_slice($data['sites'], 0, 3) as $site) {
                $serverName = $site['server_name'] ?? 'unknown';
                $filename = $site['filename'] ?? 'unknown';
                $lines[] = "  📄 $filename";
                if ($serverName !== 'unknown') {
                    $lines[] = "     Server: $serverName";
                }

                $sslStatus = ($site['ssl_enabled'] ?? false) ? '✅' : '❌';
                $phpStatus = ($site['php_fpm_detected'] ?? false) ? '✅' : '❌';
                $lines[] = "     SSL: $sslStatus  PHP-FPM: $phpStatus";

                // Site-specific issues
                $siteIssues = array_filter($site['issues'] ?? [], fn ($i) => ($i['severity'] ?? null) === 'critical');
                if (! empty($siteIssues)) {
                    foreach ($siteIssues as $issue) {
                        $msg = is_array($issue) ? $issue['message'] : $issue;
                        $lines[] = "     🚨 $msg";
                    }
                }

                // Site good practices (limit to 2)
                $siteGoodPractices = array_slice($site['good_practices'] ?? [], 0, 2);
                if (! empty($siteGoodPractices)) {
                    foreach ($siteGoodPractices as $practice) {
                        $lines[] = "     ✅ $practice";
                    }
                }

                $lines[] = '';
            }

            if (count($data['sites']) > 3) {
                $lines[] = '  ... and '.(count($data['sites']) - 3).' more site(s)';
                $lines[] = '';
            }
        }

        // Summary stats
        $lines[] = '📊 Summary:';
        $lines[] = '  • Total Sites: '.count($data['sites'] ?? []);
        $lines[] = '  • Critical Issues: '.$this->countSeverity($data, 'critical');
        $lines[] = '  • Warnings: '.$this->countSeverity($data, 'warning');
        $lines[] = '  • Good Practices: '.count($data['good_practices'] ?? []);
        $lines[] = '  • Recommendations: '.count($data['recommendations'] ?? []);

        if (isset($data['http2_detected']) && $data['http2_detected']) {
            $lines[] = '  • HTTP/2: ✅ Enabled';
        }

        return implode("\n", $lines);
    }
}
