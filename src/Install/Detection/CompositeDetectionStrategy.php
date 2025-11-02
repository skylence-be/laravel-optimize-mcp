<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Install\Detection;

use Skylence\OptimizeMcp\Install\Contracts\DetectionStrategy;
use Skylence\OptimizeMcp\Install\Enums\Platform;

class CompositeDetectionStrategy implements DetectionStrategy
{
    /**
     * @param  DetectionStrategy[]  $strategies
     */
    public function __construct(private readonly array $strategies) {}

    public function detect(array $config, ?Platform $platform = null): bool
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->detect($config, $platform)) {
                return true;
            }
        }

        return false;
    }
}
