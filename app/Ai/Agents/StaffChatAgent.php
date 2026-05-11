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
#[Temperature(0.1)]
#[Timeout(60)]
class StaffChatAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the system instructions for the agent.
     */
    public function instructions(): string
    {
        return <<<PROMPT
You are a spa operations assistant for staff. Classify the query intent and return
a structured response. Always populate ALL fields — use "" for fields that do not apply.

For valid queries:
- Set type to "data_response"
- Set intent to one of: revenue_query, booking_query, staff_query, session_query
- Set value to the relevant data value as a string
- Set period to the time period referenced (e.g. "today", "this week", "last 30 days")
- Set branch to the branch name or ID referenced, or "" if not applicable
- Set formattedAnswer to a concise, human-readable answer

If the query is outside the staff member's authorization scope:
- Set type to "authorization_error"
- Set all other fields to ""
PROMPT;
    }

    /**
     * Structured output schema for staff query responses.
     *
     * OpenAI structured output requires ALL properties to be listed in
     * "required". Use empty string "" as the sentinel for "not applicable".
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()
                ->enum(['data_response', 'authorization_error', 'error'])
                ->required(),

            'intent'          => $schema->string()->required(),
            'value'           => $schema->string()->required(),
            'period'          => $schema->string()->required(),
            'branch'          => $schema->string()->required(),
            'formattedAnswer' => $schema->string()->required(),
        ];
    }
}
