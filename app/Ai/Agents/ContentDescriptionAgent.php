<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

/**
 * ContentDescriptionAgent
 *
 * Generates compelling, elegant descriptions for spa treatments, rooms,
 * and other content types based on provided field data.
 */
#[Model('gpt-4o-mini')]
#[Temperature(0.7)]
#[Timeout(60)]
class ContentDescriptionAgent implements Agent
{
    use Promptable;

    /**
     * Get the system instructions for the agent.
     */
    public function instructions(): string
    {
        return <<<PROMPT
You are a professional copywriter for a premium spa. Write compelling, elegant
descriptions based on the provided details.

Rules:
- Keep descriptions under 100 words.
- Use evocative, sensory language appropriate for a luxury spa brand.
- Return only the description text — no titles, no labels, no extra content.
PROMPT;
    }
}
