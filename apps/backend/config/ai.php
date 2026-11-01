<?php

$aiProvider = env('AI_PROVIDER', 'openai');
$geminiExtractionModel = env('GEMINI_CAREER_EXTRACTION_MODEL', 'gemini-2.0-flash');
$geminiDraftModel = env('GEMINI_APPLICATION_DRAFT_MODEL', 'gemini-2.0-flash');
$geminiInputCost = env('GEMINI_INPUT_COST_MICROS_PER_MILLION_TOKENS');
$geminiOutputCost = env('GEMINI_OUTPUT_COST_MICROS_PER_MILLION_TOKENS');

$openaiExtractionModel = env('OPENAI_CAREER_EXTRACTION_MODEL');
$openaiDraftModel = env('OPENAI_APPLICATION_DRAFT_MODEL');
$openaiExtractionInputCost = env('OPENAI_CAREER_INPUT_COST_MICROS_PER_MILLION_TOKENS');
$openaiExtractionOutputCost = env('OPENAI_CAREER_OUTPUT_COST_MICROS_PER_MILLION_TOKENS');
$openaiDraftInputCost = env('OPENAI_APPLICATION_INPUT_COST_MICROS_PER_MILLION_TOKENS');
$openaiDraftOutputCost = env('OPENAI_APPLICATION_OUTPUT_COST_MICROS_PER_MILLION_TOKENS');

$isGemini = $aiProvider === 'gemini';

return [
    'proxy' => env('AI_HTTP_PROXY', env('HTTPS_PROXY', env('HTTP_PROXY'))),
    'asset_root' => env('AI_ASSET_ROOT', is_dir('/runtime-ai') ? '/runtime-ai' : base_path('../../runtime-ai')),
    'default_provider' => $aiProvider,
    'model_policies' => [
        'user_selected_chatgpt_plan' => [
            'provider' => 'openai_chatgpt_plan',
            'selection' => 'account_catalog',
        ],
        'low_cost_structured_extraction' => [
            'provider' => $aiProvider,
            'model' => $isGemini ? $geminiExtractionModel : $openaiExtractionModel,
            'input_cost_micros_per_million_tokens' => $isGemini
                ? (filled($geminiInputCost) ? (int) $geminiInputCost : null)
                : (filled($openaiExtractionInputCost) ? (int) $openaiExtractionInputCost : null),
            'output_cost_micros_per_million_tokens' => $isGemini
                ? (filled($geminiOutputCost) ? (int) $geminiOutputCost : null)
                : (filled($openaiExtractionOutputCost) ? (int) $openaiExtractionOutputCost : null),
        ],
        'standard_semantic_generation' => [
            'provider' => $aiProvider,
            'model' => $isGemini ? $geminiDraftModel : $openaiDraftModel,
            'input_cost_micros_per_million_tokens' => $isGemini
                ? (filled($geminiInputCost) ? (int) $geminiInputCost : null)
                : (filled($openaiDraftInputCost) ? (int) $openaiDraftInputCost : null),
            'output_cost_micros_per_million_tokens' => $isGemini
                ? (filled($geminiOutputCost) ? (int) $geminiOutputCost : null)
                : (filled($openaiDraftOutputCost) ? (int) $openaiDraftOutputCost : null),
        ],
    ],
    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'timeout_seconds' => (int) env('OPENAI_TIMEOUT_SECONDS', 45),
            'default_model' => env('OPENAI_DEFAULT_MODEL', 'gpt-4o-mini'),
            'extraction_input_cost_micros' => filled($openaiExtractionInputCost) ? (int) $openaiExtractionInputCost : null,
            'extraction_output_cost_micros' => filled($openaiExtractionOutputCost) ? (int) $openaiExtractionOutputCost : null,
            'draft_input_cost_micros' => filled($openaiDraftInputCost) ? (int) $openaiDraftInputCost : null,
            'draft_output_cost_micros' => filled($openaiDraftOutputCost) ? (int) $openaiDraftOutputCost : null,
        ],
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'timeout_seconds' => (int) env('GEMINI_TIMEOUT_SECONDS', 45),
            'test_model' => env('GEMINI_TEST_MODEL', 'gemini-2.0-flash'),
            'default_model' => env('GEMINI_DEFAULT_MODEL', 'gemini-2.0-flash'),
            'extraction_model' => $geminiExtractionModel,
            'generation_model' => $geminiDraftModel,
            'input_cost_micros_per_million_tokens' => filled($geminiInputCost) ? (int) $geminiInputCost : null,
            'output_cost_micros_per_million_tokens' => filled($geminiOutputCost) ? (int) $geminiOutputCost : null,
            'available_models' => [
                ['id' => 'gemini-1.5-flash', 'name' => 'Gemini 1.5 Flash (Fast & Cost-effective)'],
                ['id' => 'gemini-1.5-pro', 'name' => 'Gemini 1.5 Pro (Complex Reasoning)'],
                ['id' => 'gemini-2.0-flash', 'name' => 'Gemini 2.0 Flash (Next-gen Fast)'],
            ],
        ],
    ],
    'career_extraction' => [
        'model_policy' => 'low_cost_structured_extraction',
        'skill_id' => 'career.fact-extraction',
        'skill_version' => '1.1.0',
        'prompt_version' => '1.1.0',
        'max_source_characters' => 30000,
    ],
    'vacancy_extraction' => [
        'model_policy' => 'low_cost_structured_extraction',
        'skill_id' => 'vacancy.requirement-extraction',
        'skill_version' => '2.0.0',
        'prompt_version' => '2.0.0',
        'version' => env('AI_VACANCY_EXTRACTION_VERSION', 'v2'),
        'max_source_characters' => 50000,
    ],
];
