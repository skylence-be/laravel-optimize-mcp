<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Support;

final class JsonRpcResponse
{
    // JSON-RPC 2.0 Error Codes
    public const PARSE_ERROR = -32700;
    public const INVALID_REQUEST = -32600;
    public const METHOD_NOT_FOUND = -32601;
    public const INVALID_PARAMS = -32602;
    public const INTERNAL_ERROR = -32603;

    /**
     * Create a successful JSON-RPC response.
     */
    public static function success(mixed $result, string|int|null $id = null): array
    {
        return [
            'jsonrpc' => '2.0',
            'result' => $result,
            'id' => $id,
        ];
    }

    /**
     * Create an error JSON-RPC response.
     */
    public static function error(string $message, int $code = self::INTERNAL_ERROR, string|int|null $id = null, mixed $data = null): array
    {
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if ($data !== null) {
            $error['data'] = $data;
        }

        return [
            'jsonrpc' => '2.0',
            'error' => $error,
            'id' => $id,
        ];
    }

    /**
     * Create a parse error response.
     */
    public static function parseError(string|int|null $id = null): array
    {
        return self::error('Parse error', self::PARSE_ERROR, $id);
    }

    /**
     * Create an invalid request response.
     */
    public static function invalidRequest(string|int|null $id = null): array
    {
        return self::error('Invalid Request', self::INVALID_REQUEST, $id);
    }

    /**
     * Create a method not found response.
     */
    public static function methodNotFound(string|int|null $id = null): array
    {
        return self::error('Method not found', self::METHOD_NOT_FOUND, $id);
    }

    /**
     * Create an invalid params response.
     */
    public static function invalidParams(string|int|null $id = null): array
    {
        return self::error('Invalid params', self::INVALID_PARAMS, $id);
    }

    /**
     * Formats content for MCP tools response.
     */
    public static function mcpToolResponse(mixed $content, string|int|null $id = null): array
    {
        // If content is already in the correct format, use it directly
        if (is_array($content) && isset($content['content'])) {
            return self::success($content, $id);
        }

        // Convert to the expected MCP format
        return self::success([
            'content' => [
                [
                    'type' => 'text',
                    'text' => is_string($content) ? $content : json_encode($content, JSON_PRETTY_PRINT),
                ],
            ],
        ], $id);
    }
}
