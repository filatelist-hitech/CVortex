<?php

namespace App\Mcp\Tools;

use App\Diagnostics\IncidentRecorder;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

abstract class BoundedTool extends Tool
{
    public function toArray(): array
    {
        $tool = parent::toArray();
        $tool['inputSchema']['additionalProperties'] = false;
        if (isset($tool['outputSchema'])) {
            $tool['outputSchema']['additionalProperties'] = false;
        }
        $tool['annotations'] = array_merge((array) $tool['annotations'], [
            'readOnlyHint' => true,
            'destructiveHint' => false,
            'idempotentHint' => true,
            'openWorldHint' => false,
        ]);
        $tool['securitySchemes'] = [['type' => 'oauth2', 'scopes' => ['mcp:use']]];

        return $tool;
    }

    protected function principal(Request $request): User
    {
        $user = $request->user('api');
        if (! $user instanceof User || ! $user->isActive() || ! $user->tokenCan('mcp:use')) {
            abort(403);
        }

        return $user;
    }

    protected function safeError(Throwable $exception): Response
    {
        if (! $exception instanceof ModelNotFoundException
            && ! $exception instanceof ValidationException
            && ! ($exception instanceof HttpExceptionInterface && in_array($exception->getStatusCode(), [401, 403, 404], true))) {
            try {
                $request = request();
                app(IncidentRecorder::class)->record('MCP_TOOL_FAILED', 'An MCP read operation failed.', 'mcp', 'ERROR', $exception, [
                    'request_id' => $request->attributes->get('request_id'),
                    'user_id' => $request->user()?->id,
                    'route' => $request->route()?->getName(),
                    'operation' => $this->name(),
                ]);
            } catch (Throwable) {
                // Preserve the safe MCP response when both diagnostic sinks are unavailable.
            }
        }

        return match (true) {
            $exception instanceof ModelNotFoundException => $this->error('NOT_FOUND'),
            $exception instanceof ValidationException => $this->error('VALIDATION_FAILED'),
            $exception instanceof HttpExceptionInterface && $exception->getStatusCode() === 404 => $this->error('NOT_FOUND'),
            $exception instanceof HttpExceptionInterface && $exception->getStatusCode() === 403 => $this->error('FORBIDDEN'),
            $exception instanceof HttpExceptionInterface && $exception->getStatusCode() === 401 => $this->error('AUTHENTICATION_REQUIRED'),
            default => $this->error('INTERNAL_ERROR'),
        };
    }

    /** @param array<string, mixed> $payload */
    protected function success(array $payload): ResponseFactory
    {
        request()->attributes->set('mcp_outcome', 'PASS');

        return Response::structured($payload);
    }

    protected function error(string $code): Response
    {
        request()->attributes->set('mcp_outcome', $code);

        return Response::error($code);
    }
}
