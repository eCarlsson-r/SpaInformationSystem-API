<?php

namespace App\Services;

/**
 * AITranslationServiceInterface
 *
 * Contract for AI-powered translation services.
 */
interface AITranslationServiceInterface
{
    /**
     * Translate content to the target locale.
     *
     * @param string $content The content to translate
     * @param string $targetLocale The target locale (e.g., 'id', 'en')
     * @return string The translated content or original if translation fails
     */
    public function translate(string $content, string $targetLocale): string;
}