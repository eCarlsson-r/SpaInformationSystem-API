<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

/**
 * TranslationAgent
 *
 * Translates AI-generated content (treatment rationales, chatbot responses,
 * sentiment summaries) to Indonesian (Bahasa Indonesia) while preserving
 * treatment names, proper nouns, and numeric values.
 *
 * Requirements: 8.1, 8.2, 8.3, 8.4, 8.6
 */
#[Model('gpt-4o-mini')]
#[Temperature(0.1)]
#[Timeout(10)]
class TranslationAgent implements Agent
{
    use Promptable;

    /**
     * Get the system instructions for the agent.
     */
    public function instructions(): string
    {
        return <<<PROMPT
You are a professional translator specializing in spa and wellness industry content.
Translate the provided text to Indonesian (Bahasa Indonesia).

IMPORTANT RULES:
- Preserve all treatment names, service names, and product names exactly as they appear in English.
- Keep all proper nouns (brand names, place names, person names) in their original form.
- Maintain all numeric values, dates, times, and measurements unchanged.
- Translate naturally and contextually for the spa/wellness industry.
- Maintain the same tone and formality level as the original text.
- Return ONLY the translated text — no explanations, no additional content.

Examples:
- "Deep Tissue Massage" → "Deep Tissue Massage" (preserve treatment name)
- "Book your appointment at Downtown Spa" → "Pesan janji temu Anda di Downtown Spa"
- "30-minute session for $50" → "sesi 30-menit seharga $50"
PROMPT;
    }
}
