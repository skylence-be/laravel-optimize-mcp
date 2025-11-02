<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Mcp\Tools\Analyzers;

use Illuminate\Support\Facades\File;

final class TestingSetupAnalyzer extends AbstractAnalyzer
{
    public function analyze(array &$issues, array &$recommendations, array &$goodPractices): void
    {
        $composerPath = base_path('composer.json');

        if (!File::exists($composerPath)) {
            return;
        }

        $composerData = json_decode(File::get($composerPath), true);
        $devPackages = array_keys($composerData['require-dev'] ?? []);

        // Check for Pest v4
        $hasPest = in_array('pestphp/pest', $devPackages) ||
                  in_array('pestphp/pest-plugin-laravel', $devPackages);

        if ($hasPest) {
            // Check for Pest plugins
            $hasBrowserPlugin = in_array('pestphp/pest-plugin-browser', $devPackages);

            if (!$hasBrowserPlugin) {
                $recommendations[] = [
                    'category' => 'testing',
                    'type' => 'enhancement',
                    'message' => 'Add pestphp/pest-plugin-browser for E2E browser testing',
                    'benefit' => 'Pest v4 browser testing provides elegant syntax for Playwright-based tests (requires Chrome/Chromium)',
                ];
            } else {
                $goodPractices[] = [
                    'category' => 'testing',
                    'message' => 'Pest v4 with browser testing configured',
                    'details' => 'Full-stack testing with Playwright integration',
                ];
            }
        } else {
            $recommendations[] = [
                'category' => 'testing',
                'type' => 'missing_tool',
                'message' => 'Install Pest v4 for modern testing framework',
                'benefit' => 'Pest provides elegant syntax, type coverage, and browser testing support',
            ];
        }

        // Check for nunomaduro/essentials
        $hasEssentials = in_array('nunomaduro/essentials', $devPackages);

        if (!$hasEssentials) {
            $recommendations[] = [
                'category' => 'tooling',
                'type' => 'productivity',
                'message' => 'Install nunomaduro/essentials for instant config generation',
                'benefit' => 'Run "php artisan essentials:pint --force" and "php artisan essentials:rector --force" to generate optimized configs',
            ];
        } else {
            $goodPractices[] = [
                'category' => 'tooling',
                'message' => 'Nunomaduro Essentials installed',
                'details' => 'Easy config generation for Pint and Rector',
            ];
        }
    }
}
