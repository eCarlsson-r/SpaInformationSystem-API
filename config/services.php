<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
    ],

    'ollama' => [
        'url' => env('OLLAMA_URL', 'http://localhost:11434'),
        'model' => env('OLLAMA_MODEL', 'gemma2'),
    ],

    'localai' => [
        'url' => env('LOCALAI_URL', 'http://localhost:8090'),
        'text_model' => env('LOCALAI_TEXT_MODEL', 'gemma-2-9b'),
        'image_model' => env('LOCALAI_IMAGE_MODEL', 'stablediffusion'),
    ],

    'ai_text_driver' => env('AI_TEXT_DRIVER', 'openai'),
    'ai_image_driver' => env('AI_IMAGE_DRIVER', 'openai'),

];
