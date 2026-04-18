<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class ContentGenerationService
{
    private const DEFAULT_TIMEOUT = 60.0; // Increased for local image generation

    private string $textDriver;
    private string $imageDriver;

    public function __construct()
    {
        $this->textDriver = config('services.ai_text_driver', 'openai');
        $this->imageDriver = config('services.ai_image_driver', 'openai');
    }

    /**
     * Get a configured HTTP client for the specific driver.
     */
    private function getClient(string $driver): Client
    {
        $baseUri = 'https://api.openai.com';
        
        if ($driver === 'ollama') {
            $baseUri = config('services.ollama.url');
        } elseif ($driver === 'localai') {
            $baseUri = config('services.localai.url');
        }

        return new Client([
            'base_uri' => $baseUri,
            'timeout'  => self::DEFAULT_TIMEOUT,
        ]);
    }

    /**
     * Generate description based on fields.
     */
    public function generateDescription(string $type, array $fields): string
    {
        $client = $this->getClient($this->textDriver);
        $model = 'gpt-4o-mini';
        $apiKey = config('services.openai.api_key');

        if ($this->textDriver === 'ollama') {
            $model = config('services.ollama.model', 'gemma2');
            $apiKey = null;
        } elseif ($this->textDriver === 'localai') {
            $model = config('services.localai.text_model', 'gemma-2-9b');
            $apiKey = null;
        }

        if ($this->textDriver === 'openai' && empty($apiKey)) {
            throw new \Exception('OpenAI API Key is not configured.');
        }

        $fieldsJson = json_encode($fields);
        $systemPrompt = "You are a professional copywriter for a premium spa. Write a compelling, elegant description for a {$type} based on the following details: {$fieldsJson}. Keep it under 100 words. Return only the description text.";

        try {
            $headers = ['Content-Type' => 'application/json'];
            if ($apiKey) $headers['Authorization'] = "Bearer {$apiKey}";

            $response = $client->post('/v1/chat/completions', [
                'headers' => $headers,
                'json' => [
                    'model'       => $model,
                    'messages'    => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user',   'content' => "Generate description for this {$type}."],
                    ],
                    'temperature' => 0.7,
                    'max_tokens'  => 200,
                ],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);
            return trim($body['choices'][0]['message']['content'] ?? '');
        } catch (\Throwable $e) {
            Log::error("ContentGenerationService ({$this->textDriver}): Description generation failed", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Generate image based on description or name.
     */
    public function generateImage(string $type, string $prompt): string
    {
        if ($this->imageDriver === 'ollama') {
            throw new \Exception('Image generation is not supported by Ollama. Use LocalAI or OpenAI.');
        }

        $client = $this->getClient($this->imageDriver);
        $model = 'dall-e-3';
        $apiKey = config('services.openai.api_key');

        if ($this->imageDriver === 'localai') {
            $model = config('services.localai.image_model', 'stablediffusion');
            $apiKey = null;
        }

        if ($this->imageDriver === 'openai' && empty($apiKey)) {
            throw new \Exception('OpenAI API Key is not configured.');
        }

        $fullPrompt = "A high-quality, professional photograph for a premium spa website. Subject: {$type}. Context: {$prompt}. Elegant, serene, high-end aesthetics.";

        try {
            $headers = ['Content-Type' => 'application/json'];
            if ($apiKey) $headers['Authorization'] = "Bearer {$apiKey}";

            $response = $client->post('/v1/images/generations', [
                'headers' => $headers,
                'json' => [
                    'model'  => $model,
                    'prompt' => $fullPrompt,
                    'n'      => 1,
                    'size'   => '1024x1024',
                ],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);
            
            // LocalAI often returns the URL directly in the same format as OpenAI
            return $body['data'][0]['url'] ?? '';
        } catch (\Throwable $e) {
            Log::error("ContentGenerationService ({$this->imageDriver}): Image generation failed", ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
