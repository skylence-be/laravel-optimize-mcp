#!/usr/bin/env php
<?php

/**
 * Append Laravel Optimize MCP guidelines to LLM instruction files
 *
 * This script appends optimize-mcp guidelines to LLM instruction files.
 * If no files are specified, it will prompt you to select which files to update.
 *
 * Usage:
 *   php bin/append-guidelines.php                    (interactive mode)
 *   php bin/append-guidelines.php CLAUDE.md          (single file)
 *   php bin/append-guidelines.php CLAUDE.md .cursorrules  (multiple files)
 */

// Check if running in Laravel context
$isLaravel = file_exists('artisan') && file_exists('vendor/autoload.php');

if ($isLaravel) {
    // Bootstrap Laravel for prompts
    require 'vendor/autoload.php';
    $app = require_once 'bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
}

$guidelinesFile = __DIR__ . '/../.ai/optimize-mcp-guidelines.md';

// Check if guidelines file exists
if (!file_exists($guidelinesFile)) {
    echo "❌ Error: Guidelines file not found at: {$guidelinesFile}\n";
    exit(1);
}

// Read guidelines content
$guidelines = file_get_contents($guidelinesFile);

// Wrap guidelines in XML-style tags for easy identification
$wrappedGuidelines = <<<GUIDELINES


---

<laravel-optimize-mcp-guidelines>
{$guidelines}
</laravel-optimize-mcp-guidelines>

GUIDELINES;

// Detect possible LLM instruction files
$possibleFiles = [
    'CLAUDE.md' => 'Claude Code / Claude.ai',
    '.cursorrules' => 'Cursor IDE',
    '.copilot-instructions.md' => 'GitHub Copilot',
    '.aider.conf.yml' => 'Aider',
    '.windsurf/rules.md' => 'Windsurf IDE',
];

// Check which files exist
$existingFiles = [];
foreach ($possibleFiles as $file => $description) {
    if (file_exists($file)) {
        $existingFiles[$file] = $description;
    }
}

// If arguments provided, use those files
if ($argc > 1) {
    $targetFiles = array_slice($argv, 1);
} else {
    // Interactive mode
    if (empty($existingFiles) && $isLaravel) {
        // Prompt to create
        echo "No LLM instruction files found. Which would you like to create?\n\n";

        if (function_exists('Laravel\Prompts\multiselect')) {
            $selected = \Laravel\Prompts\multiselect(
                label: 'Select LLM instruction files to create with guidelines:',
                options: $possibleFiles,
                hint: 'Select one or more files to create'
            );
            $targetFiles = $selected;
        } else {
            // Fallback to simple selection
            echo "Available options:\n";
            $i = 1;
            $fileList = [];
            foreach ($possibleFiles as $file => $description) {
                echo "  [{$i}] {$file} - {$description}\n";
                $fileList[$i] = $file;
                $i++;
            }
            echo "\nEnter numbers separated by commas (e.g., 1,2,3): ";
            $input = trim(fgets(STDIN));
            $selections = explode(',', $input);
            $targetFiles = array_map(fn($num) => $fileList[trim($num)] ?? null, $selections);
            $targetFiles = array_filter($targetFiles);
        }
    } elseif (!empty($existingFiles) && $isLaravel) {
        // Prompt to update existing
        echo "Found existing LLM instruction files. Which would you like to update?\n\n";

        if (function_exists('Laravel\Prompts\multiselect')) {
            $selected = \Laravel\Prompts\multiselect(
                label: 'Select files to append guidelines to:',
                options: $existingFiles,
                hint: 'Guidelines will be safely appended (won\'t override existing content)'
            );
            $targetFiles = $selected;
        } else {
            // Fallback
            echo "Existing files:\n";
            $i = 1;
            $fileList = [];
            foreach ($existingFiles as $file => $description) {
                echo "  [{$i}] {$file} - {$description}\n";
                $fileList[$i] = $file;
                $i++;
            }
            echo "\nEnter numbers separated by commas (e.g., 1,2): ";
            $input = trim(fgets(STDIN));
            $selections = explode(',', $input);
            $targetFiles = array_map(fn($num) => $fileList[trim($num)] ?? null, $selections);
            $targetFiles = array_filter($targetFiles);
        }
    } else {
        // No Laravel prompts, default to CLAUDE.md
        $targetFiles = ['CLAUDE.md'];
        echo "No files specified, defaulting to CLAUDE.md\n";
        echo "Usage: php bin/append-guidelines.php [file1] [file2] ...\n\n";
    }
}

if (empty($targetFiles)) {
    echo "❌ No files selected. Exiting.\n";
    exit(0);
}

// Process each file
$updated = [];
$skipped = [];
$created = [];

foreach ($targetFiles as $targetFile) {
    // Skip if not a valid file path
    if (empty($targetFile) || !is_string($targetFile)) {
        continue;
    }

    // Check if file exists
    if (file_exists($targetFile)) {
        // Read existing content
        $existingContent = file_get_contents($targetFile);

        // Check if guidelines are already present
        if (strpos($existingContent, '<laravel-optimize-mcp-guidelines>') !== false) {
            $skipped[] = $targetFile;
            continue;
        }

        // Append guidelines
        $newContent = $existingContent . $wrappedGuidelines;
        file_put_contents($targetFile, $newContent);
        $updated[] = $targetFile;
    } else {
        // Create minimal file with guidelines
        $minimalContent = <<<CONTENT
# Laravel Optimize MCP - Guidelines

This project uses Laravel Optimize MCP for analyzing, inspecting, and optimizing the Laravel application.

{$wrappedGuidelines}
CONTENT;

        // Create directory if needed (e.g., .windsurf/rules.md)
        $dir = dirname($targetFile);
        if ($dir !== '.' && !is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($targetFile, $minimalContent);
        $created[] = $targetFile;
    }
}

// Display results
echo "\n";
echo str_repeat("=", 70) . "\n";
echo "🎉 Done!\n";
echo str_repeat("=", 70) . "\n\n";

if (!empty($created)) {
    echo "✅ Created {count($created)} file(s) with guidelines:\n";
    foreach ($created as $file) {
        $description = $possibleFiles[$file] ?? 'LLM instructions';
        echo "   • {$file} ({$description})\n";
    }
    echo "\n";
}

if (!empty($updated)) {
    echo "✅ Appended guidelines to {count($updated)} existing file(s):\n";
    foreach ($updated as $file) {
        $description = $possibleFiles[$file] ?? 'LLM instructions';
        echo "   • {$file} ({$description})\n";
    }
    echo "\n";
}

if (!empty($skipped)) {
    echo "⏭️  Skipped {count($skipped)} file(s) (guidelines already present):\n";
    foreach ($skipped as $file) {
        echo "   • {$file}\n";
    }
    echo "   💡 To update, remove <laravel-optimize-mcp-guidelines> section and run again\n\n";
}

echo "📖 Guidelines include:\n";
echo "   • Configuration analyzer (all contexts)\n";
echo "   • Database size inspector (HTTP MCP only)\n";
echo "   • Log file inspector (HTTP MCP only)\n";
echo "   • Nginx config inspector & generator (HTTP MCP only)\n";
echo "   • Project structure analyzer (stdio/PHP MCP only)\n";
echo "   • Package advisor (stdio/PHP MCP only)\n";
echo "\n";
