<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Mcp\Tools;

use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

final class EchoMessage extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'Echo back any message you send';

    /**
     * Get the tool's input schema.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'message' => $schema->string()
                ->description('The message to echo back')
                ->required(),
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $message = $request->string('message');

        return Response::text("Echo: {$message}");
    }
}
