<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Mcp\Tools\Analyzers;

use Illuminate\Support\Facades\File;

final class DeploymentProcessAnalyzer extends AbstractAnalyzer
{
    public function analyze(array &$issues, array &$recommendations, array &$goodPractices): void
    {
        $composerPath = base_path('composer.json');

        if (!File::exists($composerPath)) {
            return;
        }

        $composerData = json_decode(File::get($composerPath), true);
        $packages = array_merge(
            array_keys($composerData['require'] ?? []),
            array_keys($composerData['require-dev'] ?? [])
        );

        // Check for deployer/deployer
        $hasDeployer = in_array('deployer/deployer', $packages);
        $deployConfigExists = File::exists(base_path('deploy.php'));

        if ($hasDeployer && $deployConfigExists) {
            // Deployer is properly configured
            $goodPractices[] = [
                'category' => 'deployment',
                'message' => 'Deployer configured for automated deployments',
                'details' => 'Zero-downtime deployment with deploy.php configuration',
            ];

            // Check deploy.php content for best practices
            $deployConfig = File::get(base_path('deploy.php'));

            if (str_contains($deployConfig, 'laravel')) {
                $goodPractices[] = [
                    'category' => 'deployment',
                    'message' => 'Deployer using Laravel recipe',
                    'details' => 'Optimized deployment workflow for Laravel applications',
                ];
            }

            // Check for key features in deploy.php
            $features = [];
            if (str_contains($deployConfig, 'artisan:migrate')) {
                $features[] = 'migrations';
            }
            if (str_contains($deployConfig, 'artisan:optimize') || str_contains($deployConfig, 'artisan:config:cache')) {
                $features[] = 'cache optimization';
            }
            if (str_contains($deployConfig, 'npm') || str_contains($deployConfig, 'pnpm') || str_contains($deployConfig, 'build')) {
                $features[] = 'asset building';
            }
            if (str_contains($deployConfig, 'rollback')) {
                $features[] = 'rollback support';
            }
            if (str_contains($deployConfig, 'queue:restart')) {
                $features[] = 'queue worker restart';
            }

            if (!empty($features)) {
                $goodPractices[] = [
                    'category' => 'deployment',
                    'message' => 'Comprehensive deployment tasks: ' . implode(', ', $features),
                    'details' => 'Well-configured deployment process',
                ];
            }
        } elseif ($hasDeployer && !$deployConfigExists) {
            // Deployer installed but not configured
            $issues[] = [
                'severity' => 'warning',
                'category' => 'deployment',
                'file' => 'deploy.php',
                'message' => 'deployer/deployer installed but no deploy.php configuration found',
                'fix' => 'Run: vendor/bin/dep init to create deploy.php configuration',
            ];
        } elseif (!$hasDeployer && $deployConfigExists) {
            // deploy.php exists but Deployer not installed
            $issues[] = [
                'severity' => 'warning',
                'category' => 'deployment',
                'file' => 'deploy.php',
                'message' => 'deploy.php found but deployer/deployer package not installed',
                'fix' => 'Run: composer require --dev deployer/deployer',
            ];
        } else {
            // No deployment process detected
            $recommendations[] = [
                'category' => 'deployment',
                'type' => 'automation',
                'message' => 'No deployment process detected - install deployer/deployer for zero-downtime deployments',
                'benefit' => 'Automated, repeatable deployments with rollback support, migrations, cache optimization, and asset building',
            ];

            $recommendations[] = [
                'category' => 'deployment',
                'type' => 'setup-guide',
                'message' => 'Install Deployer: composer require --dev deployer/deployer && vendor/bin/dep init',
                'benefit' => 'Zero-downtime deployments with Laravel recipe - handles migrations, cache, queue workers, and assets automatically',
            ];

            $recommendations[] = [
                'category' => 'deployment',
                'type' => 'best-practices',
                'message' => 'Recommended deploy.php features: Laravel recipe, asset building (pnpm/npm), migrations, cache optimization, queue restart, rollback support',
                'benefit' => 'Comprehensive deployment workflow ensures consistent production deployments with minimal downtime',
            ];
        }

        // Check for deployment alternatives
        $hasEnvoyer = str_contains(strtolower(File::get($composerPath)), 'envoyer');
        $hasForge = File::exists(base_path('.forge'));
        $hasVapor = in_array('laravel/vapor-cli', $packages) || in_array('laravel/vapor-core', $packages);

        if ($hasEnvoyer) {
            $goodPractices[] = [
                'category' => 'deployment',
                'message' => 'Laravel Envoyer detected (SaaS deployment platform)',
                'details' => 'Managed zero-downtime deployments with health checks and notifications',
            ];
        }

        if ($hasForge) {
            $goodPractices[] = [
                'category' => 'deployment',
                'message' => 'Laravel Forge configuration detected',
                'details' => 'Server management and deployment automation via Forge',
            ];
        }

        if ($hasVapor) {
            $goodPractices[] = [
                'category' => 'deployment',
                'message' => 'Laravel Vapor configured (serverless deployment)',
                'details' => 'AWS-based serverless deployment with auto-scaling',
            ];
        }

        // If no deployment solution exists at all, emphasize the recommendation
        if (!$hasDeployer && !$hasEnvoyer && !$hasForge && !$hasVapor && !$deployConfigExists) {
            $recommendations[] = [
                'category' => 'deployment',
                'type' => 'critical-gap',
                'message' => 'No automated deployment solution detected - this is essential for production applications',
                'benefit' => 'Manual deployments are error-prone and risky. Deployer provides free, open-source automation with zero-downtime deployment',
            ];
        }
    }
}
