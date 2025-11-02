<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Install\CodeEnvironment;

use Skylence\OptimizeMcp\Contracts\McpClient;
use Skylence\OptimizeMcp\Install\Enums\Platform;

class Cursor extends CodeEnvironment implements McpClient
{
    public function name(): string
    {
        return 'cursor';
    }

    public function displayName(): string
    {
        return 'Cursor';
    }

    public function systemDetectionConfig(Platform $platform): array
    {
        return match ($platform) {
            Platform::Darwin => [
                'paths' => ['/Applications/Cursor.app'],
            ],
            Platform::Linux => [
                'paths' => [
                    '/opt/cursor',
                    '/usr/local/bin/cursor',
                    '~/.local/bin/cursor',
                ],
            ],
            Platform::Windows => [
                'paths' => [
                    '%ProgramFiles%\\Cursor',
                    '%LOCALAPPDATA%\\Programs\\Cursor',
                ],
            ],
        };
    }

    public function projectDetectionConfig(): array
    {
        return [
            'paths' => ['.cursor'],
        ];
    }

    public function mcpConfigPath(): string
    {
        return '.cursor/mcp.json';
    }
}
