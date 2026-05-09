<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * RecommendationAgent
 *
 * Generates ranked treatment recommendations based on a customer's booking
 * history and the available treatments at a branch.
 *
 * Returns a JSON array of recommendation objects via structured output.
 *
 * Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 2.1, 2.2, 2.3
 */
#[Model('gpt-4o-mini')]
#[Temperature(0.3)]
#[Timeout(10)]
class RecommendationAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the system instructions for the agent.
     */
    public function instructions(): string
    {
        return <<<PROMPT
You are a spa treatment recommendation engine. Based on the customer's booking
history, recommend treatments from the available list. Return ONLY the structured
JSON output — no explanations, no markdown.

Rules:
- Rank treatments by relevance to the customer's history.
- Each rationale must be 20 words or fewer.
- Only recommend treatments from the provided available list.
- Assign ranks starting from 1 (most recommended).
PROMPT;
    }

    /**
     * Structured output schema: array of recommendation objects.
     *
     * OpenAI structured output requires ALL properties to be listed in "required".
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'recommendations' => $schema->array()
                ->items(
                    $schema->object(fn (JsonSchema $s) => [
                        'treatment_id' => $s->integer()->required(),
                        'rank'         => $s->integer()->min(1)->required(),
                        'rationale'    => $s->string()->required(),
                    ])
                )
                ->required(),
        ];
    }
}
