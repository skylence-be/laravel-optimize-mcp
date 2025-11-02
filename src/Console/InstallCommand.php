<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Console;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Skylence\OptimizeMcp\Contracts\McpClient;
use Skylence\OptimizeMcp\Install\CodeEnvironment\CodeEnvironment;
use Skylence\OptimizeMcp\Install\CodeEnvironmentsDetector;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
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

    /** @var Collection<int, McpClient> */
    private Collection $selectedTargetMcpClients;

    /** @var array<non-empty-string> */
    private array $systemInstalledCodeEnvironments = [];

    private array $projectInstalledCodeEnvironments = [];

    private string $projectName;

    private string $greenTick;

    private string $redCross;

    /**
     * Execute the console command.
     */
    public function handle(CodeEnvironmentsDetector $codeEnvironmentsDetector): int
    {
        $this->bootstrap($codeEnvironmentsDetector);
        $this->displayHeader();
        $this->publishConfiguration();
        $this->discoverEnvironment($codeEnvironmentsDetector);
        $this->collectInstallationPreferences($codeEnvironmentsDetector);
        $this->installMcpServerConfig();
        $this->displayOutro();

        return self::SUCCESS;
    }

    protected function bootstrap(CodeEnvironmentsDetector $codeEnvironmentsDetector): void
    {
        $this->greenTick = "\033[32m✓\033[0m";
        $this->redCross = "\033[31m✗\033[0m";
        $this->selectedTargetMcpClients = collect();
        $this->projectName = config('app.name');
    }

    protected function discoverEnvironment(CodeEnvironmentsDetector $codeEnvironmentsDetector): void
    {
        $this->newLine();
        $this->components->info('Detecting installed code editors...');

        try {
            $this->systemInstalledCodeEnvironments = $codeEnvironmentsDetector->discoverSystemInstalledCodeEnvironments();
            $this->components->info('System detection complete');
        } catch (\Exception $e) {
            $this->components->warn('System detection failed: '.$e->getMessage());
            $this->systemInstalledCodeEnvironments = [];
        }

        try {
            $this->projectInstalledCodeEnvironments = $codeEnvironmentsDetector->discoverProjectInstalledCodeEnvironments(base_path());
            $this->components->info('Project detection complete');
        } catch (\Exception $e) {
            $this->components->warn('Project detection failed: '.$e->getMessage());
            $this->projectInstalledCodeEnvironments = [];
        }

        $detected = array_unique(array_merge(
            $this->systemInstalledCodeEnvironments,
            $this->projectInstalledCodeEnvironments
        ));

        if (! empty($detected)) {
            $this->components->info('Detected: '.implode(', ', $detected));
        } else {
            $this->components->info('No editors auto-detected');
        }
    }

    protected function collectInstallationPreferences(CodeEnvironmentsDetector $codeEnvironmentsDetector): void
    {
        $this->selectedTargetMcpClients = $this->selectTargetMcpClients($codeEnvironmentsDetector);
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
     * @return Collection<int, CodeEnvironment>
     */
    protected function selectTargetMcpClients(CodeEnvironmentsDetector $codeEnvironmentsDetector): Collection
    {
        $this->components->info('Preparing editor selection...');

        $allEnvironments = $codeEnvironmentsDetector->getCodeEnvironments();
        $this->components->info('Loaded '.count($allEnvironments).' code environments');

        $availableEnvironments = $allEnvironments->filter(fn (CodeEnvironment $environment): bool => $environment instanceof McpClient);
        $this->components->info('Found '.count($availableEnvironments).' MCP clients');

        if ($availableEnvironments->isEmpty()) {
            $this->components->warn('No MCP clients available');
            return collect();
        }

        $options = $availableEnvironments->mapWithKeys(function (CodeEnvironment $environment): array {
            $displayText = $environment->displayName();

            return [$environment->name() => $displayText];
        })->sort();

        $installedEnvNames = array_unique(array_merge(
            $this->projectInstalledCodeEnvironments,
            $this->systemInstalledCodeEnvironments
        ));

        $defaults = config('optimize-mcp.installation.editors', []);
        $detectedDefaults = [];

        if ($defaults === []) {
            foreach ($installedEnvNames as $envKey) {
                $matchingEnv = $availableEnvironments->first(fn (CodeEnvironment $env): bool => strtolower((string) $envKey) === strtolower($env->name()));
                if ($matchingEnv) {
                    $detectedDefaults[] = $matchingEnv->name();
                }
            }
        }

        $this->newLine();
        $this->components->info('Showing editor selection prompt...');
        $this->components->info('Options: '.json_encode($options->toArray()));
        $this->components->info('Detected defaults: '.json_encode($detectedDefaults));
        $this->components->info('Config defaults: '.json_encode($defaults));

        $defaultsToUse = $defaults === [] ? $detectedDefaults : $defaults;
        $this->components->info('Using defaults: '.json_encode($defaultsToUse));

        $selectedCodeEnvironments = collect(multiselect(
            label: sprintf('Which code editors do you use to work on %s?', $this->projectName),
            options: $options->toArray(),
            default: $defaultsToUse,
            scroll: $options->count(),
            required: true,
            hint: $defaults === [] || $detectedDefaults === [] ? '' : sprintf('Auto-detected %s for you',
                Arr::join(array_map(function ($className) use ($availableEnvironments) {
                    $env = $availableEnvironments->first(fn ($env): bool => $env->name() === $className);

                    return $env->displayName();
                }, $detectedDefaults), ', ', ' & ')
            )
        ))->sort();

        return $selectedCodeEnvironments->map(
            fn (string $name) => $availableEnvironments->first(fn ($env): bool => $env->name() === $name),
        )->filter()->values();
    }

    protected function buildMcpCommand(McpClient $mcpClient): array
    {
        return array_filter([
            'laravel-optimize-mcp',
            $mcpClient->getPhpPath(),
            $mcpClient->getArtisanPath(),
            'optimize-mcp:mcp',
        ]);
    }

    protected function installMcpServerConfig(): void
    {
        if ($this->selectedTargetMcpClients->isEmpty()) {
            return;
        }

        $this->newLine();
        $this->info(' Installing MCP servers to your selected IDEs');
        $this->newLine();

        usleep(750000);

        $failed = [];
        $longestIdeName = max(
            1,
            ...$this->selectedTargetMcpClients->map(
                fn (McpClient $mcpClient) => Str::length($mcpClient->mcpClientName())
            )->toArray()
        );

        /** @var McpClient $mcpClient */
        foreach ($this->selectedTargetMcpClients as $mcpClient) {
            $ideName = $mcpClient->mcpClientName();
            $ideDisplay = str_pad((string) $ideName, $longestIdeName);
            $this->output->write("  {$ideDisplay}... ");

            $mcp = $this->buildMcpCommand($mcpClient);

            try {
                $result = $mcpClient->installMcp(
                    array_shift($mcp),
                    array_shift($mcp),
                    $mcp
                );

                if ($result) {
                    $this->line($this->greenTick);
                } else {
                    $this->line($this->redCross);
                    $failed[$ideName] = 'Failed to write configuration';
                }
            } catch (Exception $e) {
                $this->line($this->redCross);
                $failed[$ideName] = $e->getMessage();
            }
        }

        $this->newLine();

        if ($failed !== []) {
            $this->error(sprintf('%s Some MCP servers failed to install:', $this->redCross));

            foreach ($failed as $ideName => $error) {
                $this->line("  - {$ideName}: {$error}");
            }
        }

        // Save preferences to config
        $this->saveInstallationPreferences();
    }

    protected function saveInstallationPreferences(): void
    {
        $configPath = config_path('optimize-mcp.php');

        if (! file_exists($configPath)) {
            return;
        }

        $content = file_get_contents($configPath);

        $editors = $this->selectedTargetMcpClients
            ->map(fn (McpClient $mcpClient): string => $mcpClient->name())
            ->values()
            ->toArray();

        $editorsArrayString = "[\n        '".implode("',\n        '", $editors)."',\n    ]";

        // Replace the editors array in the config file
        $pattern = "/'editors'\s*=>\s*\[[^\]]*\]/";
        $replacement = "'editors' => ".$editorsArrayString;

        $newContent = preg_replace($pattern, $replacement, $content);

        if ($newContent !== null && $newContent !== $content) {
            file_put_contents($configPath, $newContent);
        }
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
            '<fg=green>MCP Server Configuration:</>',
            ''
        );

        if ($this->selectedTargetMcpClients->isNotEmpty()) {
            $installedEditors = $this->selectedTargetMcpClients
                ->map(fn (McpClient $client) => $client->displayName())
                ->toArray();

            $this->components->bulletList([
                'MCP server configured for: '.implode(', ', $installedEditors),
                'Server key: laravel-optimize-mcp',
                'Command: php artisan optimize-mcp:mcp',
            ]);
        } else {
            $this->components->bulletList([
                'PHP stdio server (local): Use key "laravel-optimize-mcp"',
                'HTTP server: Use key "http-laravel-optimize" in .mcp.json',
            ]);
        }

        $this->newLine();
        $this->components->twoColumnDetail(
            '<fg=green>Next steps:</>',
            ''
        );

        $this->components->bulletList([
            'Configure HTTP endpoint: config/optimize-mcp.php',
            'Enable/disable tools in the config file',
            'Run installation again to add more editors',
        ]);

        $this->newLine();
        $this->components->info('For more information, visit: https://github.com/skylence-be/laravel-optimize-mcp');
    }
}
