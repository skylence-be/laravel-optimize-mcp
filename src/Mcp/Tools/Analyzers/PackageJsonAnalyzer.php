<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Mcp\Tools\Analyzers;

use Illuminate\Support\Facades\File;

final class PackageJsonAnalyzer extends AbstractAnalyzer
{
    public function analyze(array &$issues, array &$recommendations, array &$goodPractices): void
    {
        $packagePath = base_path('package.json');

        if (!File::exists($packagePath)) {
            $recommendations[] = [
                'category' => 'frontend',
                'type' => 'missing_file',
                'message' => 'No package.json found - if using Vite/frontend, create package.json',
                'benefit' => 'Enables modern frontend build tools and testing',
            ];

            return;
        }

        $packageData = json_decode(File::get($packagePath), true);

        if (!$packageData) {
            return;
        }

        $dependencies = array_merge(
            array_keys($packageData['dependencies'] ?? []),
            array_keys($packageData['devDependencies'] ?? [])
        );

        // Check for Playwright
        $hasPlaywright = in_array('playwright', $dependencies) || in_array('@playwright/test', $dependencies);

        if ($hasPlaywright) {
            $playwrightVersion = $packageData['dependencies']['playwright'] ??
                                $packageData['devDependencies']['playwright'] ??
                                $packageData['dependencies']['@playwright/test'] ??
                                $packageData['devDependencies']['@playwright/test'] ??
                                'unknown';

            $goodPractices[] = [
                'category' => 'frontend-testing',
                'message' => "Playwright {$playwrightVersion} installed",
                'details' => 'Modern browser automation for E2E testing - compatible with pest-plugin-browser',
            ];

            // Check if pest-plugin-browser is also installed
            $composerPath = base_path('composer.json');
            if (File::exists($composerPath)) {
                $composerData = json_decode(File::get($composerPath), true);
                $devPackages = array_keys($composerData['require-dev'] ?? []);

                if (!in_array('pestphp/pest-plugin-browser', $devPackages)) {
                    $recommendations[] = [
                        'category' => 'testing',
                        'type' => 'enhancement',
                        'message' => 'Install pestphp/pest-plugin-browser to leverage your existing Playwright setup',
                        'benefit' => 'Pest v4 browser testing with elegant syntax - Playwright already installed!',
                    ];
                }
            }
        } else {
            $recommendations[] = [
                'category' => 'frontend-testing',
                'type' => 'missing_dependency',
                'message' => 'Install Playwright for browser testing (pnpm add -D playwright)',
                'benefit' => 'Enables E2E testing with pest-plugin-browser. Required for full-stack test coverage',
            ];
        }

        // Check for test scripts
        $scripts = $packageData['scripts'] ?? [];

        if (isset($scripts['test']) || isset($scripts['test:e2e'])) {
            $goodPractices[] = [
                'category' => 'frontend-testing',
                'message' => 'Frontend test scripts configured',
                'details' => 'npm/pnpm test scripts for automated testing',
            ];
        }

        // Check for concurrently (dev workflow optimization)
        $hasConcurrently = in_array('concurrently', $dependencies);

        if ($hasConcurrently) {
            $goodPractices[] = [
                'category' => 'dev-workflow',
                'message' => 'Concurrently installed for parallel dev processes',
                'details' => 'Run server, queue, and vite simultaneously',
            ];
        } else {
            $recommendations[] = [
                'category' => 'dev-workflow',
                'type' => 'productivity',
                'message' => 'Install concurrently for better dev workflow (pnpm add -D concurrently)',
                'benefit' => 'Run multiple dev processes (server, queue, vite) in one terminal window',
            ];
        }
    }
}
