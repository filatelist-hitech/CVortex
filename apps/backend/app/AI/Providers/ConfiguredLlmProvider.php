<?php

namespace App\AI\Providers;

use App\AI\Contracts\LlmProvider;
use App\AI\Data\LlmRequest;
use App\AI\Data\LlmResponse;
use App\AI\Exceptions\LlmProviderException;
use App\AI\ModelPolicyResolver;

class ConfiguredLlmProvider implements LlmProvider
{
    public function __construct(
        private readonly ModelPolicyResolver $resolver,
        private readonly OpenAiResponsesProvider $openAi,
    ) {}

    public function generateStructured(LlmRequest $request): LlmResponse
    {
        $resolved = $this->resolver->resolve($request->modelPolicy);

        return match ($resolved->provider) {
            'openai' => $this->openAi->generateResolved($request, $resolved),
            default => throw new LlmProviderException(
                LlmProviderException::NOT_CONFIGURED,
                'The resolved LLM provider is not available.',
                $resolved->provider,
                $resolved->model,
            ),
        };
    }
}
