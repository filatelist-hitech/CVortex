<?php

namespace App\AI\Contracts;

use App\AI\Data\LlmRequest;
use App\AI\Data\LlmResponse;

interface LlmProvider
{
    public function generateStructured(LlmRequest $request): LlmResponse;
}
