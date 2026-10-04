<?php

declare(strict_types=1);

namespace App\Mcp\Http\Controllers;

use App\Mcp\OAuth\McpResource;
use App\Mcp\OAuth\RedirectUriValidator;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Server\Http\Controllers\OAuthRegisterController as LaravelOAuthRegisterController;
use Laravel\Mcp\Server\Registrar;
use Throwable;

final class RegisterOAuthClientController extends LaravelOAuthRegisterController
{
    /**
     * Register an OAuth client only after checking each redirect URI structurally.
     *
     * @throws BindingResolutionException
     */
    public function __invoke(Request $request): JsonResponse
    {
        $redirectUriValidator = app(RedirectUriValidator::class);
        $validator = Validator::make($request->all(), [
            'client_name' => ['nullable', 'string', 'min:1', 'max:255'],
            'name' => ['nullable', 'string', 'min:1', 'max:255'],
            'redirect_uris' => ['required', 'array', 'min:1'],
            'redirect_uris.*' => ['bail', 'required', 'string', function (string $attribute, string $value, $fail) use ($redirectUriValidator): void {
                if (! $redirectUriValidator->allows($value)) {
                    $fail($attribute.' is not a permitted redirect URI.');
                }
            }],
            'logo_uri' => ['nullable', 'string', 'url:http,https', 'max:2048'],
            'client_uri' => ['nullable', 'string', 'url:http,https', 'max:2048'],
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $redirectError = $errors->first('redirect_uris*');

            return response()->json([
                'error' => $redirectError !== '' ? 'invalid_redirect_uri' : 'invalid_client_metadata',
                'error_description' => $redirectError !== '' ? $redirectError : $errors->first(),
            ], 400);
        }

        $validated = $validator->validated();

        if (! class_exists('Laravel\\Passport\\ClientRepository')) {
            return response()->json([
                'error' => 'server_error',
                'error_description' => 'OAuth support (Passport) is not installed.',
            ], 500);
        }

        $clients = Container::getInstance()->make('Laravel\\Passport\\ClientRepository');

        try {
            $client = $clients->createAuthorizationCodeGrantClient(
                name: $this->resolveClientName($validated),
                redirectUris: $validated['redirect_uris'],
                confidential: false,
                enableDeviceFlow: false,
            );

            $this->grantMcpScope($client);
            $metadata = $this->persistClientMetadata($client, $validated);
        } catch (Throwable $throwable) {
            report($throwable);

            return response()->json([
                'error' => 'server_error',
                'error_description' => 'The client could not be registered.',
            ], 500);
        }

        return response()->json([
            'client_id' => (string) $client->id,
            'grant_types' => $client->getAttribute('grant_types'),
            'response_types' => ['code'],
            'redirect_uris' => $client->getAttribute('redirect_uris'),
            'scope' => Registrar::OAUTH_SCOPE.' '.McpResource::DRAFT_WRITE_SCOPE,
            'token_endpoint_auth_method' => 'none',
            ...$metadata,
        ], 201);
    }

    protected function grantMcpScope(mixed $client): void
    {
        parent::grantMcpScope($client);
        if (! $client instanceof Model) {
            return;
        }

        $scopes = $client->refresh()->getAttribute('scopes');
        if (! is_array($scopes) || in_array(McpResource::DRAFT_WRITE_SCOPE, $scopes, true)) {
            return;
        }

        $client->forceFill(['scopes' => [...$scopes, McpResource::DRAFT_WRITE_SCOPE]])->save();
    }
}
