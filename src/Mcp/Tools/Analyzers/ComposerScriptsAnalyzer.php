<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Mcp\Tools\Analyzers;

use Illuminate\Support\Facades\File;

final class ComposerScriptsAnalyzer extends AbstractAnalyzer
{
    public function analyze(array &$issues, array &$recommendations, array &$goodPractices): void
    {
        $composerPath = base_path('composer.json');

        if (!File::exists($composerPath)) {
            $issues[] = [
                'severity' => 'critical',
                'category' => 'configuration',
                'file' => 'composer.json',
                'message' => 'composer.json not found in project root',
            ];

            return;
        }

        $composerData = json_decode(File::get($composerPath), true);
        $scripts = $composerData['scripts'] ?? [];

        // Recommended test scripts
        $recommendedScripts = [
            'test' => 'Main test suite runner',
            'test:unit' => 'Run unit tests',
            'test:lint' => 'Code style check',
            'test:types' => 'Static analysis',
            'test:type-coverage' => 'Pest type coverage check',
            'test:rector' => 'Rector dry-run check',
            'lint' => 'Fix code style',
            'rector' => 'Run rector fixes',
        ];

        $missingScripts = [];
        foreach ($recommendedScripts as $script => $description) {
            if (!isset($scripts[$script])) {
                $missingScripts[] = $script;
            }
        }

        if (!empty($missingScripts)) {
            $recommendations[] = [
                'category' => 'composer',
                'type' => 'missing_scripts',
                'message' => 'Add recommended test and quality scripts: ' . implode(', ', $missingScripts),
                'benefit' => 'Standardizes development workflow and enables easy CI/CD integration',
            ];
        } else {
            $goodPractices[] = [
                'category' => 'composer',
                'message' => 'All recommended test scripts are configured',
                'details' => 'Good practice: standardized composer scripts for testing and quality checks',
            ];
        }

        // Check for proper test script composition
        if (isset($scripts['test']) && is_array($scripts['test'])) {
            $testSteps = $scripts['test'];

            $hasTypeChecker = false;
            $hasQualityCheck = false;
            $hasUnitTests = false;

            foreach ($testSteps as $step) {
                if (str_contains($step, 'type-coverage') || str_contains($step, 'phpstan')) {
                    $hasTypeChecker = true;
                }
                if (str_contains($step, 'rector') || str_contains($step, 'pint')) {
                    $hasQualityCheck = true;
                }
                if (str_contains($step, 'pest') && !str_contains($step, 'type-coverage')) {
                    $hasUnitTests = true;
                }
            }

            if ($hasTypeChecker && $hasQualityCheck && $hasUnitTests) {
                $goodPractices[] = [
                    'category' => 'composer',
                    'message' => 'Test script includes comprehensive checks: types, quality, and unit tests',
                    'details' => 'Excellent multi-step test pipeline',
                ];
            }
        }

        // Detect unnecessary/legacy scripts
        $unnecessaryPatterns = [
            'post-install-cmd' => 'Often not needed in modern Laravel',
            'pre-install-cmd' => 'Rarely necessary',
            'pre-update-cmd' => 'Often causes issues with composer updates',
        ];

        foreach ($unnecessaryPatterns as $pattern => $reason) {
            if (isset($scripts[$pattern])) {
                $recommendations[] = [
                    'category' => 'composer',
                    'type' => 'unnecessary_script',
                    'message' => "Consider removing '{$pattern}' script",
                    'benefit' => $reason,
                ];
            }
        }

        // Check for dev script (concurrent development)
        if (isset($scripts['dev'])) {
            $goodPractices[] = [
                'category' => 'composer',
                'message' => 'Development script configured for concurrent processes',
                'details' => 'Good practice: single command to run server, queue, and vite',
            ];
        }
    }
}
