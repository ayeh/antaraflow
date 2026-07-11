<?php

return [
    'default' => env('AI_DEFAULT_PROVIDER', 'openai'),

    'transcriber' => env('AI_TRANSCRIBER', 'openai'),

    /*
     * OpenAI organization Admin key (sk-admin-...) used to read actual spend
     * from the Costs API. Distinct from the standard OPENAI_API_KEY used for
     * inference. Leave empty to disable live-spend/balance display.
     */
    'openai_admin_key' => env('OPENAI_ADMIN_KEY'),

    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-5.4-mini'),
            'transcription_model' => env('OPENAI_WHISPER_MODEL', 'whisper-1'),
        ],
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
        ],
        'google' => [
            'api_key' => env('GOOGLE_AI_API_KEY'),
            'model' => env('GOOGLE_AI_MODEL', 'gemini-2.0-flash'),
        ],
        'ollama' => [
            'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
            'model' => env('OLLAMA_MODEL', 'llama3.2'),
        ],
        'whisper_local' => [
            'url' => env('WHISPER_LOCAL_URL', 'http://localhost:8000'),
            'model' => env('WHISPER_LOCAL_MODEL', 'large-v3'),
        ],
    ],

    /*
     * Per-model pricing in USD used to estimate spend from recorded usage.
     * Chat/completion models: cost per 1 million tokens (input / output).
     * Transcription models: cost per audio minute.
     * Unlisted models are treated as $0 (logged), so keep this current.
     */
    'pricing' => [
        'chat' => [
            'gpt-5.4' => ['input' => 2.50, 'output' => 15.00],
            'gpt-5.4-mini' => ['input' => 0.75, 'output' => 4.50],
            'gpt-5.4-nano' => ['input' => 0.20, 'output' => 1.25],
            'gpt-4o' => ['input' => 2.50, 'output' => 10.00],
            'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
            'gpt-4.1' => ['input' => 2.00, 'output' => 8.00],
            'gpt-4.1-mini' => ['input' => 0.40, 'output' => 1.60],
            'gpt-4.1-nano' => ['input' => 0.10, 'output' => 0.40],
            'claude-sonnet-4-20250514' => ['input' => 3.00, 'output' => 15.00],
            'gemini-2.0-flash' => ['input' => 0.10, 'output' => 0.40],
        ],
        'transcription' => [
            'whisper-1' => ['per_minute' => 0.006],
        ],
    ],
];
