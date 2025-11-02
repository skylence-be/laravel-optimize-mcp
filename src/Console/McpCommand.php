<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('optimize-mcp:mcp', 'Starts Laravel Optimize MCP (usually from mcp.json)')]
class McpCommand extends Command
{
    public function handle(): int
    {
        return Artisan::call('mcp:start optimize');
    }
}
