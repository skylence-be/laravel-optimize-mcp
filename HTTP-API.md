# HTTP MCP API Documentation

This package now supports HTTP access to MCP tools via JSON-RPC 2.0 protocol.

## Configuration

The HTTP API can be configured in `config/optimize-mcp.php`:

```php
'http' => [
    'enabled' => true,
    'prefix' => 'optimize-mcp',  // Default: /optimize-mcp
    'middleware' => [],           // Add middleware as needed
],

'logging' => [
    'enabled' => false,      // Enable for debugging
    'channel' => 'stack',
],
```

## Endpoints

### Base URL
All endpoints are prefixed with `/optimize-mcp` by default (configurable).

### 1. Get Manifest
Returns the MCP server manifest with available capabilities.

**GET** `/optimize-mcp/manifest.json`

**Response:**
```json
{
    "jsonrpc": "2.0",
    "result": {
        "protocolVersion": "2024-11-05",
        "serverInfo": {
            "name": "Laravel Optimize",
            "version": "1.0.0"
        },
        "capabilities": {
            "tools": {}
        }
    },
    "id": null
}
```

### 2. List Tools
Returns a list of available MCP tools.

**POST** `/optimize-mcp/`

**Request:**
```json
{
    "jsonrpc": "2.0",
    "id": 1,
    "method": "tools/list",
    "params": {}
}
```

**Response:**
```json
{
    "jsonrpc": "2.0",
    "result": {
        "tools": [
            {
                "name": "ping",
                "description": "A simple ping tool to test the MCP server connection.",
                "inputSchema": {
                    "type": "object",
                    "properties": {
                        "message": {
                            "type": "string",
                            "description": "Custom message to include in the response.",
                            "default": "pong"
                        },
                        "include_timestamp": {
                            "type": "boolean",
                            "description": "Whether to include the current timestamp.",
                            "default": true
                        },
                        "include_app_info": {
                            "type": "boolean",
                            "description": "Whether to include application information.",
                            "default": false
                        }
                    }
                }
            }
        ]
    },
    "id": 1
}
```

### 3. Call Tool (JSON-RPC)
Execute a tool using JSON-RPC 2.0 protocol.

**POST** `/optimize-mcp/tools/call`

**Request:**
```json
{
    "jsonrpc": "2.0",
    "id": 2,
    "method": "tools/call",
    "params": {
        "name": "ping",
        "arguments": {
            "message": "hello",
            "include_timestamp": true,
            "include_app_info": false
        }
    }
}
```

**Response:**
```json
{
    "jsonrpc": "2.0",
    "result": {
        "content": [
            {
                "type": "text",
                "text": "{\"status\":\"success\",\"message\":\"hello\",\"timestamp\":\"2025-11-02T10:30:00+00:00\"}"
            }
        ]
    },
    "id": 2
}
```

### 4. Call Tool (REST)
Execute a tool using simple REST endpoint.

**POST** `/optimize-mcp/tools/ping`

**Request:**
```json
{
    "message": "test",
    "include_timestamp": true,
    "include_app_info": true
}
```

**Response:**
```json
{
    "content": [
        {
            "type": "text",
            "text": "{\"status\":\"success\",\"message\":\"test\",\"timestamp\":\"2025-11-02T10:30:00+00:00\",\"app\":{\"name\":\"Laravel\",\"environment\":\"local\",\"laravel_version\":\"11.x\",\"php_version\":\"8.2.0\"}}"
        }
    ]
}
```

## Testing with cURL

### Test Ping Tool (JSON-RPC)
```bash
curl -X POST http://your-app.test/optimize-mcp/tools/call \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": 1,
    "method": "tools/call",
    "params": {
        "name": "ping",
        "arguments": {
            "message": "Hello MCP!",
            "include_timestamp": true
        }
    }
}'
```

### Test Ping Tool (REST)
```bash
curl -X POST http://your-app.test/optimize-mcp/tools/ping \
  -H "Content-Type: application/json" \
  -d '{
    "message": "Hello MCP!",
    "include_timestamp": true,
    "include_app_info": true
}'
```

### Get Manifest
```bash
curl -X GET http://your-app.test/optimize-mcp/manifest.json
```

### List Tools
```bash
curl -X POST http://your-app.test/optimize-mcp/ \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": 1,
    "method": "tools/list",
    "params": {}
}'
```

## Error Handling

The API follows JSON-RPC 2.0 error codes:

- `-32700`: Parse error (invalid JSON)
- `-32600`: Invalid request
- `-32601`: Method not found
- `-32602`: Invalid params
- `-32603`: Internal error

**Error Response Example:**
```json
{
    "jsonrpc": "2.0",
    "error": {
        "code": -32601,
        "message": "Method not found: invalid/method"
    },
    "id": 1
}
```

## Middleware

You can add authentication or other middleware to protect the API endpoints:

```php
'http' => [
    'enabled' => true,
    'prefix' => 'optimize-mcp',
    'middleware' => ['auth:sanctum', 'throttle:60,1'],
],
```

## Available Tools

- **ping**: Test the MCP server connection
- Additional tools can be added to `src/Mcp/Tools/` directory

## Notes

- All POST requests must have `Content-Type: application/json` header
- The `id` field in JSON-RPC requests can be any string or number
- Notifications (requests without `id`) will return HTTP 204 No Content
