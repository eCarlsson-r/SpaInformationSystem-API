<?php

namespace App\Services;

use App\Ai\Agents\TranslationAgent;
use Illuminate\Support\Facades\Log;

/**
 * AITranslationService
 *
 * Provides AI-powered translation using the Laravel AI SDK.
 * Translates content to Indonesian while preserving treatment names,
 * proper nouns, and numeric values.
 *
 * Requirements: 8.1, 8.2, 8.3, 8.4, 8.6
 */
class AITranslationService implements AITranslationServiceInterface
{
    /**
     * Translate content to the target locale.
     *
     * Returns the original content unchanged when:
     *  - targetLocale is 'en' (Requirement 8.2)
     *  - content is empty
     *  - the AI service is unavailable (Requirement 8.4)
     *
     * @param string $content      The content to translate.
     * @param string $targetLocale The target locale ('en' or 'id').
     * @return string The translated content, or original on failure.
     */
    public function translate(string $content, string $targetLocale): string
    {
        // Return unchanged for English locale or empty content (Requirement 8.2)
        if ($targetLocale === 'en' || empty(trim($content))) {
            return $content;
        }

        // Only translate to Indonesian for now
        if ($targetLocale !== 'id') {
            Log::warning("AITranslationService: Unsupported target locale '{$targetLocale}', returning original content");
            return $content;
        }

        try {
            $translated = (string) (new TranslationAgent)->prompt($content);

            // Guard against empty or identical responses
            if (empty(trim($translated)) || $translated === $content) {
                Log::warning('AITranslationService: Translation returned empty or identical content', [
                    'original'   => $content,
                    'translated' => $translated,
                ]);
                return $content;
            }

            return $translated;
        } catch (\Throwable $e) {
            // Requirement 8.4: graceful fallback — return original content on any failure
            Log::warning('AITranslationService: Translation failed, returning original content', [
                'error'          => $e->getMessage(),
                'target_locale'  => $targetLocale,
            ]);
            return $content;
        }
    }
}
