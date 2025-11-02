<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Support;

use Illuminate\Support\Facades\Log;

final class Logger
{
    /**
     * Create a new logger instance.
     */
    public function __construct(
        private readonly bool $enabled = true,
        private readonly string $channel = 'stack'
    ) {
    }

    /**
     * Log an info message.
     */
    public function info(string $message, array $context = []): void
    {
        if (! $this->enabled) {
            return;
        }

        Log::channel($this->channel)->info($message, $context);
    }

    /**
     * Log a debug message.
     */
    public function debug(string $message, array $context = []): void
    {
        if (! $this->enabled) {
            return;
        }

        Log::channel($this->channel)->debug($message, $context);
    }

    /**
     * Log an error message.
     */
    public function error(string $message, array $context = []): void
    {
        if (! $this->enabled) {
            return;
        }

        Log::channel($this->channel)->error($message, $context);
    }

    /**
     * Log a warning message.
     */
    public function warning(string $message, array $context = []): void
    {
        if (! $this->enabled) {
            return;
        }

        Log::channel($this->channel)->warning($message, $context);
    }
}
