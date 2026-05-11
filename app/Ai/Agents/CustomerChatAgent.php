<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;

/**
 * CustomerChatAgent
 *
 * Processes natural-language messages for the customer booking assistant.
 * Extracts booking intent or asks for clarification when parameters are missing.
 *
 * Requirements: 4.2, 4.3, 4.4, 4.5, 4.6, 4.7
 */
#[Temperature(0.2)]
#[Timeout(60)]
class CustomerChatAgent implements Agent, Conversational, HasStructuredOutput
{
    use Promptable;

    /**
     * Prior conversation messages injected by ChatbotService.
     *
     * @var Message[]
     */
    private array $history = [];

    /**
     * Inject conversation history for context retention (Requirement 4.7).
     *
     * @param  Message[] $messages
     */
    public function withHistory(array $messages): static
    {
        $clone          = clone $this;
        $clone->history = $messages;
        return $clone;
    }

    /**
     * Get the system instructions for the agent.
     */
    public function instructions(): string
    {
        return <<<PROMPT
You are a spa booking assistant. Extract booking intent from the customer's message. Return ONLY a valid JSON object.

Always return ALL fields. Use an empty string "" for fields that do not apply.

If all four parameters are present (date, time, treatment, branch):
- Set type to "booking_intent"
- Populate params with date (YYYY-MM-DD), time (HH:MM), treatmentId, branchId
- Set missingField and message to ""

If any parameter is missing:
- Set type to "clarification"
- Set missingField to the missing field name (date | time | treatment | branch)
- Set message to a friendly question asking for the missing information
- Set params fields to ""

If the customer asks for a recommendation or treatment advice:
- Set type to "recommendation"
- Set message to a friendly response asking for their preferences (e.g. relaxation, deep tissue, etc.) if they haven't specified any
- Set missingField and params fields to ""

If the message is unrelated to booking or recommendations:
- Set type to "error"
- Set message to a brief explanation
- Set missingField to "" and params fields to ""
PROMPT;
    }

    /**
     * Return prior conversation messages for context (Conversational interface).
     *
     * @return Message[]
     */
    public function messages(): iterable
    {
        return $this->history;
    }

    /**
     * Structured output schema for booking intent or clarification.
     *
     * OpenAI structured output requires ALL properties to be listed in
     * "required". Use empty string "" as the sentinel for "not applicable".
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            // Always present: "booking_intent" | "clarification" | "error"
            'type' => $schema->string()
                ->enum(['booking_intent', 'clarification', 'recommendation', 'error'])
                ->required(),

            // Populated when type = "booking_intent"; empty strings otherwise
            'params' => $schema->object(fn (JsonSchema $s) => [
                'date'        => $s->string()->required(),
                'time'        => $s->string()->required(),
                'treatmentId' => $s->string()->required(),
                'branchId'    => $s->string()->required(),
            ])->required(),

            // Populated when type = "clarification"; empty string otherwise
            'missingField' => $schema->string()->required(),

            // Populated when type = "clarification" or "error"; empty string otherwise
            'message' => $schema->string()->required(),
        ];
    }
}
