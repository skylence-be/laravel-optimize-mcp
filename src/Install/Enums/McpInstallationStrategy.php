<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Install\Enums;

enum McpInstallationStrategy: string
{
    case FILE = 'file';
    case SHELL = 'shell';
    case NONE = 'none';
}
