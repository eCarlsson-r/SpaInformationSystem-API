<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/**
 * AITranslationService
 *
 * Provides AI-powered translation using OpenAI GPT-4o-mini.
 * Translates content to Indonesian while preserving treatment names,
 * proper nouns, and numeric values.
 *
 * Requirements: 8.1, 8.2, 8.3, 8.4, 8.6
 */
class AITranslationService implements AITranslationServiceInterface
{
    private const OPENAI_TIMEOUT = 9.0;

    private Client $httpClient;

    public function __construct(?Client $httpClient = null)
    {
        $this->httpClient = $httpClient ?? new Client([
            'base_uri' => 'https://api.openai.com',
            'timeout'  => self::OPENAI_TIMEOUT,
        ]);
    }

    /**
     * Translate content to the target locale.
     *
     * @param string $content The content to translate
     * @param string $targetLocale The target locale (e.g., 'id', 'en')
     * @return string The translated content or original if translation fails
     */
    public function translate(string $content, string $targetLocale): string
    {
        // Return unchanged for English locale or empty content
        if ($targetLocale === 'en' || empty(trim($content))) {
            return $content;
        }

        // Only translate to Indonesian for now
        if ($targetLocale !== 'id') {
            Log::warning("AITranslationService: Unsupported target locale '{$targetLocale}', returning original content");
            return $content;
        }

        $apiKey = config('services.openai.api_key');

        if (empty($apiKey)) {
            Log::warning('AITranslationService: OpenAI API key not configured, returning original content');
            return $content;
        }

        $systemPrompt = <<<PROMPT
You are a professional translator specializing in spa and wellness industry content.
Translate the following text to Indonesian (Bahasa Indonesia).

IMPORTANT INSTRUCTIONS:
- Preserve all treatment names, service names, and product names exactly as they appear in English
- Keep all proper nouns (brand names, place names, person names) in their original form
- Maintain all numeric values, dates, times, and measurements unchanged
- Translate naturally and contextually appropriate for spa/wellness industry
- Maintain the same tone and formality level as the original text
- Return ONLY the translated text, no explanations or additional content

Examples:
- "Deep Tissue Massage" → "Deep Tissue Massage" (preserve treatment name)
- "Book your appointment at Downtown Spa" → "Pesan janji temu Anda di Downtown Spa" (translate but keep "Downtown Spa")
- "30-minute session for $50" → "sesi 30-menit seharga $50" (keep numbers and currency)

Translate the following content:
PROMPT;

        try {
            $response = $this->httpClient->post('/v1/chat/completions', [
                'headers' => [
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'       => 'gpt-4o-mini',
                    'messages'    => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $content]
                    ],
                    'temperature' => 0.1, // Low temperature for consistent translations
                    'max_tokens'  => 1000,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (!isset($data['choices'][0]['message']['content'])) {
                Log::warning('AITranslationService: Invalid OpenAI response structure', ['response' => $data]);
                return $content;
            }

            $translatedContent = trim($data['choices'][0]['message']['content']);

            // Validate that we got a reasonable translation (not empty and different from original)
            if (empty($translatedContent) || $translatedContent === $content) {
                Log::warning('AITranslationService: Translation returned empty or identical content', [
                    'original' => $content,
                    'translated' => $translatedContent
                ]);
                return $content;
            }

            return $translatedContent;

        } catch (GuzzleException $e) {
            Log::warning('AITranslationService: OpenAI API request failed', [
                'error' => $e->getMessage(),
                'content' => $content,
                'target_locale' => $targetLocale
            ]);
            return $content;
        } catch (\Throwable $e) {
            Log::warning('AITranslationService: Unexpected error during translation', [
                'error' => $e->getMessage(),
                'content' => $content,
                'target_locale' => $targetLocale
            ]);
            return $content;
        }
    }
}