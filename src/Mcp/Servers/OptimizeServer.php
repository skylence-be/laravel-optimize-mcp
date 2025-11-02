<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Mcp\Servers;

use DirectoryIterator;
use Laravel\Mcp\Server;

class OptimizeServer extends Server
{
    /**
     * The MCP server's name.
     */
    protected string $name = 'Laravel Optimize';

    /**
     * The MCP server's version.
     */
    protected string $version = '1.0.0';

    /**
     * The MCP server's instructions for the LLM.
     */
    protected string $instructions = 'Laravel Optimize MCP server providing optimization tools and utilities for AI-assisted development.';

    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [];

    /**
     * The resources registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Resource>>
     */
    protected array $resources = [];

    /**
     * The prompts registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Prompt>>
     */
    protected array $prompts = [];

    /**
     * Bootstrap the server.
     */
    protected function boot(): void
    {
        collect($this->discoverTools())->each(fn (string $tool): string => $this->tools[] = $tool);
    }

    /**
     * Discover tools in the Tools directory.
     *
     * @return array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected function discoverTools(): array
    {
        $tools = [];
        $toolDir = new DirectoryIterator(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'Tools');

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
}
