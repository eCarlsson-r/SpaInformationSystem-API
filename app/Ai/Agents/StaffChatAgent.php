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
 * StaffChatAgent
 *
 * Processes natural-language operational queries from spa staff.
 * Classifies intent and returns a structured data response.
 *
 * Requirements: 5.2, 5.3, 5.4, 5.5, 5.6
 */
#[Model('gpt-4o-mini')]
#[Temperature(0.1)]
#[Timeout(10)]
class StaffChatAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the system instructions for the agent.
     */
    public function instructions(): string
    {
        return <<<PROMPT
You are a spa operations assistant for staff. Classify the query intent as one of:
revenue_query, booking_query, staff_query, session_query.

Return a structured response with:
- type: "data_response" for valid queries, "authorization_error" if outside scope
- intent: the classified intent string
- value: the relevant data value (number or string)
- period: the time period referenced (e.g. "today", "this week", "last 30 days")
- branch: the branch name or ID referenced
- formattedAnswer: a concise, human-readable answer to the query

If the query is outside the staff member's authorization scope, return type "authorization_error".
PROMPT;
    }

    /**
     * Structured output schema for staff query responses.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()
                ->enum(['data_response', 'authorization_error', 'error'])
                ->required(),

            'intent' => $schema->string()
                ->enum(['revenue_query', 'booking_query', 'staff_query', 'session_query']),

            'value'           => $schema->string(),
            'period'          => $schema->string(),
            'branch'          => $schema->string(),
            'formattedAnswer' => $schema->string(),
        ];
    }
}
