<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Install\CodeEnvironment;

use Skylence\OptimizeMcp\Contracts\McpClient;
use Skylence\OptimizeMcp\Install\Enums\McpInstallationStrategy;
use Skylence\OptimizeMcp\Install\Enums\Platform;

class PhpStorm extends CodeEnvironment implements McpClient
{
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
                    '~/.local/share/JetBrains/PhpStorm',
                    '/opt/phpstorm',
                ],
            ],
            Platform::Windows => [
                'paths' => [
                    '%ProgramFiles%\\JetBrains\\PhpStorm',
                    '%LOCALAPPDATA%\\JetBrains\\PhpStorm',
                ],
            ],
        };
    }

    public function projectDetectionConfig(): array
    {
        return [
            'paths' => ['.idea'],
        ];
    }

    public function mcpInstallationStrategy(): McpInstallationStrategy
    {
        return McpInstallationStrategy::SHELL;
    }

    public function shellMcpCommand(): string
    {
        return 'mcp add -s local -t stdio {key} {command} {args}';
    }
}
