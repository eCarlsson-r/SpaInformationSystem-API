<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | The default provider used when no provider is specified on an agent or
    | AI operation. Supported: "openai", "anthropic", "gemini", "ollama", etc.
    |
    */

    'default' => env('AI_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | AI Providers
    |--------------------------------------------------------------------------
    |
    | Configure credentials and optional custom base URLs for each provider.
    | The "url" key allows routing through a proxy or local endpoint.
    |
    */

    'providers' => [

        'openai' => [
            'driver' => 'openai',
            'key'    => env('OPENAI_API_KEY'),
            'models' => [
                'text' => [
                    'default' => env('OPENAI_TEXT_MODEL', 'gpt-4o-mini'),
                ],
                'image' => [
                    'default' => env('OPENAI_IMAGE_MODEL', 'gpt-image-1-mini')
                ]
            ],
        ],

        'ollama' => [
            'driver' => 'ollama',
            'url'    => env('OLLAMA_URL', 'http://localhost:11434'),
            'models' => [
                'text' => [
                    'default' => env('OLLAMA_TEXT_MODEL', 'gemma4:e2b'),
                ],
            ],
        ],

        'gemini' => [
            'driver' => 'gemini',
            'key'    => env('GEMINI_API_KEY'),
            'models' => [
                'text' => [
                    'default' => env('GEMINI_TEXT_MODEL', 'gemini-2.5-flash')
                ],
                'image' => [
                    'default' => env('GEMINI_IMAGE_MODEL', 'gemini-2.5-flash-image')
                ]
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Default Models
    |--------------------------------------------------------------------------
    |
    | The default model used for each AI operation type when no model is
    | specified on the agent or operation.
    |
    */

    'models' => [
        'text'          => env('OPENAI_TEXT_MODEL', 'gpt-4o-mini'),
        'image'         => env('OPENAI_IMAGE_MODEL', 'gpt-image-1-mini'),
        'embeddings'    => env('AI_EMBEDDINGS_MODEL', 'text-embedding-3-small'),
        'transcription' => env('AI_TRANSCRIPTION_MODEL', 'whisper-1'),
        'tts'           => env('AI_TTS_MODEL', 'tts-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Embedding Caching
    |--------------------------------------------------------------------------
    */

    'caching' => [
        'embeddings' => [
            'cache' => false,
            'store' => env('CACHE_STORE', 'database'),
        ],
    ],

];
