<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Install\CodeEnvironment;

use Skylence\OptimizeMcp\Contracts\McpClient;
use Skylence\OptimizeMcp\Install\Enums\Platform;

class PhpStorm extends CodeEnvironment implements McpClient
{
    public bool $useAbsolutePathForMcp = true;

    public function name(): string
    {
        return 'phpstorm';
    }

    public function displayName(): string
    {
        return 'PhpStorm';
    }

    public function systemDetectionConfig(Platform $platform): array
    {
        return match ($platform) {
            Platform::Darwin => [
                'paths' => ['/Applications/PhpStorm.app'],
            ],
            Platform::Linux => [
                'paths' => [
                    '/opt/phpstorm',
                    '/opt/PhpStorm*',
                    '/usr/local/bin/phpstorm',
                    '~/.local/share/JetBrains/Toolbox/apps/PhpStorm/ch-*',
                ],
            ],
            Platform::Windows => [
                'paths' => [
                    '%ProgramFiles%\\JetBrains\\PhpStorm*',
                    '%LOCALAPPDATA%\\JetBrains\\Toolbox\\apps\\PhpStorm\\ch-*',
                    '%LOCALAPPDATA%\\Programs\\PhpStorm',
                ],
            ],
        };
    }

    public function projectDetectionConfig(): array
    {
        return [
            'paths' => ['.idea', '.junie'],
        ];
    }

    public function mcpClientName(): string
    {
        return 'Junie';
    }

    public function mcpConfigPath(): string
    {
        return '.junie/mcp/mcp.json';
    }
}
