<?php

return [
    'asset_root' => env('AI_ASSET_ROOT', is_dir('/runtime-ai') ? '/runtime-ai' : base_path('../../runtime-ai')),
    'provider' => env('AI_PROVIDER', 'none'),
    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'model' => env('OPENAI_CAREER_EXTRACTION_MODEL'),
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
];
