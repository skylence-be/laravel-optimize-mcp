<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Mcp\Tools\Analyzers\Config;

abstract class AbstractConfigAnalyzer
{
    /**
     * Analyze and populate issues and recommendations.
     */
    abstract public function analyze(array &$issues, array &$recommendations, string $environment): void;
}
