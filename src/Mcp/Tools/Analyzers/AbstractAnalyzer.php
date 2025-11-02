<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Mcp\Tools\Analyzers;

abstract class AbstractAnalyzer
{
    /**
     * Analyze and populate issues, recommendations, and good practices.
     */
    abstract public function analyze(array &$issues, array &$recommendations, array &$goodPractices): void;
}
