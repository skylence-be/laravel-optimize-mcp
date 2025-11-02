<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;

#[AsCommand('optimize-mcp:install', 'Install Laravel Optimize MCP')]
class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'optimize-mcp:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install Laravel Optimize MCP';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->displayHeader();
        $this->publishConfiguration();
        $this->displayOutro();

        return self::SUCCESS;
    }

    /**
     * Display the installation header.
     */
    protected function displayHeader(): void
    {
        intro('Laravel Optimize MCP Installation');

        note($this->getLogo());

        $this->newLine();
        $this->components->info('Welcome to Laravel Optimize MCP!');
        $this->components->info('This package provides optimization tools for AI-assisted development.');
        $this->newLine();
    }

    /**
     * Get the package logo.
     */
    protected function getLogo(): string
    {
        return <<<'LOGO'
         ██████╗ ██████╗ ████████╗██╗███╗   ███╗██╗███████╗███████╗
        ██╔═══██╗██╔══██╗╚══██╔══╝██║████╗ ████║██║╚══███╔╝██╔════╝
        ██║   ██║██████╔╝   ██║   ██║██╔████╔██║██║  ███╔╝ █████╗
        ██║   ██║██╔═══╝    ██║   ██║██║╚██╔╝██║██║ ███╔╝  ██╔══╝
        ╚██████╔╝██║        ██║   ██║██║ ╚═╝ ██║██║███████╗███████╗
         ╚═════╝ ╚═╝        ╚═╝   ╚═╝╚═╝     ╚═╝╚═╝╚══════╝╚══════╝

        ███╗   ███╗ ██████╗██████╗
        ████╗ ████║██╔════╝██╔══██╗
        ██╔████╔██║██║     ██████╔╝
        ██║╚██╔╝██║██║     ██╔═══╝
        ██║ ╚═╝ ██║╚██████╗██║
        ╚═╝     ╚═╝ ╚═════╝╚═╝
        LOGO;
    }

    /**
     * Publish the package configuration.
     */
    protected function publishConfiguration(): void
    {
        $this->components->task('Publishing configuration', function () {
            $this->callSilent('vendor:publish', [
                '--tag' => 'optimize-mcp-config',
                '--force' => true,
            ]);
        });

        $this->newLine();
        $this->components->info('Configuration published to config/optimize-mcp.php');
    }

    /**
     * Display the installation outro.
     */
    protected function displayOutro(): void
    {
        $this->newLine();

        outro('Installation complete! 🚀');

        $this->newLine();
        $this->components->twoColumnDetail(
            '<fg=green>Next steps:</>',
            ''
        );

        $this->components->bulletList([
            'Configure your MCP server in routes/ai.php',
            'Register the OptimizeServer in your application',
            'Use the ping tool to test your setup',
        ]);

        $this->newLine();
        $this->components->info('For more information, visit: https://github.com/laravel/optimize-mcp');
    }
}
