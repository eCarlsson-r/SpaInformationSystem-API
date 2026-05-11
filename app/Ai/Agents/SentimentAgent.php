<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * SentimentAgent
 *
 * Analyzes the sentiment of customer feedback.
 * Returns a score between -1.0 and 1.0 and a label (positive, neutral, negative).
 */
#[Temperature(0.1)]
#[Timeout(60)]
class SentimentAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the system instructions for the agent.
     */
    public function instructions(): string
    {
        return <<<PROMPT
You are a sentiment analysis engine for a spa customer feedback system.
Analyze the sentiment of the provided customer comment and return a structured response.

Rules:
- score must be a float between -1.0 (most negative) and 1.0 (most positive)
- label must be one of: positive, neutral, negative
- score >= 0.2 → label must be "positive"
- score <= -0.2 → label must be "negative"
- -0.2 < score < 0.2 → label must be "neutral"
PROMPT;
    }

    /**
     * Structured output schema for sentiment analysis.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'score' => $schema->number()
                ->min(-1.0)
                ->max(1.0)
                ->required(),

            'label' => $schema->string()
                ->enum(['positive', 'neutral', 'negative'])
                ->required(),
        ];
    }
}
