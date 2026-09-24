<?php

return [
    'asset_root' => env('AI_ASSET_ROOT', is_dir('/runtime-ai') ? '/runtime-ai' : base_path('../../runtime-ai')),
    'model_policies' => [
        'low_cost_structured_extraction' => [
            'provider' => env('AI_PROVIDER', 'none'),
            'model' => env('OPENAI_CAREER_EXTRACTION_MODEL'),
            'input_cost_micros_per_million_tokens' => filled(env('OPENAI_CAREER_INPUT_COST_MICROS_PER_MILLION_TOKENS'))
                ? (int) env('OPENAI_CAREER_INPUT_COST_MICROS_PER_MILLION_TOKENS') : null,
            'output_cost_micros_per_million_tokens' => filled(env('OPENAI_CAREER_OUTPUT_COST_MICROS_PER_MILLION_TOKENS'))
                ? (int) env('OPENAI_CAREER_OUTPUT_COST_MICROS_PER_MILLION_TOKENS') : null,
        ],
    ],
    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'timeout_seconds' => (int) env('OPENAI_TIMEOUT_SECONDS', 45),
        ],
    ],
    'career_extraction' => [
        'model_policy' => 'low_cost_structured_extraction',
        'skill_id' => 'career.fact-extraction',
        'skill_version' => '1.0.0',
        'prompt_version' => '1.0.0',
        'max_source_characters' => 30000,
    ],
    'vacancy_extraction' => [
        'model_policy' => 'low_cost_structured_extraction',
        'skill_id' => 'vacancy.requirement-extraction',
        'skill_version' => '1.0.0',
        'prompt_version' => '1.0.0',
        'max_source_characters' => 50000,
    ],
];
