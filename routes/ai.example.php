<?php

use Laravel\Mcp\Facades\Mcp;
use Skylence\OptimizeMcp\Mcp\Servers\OptimizeServer;

/*
|--------------------------------------------------------------------------
| AI Routes
|--------------------------------------------------------------------------
|
| Here is where you can register MCP servers for your application.
| These servers will be available to AI clients through the Model
| Context Protocol.
|
*/

// Local MCP server (for CLI usage)
Mcp::local('optimize', OptimizeServer::class);

// Web MCP server (for HTTP access)
// Uncomment and configure authentication middleware as needed
// Mcp::web('/mcp/optimize', OptimizeServer::class)
//     ->middleware(['auth:sanctum']);
