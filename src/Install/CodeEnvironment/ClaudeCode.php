<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Install\CodeEnvironment;

use Skylence\OptimizeMcp\Contracts\McpClient;
use Skylence\OptimizeMcp\Install\Enums\Platform;

class ClaudeCode extends CodeEnvironment implements McpClient
{
    public function name(): string
    {
        return 'claude_code';
    }

    public function displayName(): string
    {
        return 'Claude Code';
    }

    public function systemDetectionConfig(Platform $platform): array
    {
        return match ($platform) {
            Platform::Darwin, Platform::Linux => [
                'command' => 'command -v claude',
            ],
            Platform::Windows => [
                'command' => 'where claude',
            ],
        };
    }

    public function projectDetectionConfig(): array
    {
        return [
            'paths' => ['.claude'],
            'files' => ['CLAUDE.md'],
        ];
    }

    public function mcpConfigPath(): string
    {
        return '.mcp.json';
    }
}
