<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Mcp\Tools;

use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsIdempotent]
class Ping extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'A simple ping tool to test the MCP server connection. Returns a pong response with optional message and timestamp.';

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $message = $request->string('message', 'pong');
        $includeTimestamp = $request->boolean('include_timestamp', true);
        $includeAppInfo = $request->boolean('include_app_info', false);

        $response = [
            'status' => 'success',
            'message' => $message,
        ];

        if ($includeTimestamp) {
            $response['timestamp'] = now()->toIso8601String();
        }

        if ($includeAppInfo) {
            $response['app'] = [
                'name' => config('app.name'),
                'environment' => config('app.env'),
                'laravel_version' => app()->version(),
                'php_version' => PHP_VERSION,
            ];
        }

        return Response::json($response);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'message' => $schema->string()
                ->description('Custom message to include in the response.')
                ->default('pong'),

            'include_timestamp' => $schema->boolean()
                ->description('Whether to include the current timestamp in the response.')
                ->default(true),

            'include_app_info' => $schema->boolean()
                ->description('Whether to include application information (name, environment, Laravel version, PHP version) in the response.')
                ->default(false),
        ];
    }
}
