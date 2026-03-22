<?php

return [
    'default' => env('AI_DEFAULT_PROVIDER', 'openai'),

    'transcriber' => env('AI_TRANSCRIBER', 'openai'),

    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o'),
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
];
