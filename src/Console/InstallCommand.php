<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Console;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Skylence\OptimizeMcp\Contracts\McpClient;
use Skylence\OptimizeMcp\Install\CodeEnvironment\CodeEnvironment;
use Skylence\OptimizeMcp\Install\CodeEnvironmentsDetector;
use Skylence\OptimizeMcp\Support\Config;
use Laravel\Prompts\Concerns\Colors;
use Laravel\Prompts\Terminal;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\confirm;
use Skylence\OptimizeMcp\Install\Mcp\FileWriter;

#[AsCommand('optimize-mcp:install', 'Install Laravel Optimize MCP')]
class InstallCommand extends Command
{
    use Colors;

    private CodeEnvironmentsDetector $codeEnvironmentsDetector;

    private Terminal $terminal;

    /** @var Collection<int, McpClient> */
    private Collection $selectedTargetMcpClient;

    /** @var Collection<int, string> */
    private Collection $selectedFeatures;

    private string $projectName;

    private bool $configureHttpServer = false;

    /** @var array<non-empty-string> */
    private array $systemInstalledCodeEnvironments = [];

    private array $projectInstalledCodeEnvironments = [];

    private string $greenTick;

    private string $redCross;

    public function __construct(protected Config $config)
    {
        parent::__construct();
    }

    public function handle(CodeEnvironmentsDetector $codeEnvironmentsDetector, Terminal $terminal): void
    {
        $this->bootstrap($codeEnvironmentsDetector, $terminal);

        $this->displayHeader();
        $this->discoverEnvironment();
        $this->collectInstallationPreferences();
        $this->performInstallation();
        $this->outro();
    }

    protected function bootstrap(CodeEnvironmentsDetector $codeEnvironmentsDetector, Terminal $terminal): void
    {
        $this->codeEnvironmentsDetector = $codeEnvironmentsDetector;
        $this->terminal = $terminal;

        $this->terminal->initDimensions();

        $this->greenTick = $this->green('✓');
        $this->redCross = $this->red('✗');

        $this->selectedTargetMcpClient = collect();

        $this->projectName = config('app.name');
    }

    protected function displayHeader(): void
    {
        note($this->logo());
        intro('Laravel Optimize MCP :: Install');
        note("Let's configure {$this->bgYellow($this->black($this->bold($this->projectName)))} with MCP");
    }

    protected function logo(): string
    {
        return
         <<<'HEADER'
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
        HEADER;
    }

    protected function discoverEnvironment(): void
    {
        $this->systemInstalledCodeEnvironments = $this->codeEnvironmentsDetector->discoverSystemInstalledCodeEnvironments();
        $this->projectInstalledCodeEnvironments = $this->codeEnvironmentsDetector->discoverProjectInstalledCodeEnvironments(base_path());
    }

    protected function collectInstallationPreferences(): void
    {
        $this->selectedFeatures = $this->selectFeatures();
        $this->selectedTargetMcpClient = $this->selectTargetMcpClients();

        if ($this->selectedTargetMcpClient->isNotEmpty()) {
            $this->configureHttpServer = $this->shouldConfigureHttpServer();
        }
    }

    protected function performInstallation(): void
    {
        if ($this->selectedTargetMcpClient->isNotEmpty()) {
            $this->installMcpServerConfig();
        }
    }

    /**
     * @return Collection<int, string>
     */
    protected function selectFeatures(): Collection
    {
        $features = collect(['mcp_server']);

        if ($this->isSailInstalled() && ($this->isRunningInsideSail() || $this->shouldConfigureSail())) {
            $features->push('sail');
        }

        return $features;
    }

    protected function shouldConfigureSail(): bool
    {
        return confirm(
            label: 'Laravel Sail detected. Configure Optimize MCP to use Sail?',
            default: $this->config->getSail(),
            hint: 'This will configure the MCP server to run through Sail. Note: Sail must be running to use Optimize MCP',
        );
    }

    protected function shouldConfigureHttpServer(): bool
    {
        return confirm(
            label: 'Configure HTTP access for remote servers (staging/production)?',
            default: false,
            hint: 'Adds HTTP server config to check remote .env variables and configuration',
        );
    }

    /**
     * @return Collection<int, CodeEnvironment>
     */
    protected function selectTargetMcpClients(): Collection
    {
        return $this->selectCodeEnvironments(
            McpClient::class,
            sprintf('Which code editors do you use to work on %s?', $this->projectName),
            $this->config->getEditors(),
        );
    }

    /**
     * Get configuration settings for contract-specific selection behavior.
     *
     * @return array{required: bool, displayMethod: string}
     */
    protected function getSelectionConfig(string $contractClass): array
    {
        return match ($contractClass) {
            McpClient::class => ['required' => true, 'displayMethod' => 'displayName'],
            default => throw new InvalidArgumentException("Unsupported contract class: {$contractClass}"),
        };
    }

    /**
     * @param  array<int, string>  $defaults
     * @return Collection<int, CodeEnvironment>
     */
    protected function selectCodeEnvironments(string $contractClass, string $label, array $defaults): Collection
    {
        $allEnvironments = $this->codeEnvironmentsDetector->getCodeEnvironments();
        $config = $this->getSelectionConfig($contractClass);

        $availableEnvironments = $allEnvironments->filter(fn (CodeEnvironment $environment): bool => $environment instanceof $contractClass);

        if ($availableEnvironments->isEmpty()) {
            return collect();
        }

        $options = $availableEnvironments->mapWithKeys(function (CodeEnvironment $environment) use ($config): array {
            $displayMethod = $config['displayMethod'];
            $displayText = $environment->{$displayMethod}();

            return [$environment->name() => $displayText];
        })->sort();

        $installedEnvNames = array_unique(array_merge(
            $this->projectInstalledCodeEnvironments,
            $this->systemInstalledCodeEnvironments
        ));

        $detectedDefaults = [];

        if ($defaults === []) {
            foreach ($installedEnvNames as $envKey) {
                $matchingEnv = $availableEnvironments->first(fn (CodeEnvironment $env): bool => strtolower((string) $envKey) === strtolower($env->name()));
                if ($matchingEnv) {
                    $detectedDefaults[] = $matchingEnv->name();
                }
            }
        }

        $selectedCodeEnvironments = collect(multiselect(
            label: $label,
            options: $options->toArray(),
            default: $defaults === [] ? $detectedDefaults : $defaults,
            scroll: $options->count(),
            required: $config['required'],
            hint: $defaults === [] || $detectedDefaults === [] ? '' : sprintf('Auto-detected %s for you',
                Arr::join(array_map(function ($className) use ($availableEnvironments, $config) {
                    $env = $availableEnvironments->first(fn ($env): bool => $env->name() === $className);
                    $displayMethod = $config['displayMethod'];

                    return $env->{$displayMethod}();
                }, $detectedDefaults), ', ', ' & ')
            )
        ))->sort();

        return $selectedCodeEnvironments->map(
            fn (string $name) => $availableEnvironments->first(fn ($env): bool => $env->name() === $name),
        )->filter()->values();
    }

    protected function shouldUseSail(): bool
    {
        return $this->selectedFeatures->contains('sail');
    }

    protected function isSailInstalled(): bool
    {
        return file_exists(base_path('vendor/bin/sail')) &&
               (file_exists(base_path('docker-compose.yml')) || file_exists(base_path('compose.yaml')));
    }

    protected function isRunningInsideSail(): bool
    {
        return get_current_user() === 'sail' || getenv('LARAVEL_SAIL') === '1';
    }

    protected function buildMcpCommand(McpClient $mcpClient): array
    {
        if ($this->shouldUseSail()) {
            return ['laravel-optimize-mcp', './vendor/bin/sail', 'artisan', 'optimize-mcp:mcp'];
        }

        $inWsl = $this->isRunningInWsl();

        return array_filter([
            'laravel-optimize-mcp',
            $inWsl ? 'wsl' : false,
            $mcpClient->getPhpPath($inWsl),
            $mcpClient->getArtisanPath($inWsl),
            'optimize-mcp:mcp',
        ]);
    }

    protected function installMcpServerConfig(): void
    {
        if ($this->selectedTargetMcpClient->isEmpty()) {
            $this->info('No editors selected for MCP installation.');

            return;
        }

        $this->newLine();
        $this->info(' Installing MCP servers to your selected IDEs');
        $this->newLine();

        usleep(750000);

        $failed = [];
        $longestIdeName = max(
            1,
            ...$this->selectedTargetMcpClient->map(
                fn (McpClient $mcpClient) => Str::length($mcpClient->mcpClientName())
            )->toArray()
        );

        /** @var McpClient $mcpClient */
        foreach ($this->selectedTargetMcpClient as $mcpClient) {
            $ideName = $mcpClient->mcpClientName();
            $ideDisplay = str_pad((string) $ideName, $longestIdeName);
            $this->output->write("  {$ideDisplay}... ");

            $mcp = $this->buildMcpCommand($mcpClient);

            try {
                $result = $this->installMcpServers($mcpClient, $mcp);

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

        if ($this->configureHttpServer) {
            $this->displayHttpConfigurationInstructions();
        }

        $this->config->setSail(
            $this->shouldUseSail()
        );

        $this->config->setEditors(
            $this->selectedTargetMcpClient->map(fn (McpClient $mcpClient): string => $mcpClient->name())->values()->toArray()
        );
    }

    /**
     * Install MCP servers (local and optionally HTTP) for the given client.
     *
     * @param  array<int, string>  $mcp
     */
    protected function installMcpServers(McpClient $mcpClient, array $mcp): bool
    {
        $path = $mcpClient->mcpConfigPath();
        if (! $path) {
            return $mcpClient->installMcp(
                array_shift($mcp),
                array_shift($mcp),
                $mcp
            );
        }

        $writer = new FileWriter($path);
        $writer->configKey($mcpClient->mcpConfigKey());

        // Add local PHP stdio server
        $localKey = array_shift($mcp);
        $command = array_shift($mcp);
        $writer->addServer($localKey, $command, $mcp, []);

        // Add HTTP server if configured
        if ($this->configureHttpServer) {
            $writer->addHttpServer(
                'laravel-optimize-mcp-http',
                'https://your-production-url.com/optimize-mcp',
                [
                    'Authorization' => 'Bearer YOUR-TOKEN-HERE',
                ]
            );
        }

        return $writer->save();
    }

    protected function displayHttpConfigurationInstructions(): void
    {
        $token = bin2hex(random_bytes(32));

        note(
            <<<NOTE
            {$this->green('✓')} HTTP server configuration added!

            Next steps to enable remote access:

            1. Add to your production/staging .env file:
               OPTIMIZE_MCP_AUTH_ENABLED=true
               OPTIMIZE_MCP_API_TOKEN={$token}

            2. Update your MCP config file with your production URL:
               Replace "https://your-production-url.com/optimize-mcp"
               with your actual URL (e.g., "https://myapp.com/optimize-mcp")

            3. Add the token to your MCP config headers:
               "headers": { "Authorization": "Bearer {$token}" }
            NOTE
        );
    }

    protected function outro(): void
    {
        $label = 'https://github.com/skylence-be/laravel-optimize-mcp';

        $ideNames = $this->selectedTargetMcpClient->map(fn (McpClient $mcpClient): string => $mcpClient->mcpClientName())
            ->toArray();

        $text = 'Installation complete! 🚀 Documentation: ';
        $paddingLength = (int) (floor(($this->terminal->cols() - mb_strlen($text.$label)) / 2)) - 2;

        $this->output->write([
            "\033[42m\033[2K".str_repeat(' ', max(0, $paddingLength)),
            $this->black($this->bold($text.$this->hyperlink($label, $label))).$this->reset(PHP_EOL).$this->reset(PHP_EOL),
        ]);
    }

    protected function hyperlink(string $label, string $url): string
    {
        return "\033]8;;{$url}\007{$label}\033]8;;\033\\";
    }

    /**
     * Are we running inside a Windows Subsystem for Linux (WSL) environment?
     * This differentiates between a regular Linux installation and a WSL.
     */
    private function isRunningInWsl(): bool
    {
        // Check for WSL-specific environment variables.
        return ! empty(getenv('WSL_DISTRO_NAME')) || ! empty(getenv('IS_WSL'));
    }
}
