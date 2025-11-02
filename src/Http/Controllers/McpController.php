<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Skylence\OptimizeMcp\Support\JsonRpcResponse;
use Skylence\OptimizeMcp\Support\Logger;
use Skylence\OptimizeMcp\Support\OptimizeServerHttp;

final class McpController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        private readonly OptimizeServerHttp $server,
        private readonly Logger $logger
    ) {
    }

    /**
     * Handle JSON-RPC requests.
     */
    public function handle(Request $request): JsonResponse
    {
        $this->logger->info('MCP request received', [
            'method' => $request->method(),
            'uri' => $request->fullUrl(),
            'input_all' => $request->all(),
            'content_type' => $request->header('Content-Type'),
        ]);

        // Validate Content-Type for POST requests
        if (! $request->isJson()) {
            $this->logger->warning('MCP request: Content-Type is not JSON', [
                'content_type' => $request->header('Content-Type'),
            ]);

            return new JsonResponse(
                JsonRpcResponse::error(
                    'Parse error: Content-Type must be application/json',
                    JsonRpcResponse::PARSE_ERROR,
                    null
                ),
                400
            );
        }

        // Validate non-empty body
        $rawContent = $request->getContent();
        if (empty($rawContent)) {
            $this->logger->warning('MCP request: Empty JSON body');

            return new JsonResponse(
                JsonRpcResponse::error(
                    'Invalid Request: Empty JSON body',
                    JsonRpcResponse::INVALID_REQUEST,
                    null
                ),
                400
            );
        }

        // Validate JSON parsing
        $decoded = json_decode($rawContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->warning('MCP request: JSON parse error', [
                'json_error' => json_last_error_msg(),
            ]);

            return new JsonResponse(
                JsonRpcResponse::error(
                    'Parse error: Invalid JSON in request body. Error: '.json_last_error_msg(),
                    JsonRpcResponse::PARSE_ERROR,
                    null
                ),
                400
            );
        }

        // Validate decoded type
        if (! is_array($decoded)) {
            $this->logger->warning('MCP request: Decoded JSON is not an array/object', [
                'decoded_type' => gettype($decoded),
            ]);

            return new JsonResponse(
                JsonRpcResponse::error(
                    'Invalid Request: JSON body must be an object',
                    JsonRpcResponse::INVALID_REQUEST,
                    null
                ),
                400
            );
        }

        $jsonrpc = $decoded['jsonrpc'] ?? null;
        $id = $decoded['id'] ?? null;
        $method = $decoded['method'] ?? null;
        $params = $decoded['params'] ?? [];

        // Validate JSON-RPC version
        if ($jsonrpc !== '2.0') {
            $this->logger->warning('MCP request: Invalid JSON-RPC version', [
                'version_received' => $jsonrpc,
            ]);

            return new JsonResponse(
                JsonRpcResponse::error(
                    "Invalid JSON-RPC version. Must be '2.0'",
                    JsonRpcResponse::INVALID_REQUEST,
                    $id
                ),
                400
            );
        }

        // Validate method
        if (empty($method) || ! is_string($method)) {
            $this->logger->warning('MCP request: Missing or invalid method', [
                'method_received' => $method,
            ]);

            return new JsonResponse(
                JsonRpcResponse::error(
                    'Invalid Request: Method is missing or not a string',
                    JsonRpcResponse::INVALID_REQUEST,
                    $id
                ),
                400
            );
        }

        $this->logger->info('JSON-RPC request processing', [
            'jsonrpc_version' => $jsonrpc,
            'id' => $id,
            'method' => $method,
            'params_type' => gettype($params),
        ]);

        // Handle notifications (no response expected)
        if ($method === 'notifications/initialized' && is_null($id)) {
            $this->logger->info('MCP notification: Client initialized');

            return new JsonResponse(null, 204);
        }

        try {
            $result = match ($method) {
                'initialize', 'mcp.manifest', 'mcp.getManifest' => $this->getManifestResponse(),
                'tools/list' => ['tools' => $this->server->getTools()],
                'tools/call' => $this->callToolViaJsonRpc($params, $id),
                default => throw new \Exception("Method not found: {$method}"),
            };

            $this->logger->info('MCP request completed', ['method' => $method]);

            // For tools/call, the result is already a complete JSON-RPC response
            if ($method === 'tools/call' && is_array($result) && isset($result['jsonrpc'])) {
                return new JsonResponse($result);
            }

            return new JsonResponse(JsonRpcResponse::success($result, $id));
        } catch (\Exception $e) {
            $this->logger->error('MCP request failed', [
                'method' => $method,
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse(
                JsonRpcResponse::error($e->getMessage(), JsonRpcResponse::INTERNAL_ERROR, $id)
            );
        }
    }

    /**
     * Get the server manifest.
     */
    public function manifest(Request $request): JsonResponse
    {
        $this->logger->info('MCP manifest request received', [
            'method' => $request->method(),
            'uri' => $request->fullUrl(),
        ]);

        // Handle GET requests for manifest
        if ($request->method() === 'GET') {
            return new JsonResponse(
                JsonRpcResponse::success($this->buildManifestResponse())
            );
        }

        // For POST requests, treat as JSON-RPC call
        return $this->handle($request);
    }

    /**
     * Get manifest response in MCP protocol format.
     */
    private function getManifestResponse(): array
    {
        return $this->buildManifestResponse();
    }

    /**
     * Build the complete manifest response.
     */
    private function buildManifestResponse(): array
    {
        $manifest = $this->server->getManifest();

        return [
            'protocolVersion' => '2024-11-05',
            'serverInfo' => [
                'name' => $manifest['name'],
                'version' => $manifest['version'],
                'description' => $manifest['description'] ?? '',
            ],
            'capabilities' => [
                'tools' => new \stdClass(),
            ],
        ];
    }

    /**
     * Call a tool via JSON-RPC and return formatted MCP response.
     */
    private function callToolViaJsonRpc(array $params, string|int|null $id): array
    {
        // Validate params is an array
        if (! is_array($params)) {
            throw new \InvalidArgumentException('Invalid params: Must be an object');
        }

        $toolName = $params['name'] ?? null;
        $arguments = $params['arguments'] ?? [];

        // Validate tool name
        if (! $toolName || ! is_string($toolName)) {
            throw new \InvalidArgumentException('Invalid params: tool name is required and must be a string');
        }

        // Validate arguments
        if (! is_array($arguments) && ! is_object($arguments)) {
            throw new \InvalidArgumentException('Invalid params: arguments must be an array or object');
        }

        $this->logger->info('Executing tool via JSON-RPC', [
            'tool_name' => $toolName,
        ]);

        $result = $this->executeTool($toolName, (array) $arguments);

        return JsonRpcResponse::mcpToolResponse($result, $id);
    }

    /**
     * Execute a tool by name.
     */
    private function executeTool(string $toolName, array $params = []): mixed
    {
        return $this->server->executeTool($toolName, $params);
    }

    /**
     * Execute a tool call via JSON-RPC (tools/call endpoint).
     */
    public function executeToolCall(Request $request): JsonResponse
    {
        $this->logger->info('MCP executeToolCall received', [
            'method' => $request->method(),
            'uri' => $request->fullUrl(),
            'input_all' => $request->all(),
            'content_type' => $request->header('Content-Type'),
        ]);

        // Validate Content-Type
        if (! $request->isJson()) {
            $this->logger->warning('executeToolCall: Content-Type is not JSON', [
                'content_type' => $request->header('Content-Type'),
            ]);

            return new JsonResponse(
                JsonRpcResponse::error(
                    'Parse error: Content-Type must be application/json',
                    JsonRpcResponse::PARSE_ERROR,
                    null
                ),
                400
            );
        }

        // Validate non-empty body
        $rawContent = $request->getContent();
        if (empty($rawContent)) {
            $this->logger->warning('executeToolCall: Empty JSON body');

            return new JsonResponse(
                JsonRpcResponse::error(
                    'Invalid Request: Empty JSON body',
                    JsonRpcResponse::INVALID_REQUEST,
                    null
                ),
                400
            );
        }

        // Parse JSON
        $decoded = json_decode($rawContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->warning('executeToolCall: JSON parse error', [
                'json_error' => json_last_error_msg(),
            ]);

            return new JsonResponse(
                JsonRpcResponse::error(
                    'Parse error: Invalid JSON in request body. Error: '.json_last_error_msg(),
                    JsonRpcResponse::PARSE_ERROR,
                    null
                ),
                400
            );
        }

        // Validate decoded type
        if (! is_array($decoded)) {
            $this->logger->warning('executeToolCall: Decoded JSON is not an array/object', [
                'decoded_type' => gettype($decoded),
            ]);

            return new JsonResponse(
                JsonRpcResponse::error(
                    'Invalid Request: JSON body must be an object',
                    JsonRpcResponse::INVALID_REQUEST,
                    null
                ),
                400
            );
        }

        $jsonrpc = $decoded['jsonrpc'] ?? null;
        $id = $decoded['id'] ?? null;
        $params = $decoded['params'] ?? [];

        // Validate JSON-RPC version
        if ($jsonrpc !== '2.0') {
            $this->logger->warning('executeToolCall: Invalid JSON-RPC version', [
                'version_received' => $jsonrpc,
            ]);

            return new JsonResponse(
                JsonRpcResponse::error(
                    "Invalid JSON-RPC version. Must be '2.0'",
                    JsonRpcResponse::INVALID_REQUEST,
                    $id
                ),
                400
            );
        }

        // Validate params
        if (! is_array($params)) {
            $this->logger->warning('MCP executeToolCall: params is not an object/array', [
                'params_received' => $params,
            ]);

            return new JsonResponse(
                JsonRpcResponse::error(
                    'Invalid params: Must be an object',
                    JsonRpcResponse::INVALID_PARAMS,
                    $id
                ),
                400
            );
        }

        $toolName = $params['name'] ?? null;
        $arguments = $params['arguments'] ?? [];

        // Validate tool name
        if (! $toolName || ! is_string($toolName)) {
            $this->logger->warning('executeToolCall: Missing or invalid tool name', [
                'tool_name_received' => $toolName,
            ]);

            return new JsonResponse(
                JsonRpcResponse::error(
                    'Invalid params: tool name is required and must be a string',
                    JsonRpcResponse::INVALID_PARAMS,
                    $id
                ),
                400
            );
        }

        // Validate arguments
        if (! is_array($arguments) && ! is_object($arguments)) {
            $this->logger->warning('MCP executeToolCall: Invalid arguments type', [
                'arguments_type' => gettype($arguments),
            ]);

            return new JsonResponse(
                JsonRpcResponse::error(
                    'Invalid params: arguments must be an array or object',
                    JsonRpcResponse::INVALID_PARAMS,
                    $id
                ),
                400
            );
        }

        $this->logger->info('Executing tool via executeToolCall', [
            'tool_name' => $toolName,
        ]);

        try {
            $result = $this->executeTool($toolName, (array) $arguments);

            return new JsonResponse(JsonRpcResponse::mcpToolResponse($result, $id));
        } catch (\Exception $e) {
            $this->logger->error('executeToolCall: Tool execution error', [
                'tool' => $toolName,
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse(
                JsonRpcResponse::error(
                    $e->getMessage(),
                    JsonRpcResponse::INTERNAL_ERROR,
                    $id
                ),
                500
            );
        }
    }

    /**
     * Execute a specific tool directly via REST endpoint.
     */
    public function executeToolRest(Request $request, string $tool): JsonResponse
    {
        try {
            $this->logger->info('MCP tool execution request', [
                'tool' => $tool,
                'method' => $request->method(),
                'input' => $request->all(),
            ]);

            // Validate request is JSON for POST requests
            if ($request->method() === 'POST' && ! $request->isJson()) {
                $this->logger->warning('Invalid request format - not JSON', [
                    'tool' => $tool,
                    'content_type' => $request->header('Content-Type'),
                ]);

                return new JsonResponse([
                    'content' => [
                        [
                            'type' => 'error',
                            'text' => 'Parse error: Content-Type must be application/json',
                        ],
                    ],
                ], 400);
            }

            $params = $request->all();

            $this->logger->debug('Executing tool', [
                'tool' => $tool,
                'params' => $params,
            ]);

            $result = $this->executeTool($tool, $params);

            // Format response in MCP standard format
            $response = [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode($result),
                    ],
                ],
            ];

            $this->logger->info('Tool execution successful', [
                'tool' => $tool,
            ]);

            return new JsonResponse($response);
        } catch (\Exception $e) {
            $this->logger->error('Tool execution failed', [
                'tool' => $tool,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse([
                'content' => [
                    [
                        'type' => 'error',
                        'text' => $e->getMessage(),
                    ],
                ],
            ], 500);
        }
    }
}
