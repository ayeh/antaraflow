<?php

return [
    'default' => env('AI_DEFAULT_PROVIDER', 'openai'),

    'transcriber' => env('AI_TRANSCRIBER', 'openai'),

    /*
     * Languages a transcription should expect in addition to whichever one the
     * meeting is filed under. Malay meetings here borrow English freely
     * mid-sentence ("so kita pergi ke portal dulu"), and naming a single
     * language pushes the model to force everything it hears into that one.
     * These are hints, not constraints — models still detect what was spoken.
     */
    'transcription_language_hints' => ['ms', 'en'],

    /*
     * OpenAI organization Admin key (sk-admin-...) used to read actual spend
     * from the Costs API. Distinct from the standard OPENAI_API_KEY used for
     * inference. Leave empty to disable live-spend/balance display.
     */
    'openai_admin_key' => env('OPENAI_ADMIN_KEY'),

    /*
     * When set, the OpenAI actual-spend figure is scoped to this project
     * (proj_...) instead of the whole organization — so the dashboard reflects
     * this app's spend only. Pair it with a project-scoped OPENAI_API_KEY.
     */
    'openai_project_id' => env('OPENAI_PROJECT_ID'),

    /*
     * Anthropic organization Admin key (sk-ant-admin-...) used to read actual
     * spend from the Cost Report API. Distinct from ANTHROPIC_API_KEY.
     */
    'anthropic_admin_key' => env('ANTHROPIC_ADMIN_KEY'),

    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-5.4-mini'),
            'transcription_model' => env('OPENAI_WHISPER_MODEL', 'whisper-1'),

            /*
             * Uploaded recordings stay on Whisper. It is the only model that
             * returns segment timestamps *and* accepts a vocabulary hint, and
             * measurement against the live API put the diarizing alternative at
             * 3.5x the cost while transcribing proper nouns worse — it refuses
             * keyword hints. Switch to 'gpt-4o-transcribe-diarize' when real
             * speaker labels are worth that trade.
             */
            'upload_transcription_model' => env('OPENAI_UPLOAD_TRANSCRIPTION_MODEL', 'whisper-1'),

            /*
             * Live chunks are short and already carry their own start/end from
             * the session, so they use the cheaper, faster model that returns
             * plain text without timestamps.
             */
            'live_transcription_model' => env('OPENAI_LIVE_TRANSCRIPTION_MODEL', 'gpt-transcribe'),
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
     * Transcription models: cost per audio minute, OR — for models billed on
     * audio/text tokens rather than duration — the same input/output per-million
     * shape as chat. A model with input/output keys is costed from tokens.
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
            'gpt-transcribe' => ['per_minute' => 0.0045],
            'gpt-live-transcribe' => ['per_minute' => 0.017],
            'gpt-4o-transcribe' => ['per_minute' => 0.006],
            'gpt-4o-mini-transcribe' => ['per_minute' => 0.003],

            // Billed on tokens, not duration — roughly $0.006/min in practice,
            // but it scales with how much speech the recording actually contains.
            'gpt-4o-transcribe-diarize' => ['input' => 2.50, 'output' => 10.00],
        ],
    ],
];
