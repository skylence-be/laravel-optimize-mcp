<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Install\Detection;

use Illuminate\Support\Facades\Process;
use Skylence\OptimizeMcp\Install\Contracts\DetectionStrategy;
use Skylence\OptimizeMcp\Install\Enums\Platform;

class CommandDetectionStrategy implements DetectionStrategy
{
    public function detect(array $config, ?Platform $platform = null): bool
    {
        if (! isset($config['command'])) {
            return false;
        }

        try {
            return Process::timeout(3)->run($config['command'])->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
}
