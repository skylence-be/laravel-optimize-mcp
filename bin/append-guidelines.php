#!/usr/bin/env php
<?php

/**
 * Append Laravel Optimize MCP guidelines to LLM instruction files
 *
 * This script appends optimize-mcp guidelines to existing CLAUDE.md, .cursorrules,
 * or other LLM instruction files. If the file doesn't exist, it creates a minimal
 * file with just the guidelines.
 *
 * Usage:
 *   php bin/append-guidelines.php [target-file]
 *
 * Examples:
 *   php bin/append-guidelines.php CLAUDE.md
 *   php bin/append-guidelines.php .cursorrules
 *   php bin/append-guidelines.php  (defaults to CLAUDE.md)
 */

$targetFile = $argv[1] ?? 'CLAUDE.md';
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

// Check if target file exists
if (file_exists($targetFile)) {
    echo "📄 Found existing file: {$targetFile}\n";

    // Read existing content
    $existingContent = file_get_contents($targetFile);

    // Check if guidelines are already present
    if (strpos($existingContent, '<laravel-optimize-mcp-guidelines>') !== false) {
        echo "✅ Guidelines already present in {$targetFile}\n";
        echo "💡 To update, remove the <laravel-optimize-mcp-guidelines> section and run this script again.\n";
        exit(0);
    }

    // Append guidelines
    $newContent = $existingContent . $wrappedGuidelines;
    file_put_contents($targetFile, $newContent);

    echo "✅ Guidelines appended to {$targetFile}\n";
} else {
    echo "📝 Creating new file: {$targetFile}\n";

    // Create minimal file with guidelines
    $minimalContent = <<<CONTENT
# Laravel Optimize MCP - Guidelines

This project uses Laravel Optimize MCP for analyzing, inspecting, and optimizing the Laravel application.

{$wrappedGuidelines}
CONTENT;

    file_put_contents($targetFile, $minimalContent);

    echo "✅ Created {$targetFile} with guidelines\n";
}

echo "\n";
echo "🎉 Done!\n";
echo "\n";
echo "📖 Guidelines include:\n";
echo "   - Configuration analyzer\n";
echo "   - Database size inspector (HTTP MCP only)\n";
echo "   - Log file inspector (HTTP MCP only)\n";
echo "   - Nginx config inspector & generator (HTTP MCP only)\n";
echo "   - Project structure analyzer (stdio/PHP MCP only)\n";
echo "   - Package advisor (stdio/PHP MCP only)\n";
echo "\n";
