<?php

namespace App\AI\Providers;

use App\AI\Contracts\LlmProvider;
use App\AI\Data\LlmRequest;
use App\AI\Data\LlmResponse;
use App\AI\Exceptions\LlmProviderException;

class UnconfiguredLlmProvider implements LlmProvider
{
    public function generateStructured(LlmRequest $request): LlmResponse
    {
        throw new LlmProviderException('No system LLM provider is configured.');
    }
}
