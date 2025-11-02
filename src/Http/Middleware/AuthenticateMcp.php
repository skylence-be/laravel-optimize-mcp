<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuthenticateMcp
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        if (! config('optimize-mcp.auth.enabled', true)) {
            return $next($request);
        }

        // Check for API token in header
        $token = $request->header('X-MCP-Token') ?? $request->bearerToken();

        if (! $token) {
            return response()->json([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32001,
                    'message' => 'Authentication required',
                ],
                'id' => $request->input('id'),
            ], 401);
        }

        // Validate token
        if (! $this->isValidToken($token)) {
            return response()->json([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32002,
                    'message' => 'Invalid authentication token',
                ],
                'id' => $request->input('id'),
            ], 403);
        }

        return $next($request);
    }

    /**
     * Validate the authentication token.
     */
    protected function isValidToken(string $token): bool
    {
        // Get token from config (not env) to ensure it works with config caching
        $validToken = config('optimize-mcp.auth.token');

        if (! $validToken) {
            // If no token is configured and auth is enabled, deny access for security
            return false;
        }

        return hash_equals($validToken, $token);
    }
}
