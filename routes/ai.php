<?php

use Laravel\Mcp\Facades\Mcp;
use Skylence\OptimizeMcp\Mcp\Servers\OptimizeServer;

// Register the Optimize MCP server
Mcp::local('optimize', OptimizeServer::class);
