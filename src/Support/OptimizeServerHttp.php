<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Support;

use DirectoryIterator;
use Laravel\Mcp\Server\Tool;

/**
 * HTTP wrapper for the Optimize MCP Server.
 * Provides HTTP-accessible methods for tools and manifest.
 */
final class OptimizeServerHttp
{
    /**
     * Registered tools.
     *
     * @var array<string, Tool>
     */
    private array $tools = [];

    /**
     * Create a new OptimizeServerHttp instance.
     */
    public function __construct()
    {
        $this->discoverAndRegisterTools();
    }

    /**
     * Discover and register all tools.
     */
    private function discoverAndRegisterTools(): void
    {
        $toolClasses = $this->discoverTools();

        foreach ($toolClasses as $toolClass) {
            $tool = app($toolClass);
            $this->tools[$tool->name()] = $tool;
        }
    }

    /**
     * Discover tools in the Tools directory.
     *
     * @return array<int, class-string<Tool>>
     */
    private function discoverTools(): array
    {
        $tools = [];
        $toolDir = new DirectoryIterator(__DIR__.'/../Mcp/Tools');

        foreach ($toolDir as $toolFile) {
            if ($toolFile->isFile() && $toolFile->getExtension() === 'php') {
                $fqdn = 'Skylence\\OptimizeMcp\\Mcp\\Tools\\'.$toolFile->getBasename('.php');
                if (class_exists($fqdn)) {
                    $tools[] = $fqdn;
                }
            }
        }

        // Allow configuration-based tool inclusion/exclusion
        $configuredTools = config('optimize-mcp.tools', []);
        foreach ($configuredTools as $toolName => $enabled) {
            if (! $enabled) {
                $toolClass = 'Skylence\\OptimizeMcp\\Mcp\\Tools\\'.ucfirst($toolName);
                $tools = array_filter($tools, fn ($tool) => $tool !== $toolClass);
            }
        }

        return $tools;
    }

    /**
     * Get the server manifest.
     */
    public function getManifest(): array
    {
        return [
            'name' => config('optimize-mcp.server.name', 'Laravel Optimize'),
            'version' => config('optimize-mcp.server.version', '1.0.0'),
            'description' => 'Laravel Optimize MCP server providing optimization tools and utilities for AI-assisted development.',
        ];
    }

    /**
     * Get all registered tools (for tools/list).
     */
    public function getTools(): array
    {
        $tools = [];

        foreach ($this->tools as $tool) {
            $toolArray = $tool->toArray();
            $tools[] = [
                'name' => $toolArray['name'],
                'description' => $toolArray['description'] ?? '',
                'inputSchema' => $toolArray['inputSchema'] ?? [
                    'type' => 'object',
                    'properties' => [],
                ],
            ];
        }

        return $tools;
    }

    /**
     * Execute a tool by name.
     */
    public function executeTool(string $toolName, array $params = []): array
    {
        if (! isset($this->tools[$toolName])) {
            throw new \InvalidArgumentException("Tool '{$toolName}' not found");
        }

        $tool = $this->tools[$toolName];

        // Create a Laravel MCP Request
        $request = new \Laravel\Mcp\Request($params);

        // Execute the tool
        $response = $tool->handle($request);

        // Format the response
        if ($response instanceof \Laravel\Mcp\Response) {
            $content = $response->content();

            // Handle different content types
            if ($content instanceof \Laravel\Mcp\Content\Json) {
                return [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => json_encode($content->data),
                        ],
                    ],
                ];
            }

            if ($content instanceof \Laravel\Mcp\Content\Text) {
                return [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $content->text,
                        ],
                    ],
                ];
            }

            // Fallback for other content types
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => (string) $content,
                    ],
                ],
            ];
        }

        return (array) $response;
    }

    /**
     * Check if a tool exists.
     */
    public function hasTool(string $toolName): bool
    {
        return isset($this->tools[$toolName]);
    }
}
