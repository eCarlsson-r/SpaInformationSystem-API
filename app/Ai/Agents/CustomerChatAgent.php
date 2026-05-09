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
#[Model('gpt-4o-mini')]
#[Temperature(0.2)]
#[Timeout(10)]
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
You are a spa booking assistant. Extract booking intent from the customer's message.

If all four parameters are present (date, time, treatment, branch), set type to
"booking_intent" and populate the params object.

If any parameter is missing, set type to "clarification", specify the missingField
(one of: date, time, treatment, branch), and provide a friendly question as message.

If the message is unrelated to booking, set type to "error" with an appropriate message.
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
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()
                ->enum(['booking_intent', 'clarification', 'error'])
                ->required(),

            'params' => $schema->object(fn (JsonSchema $s) => [
                'date'        => $s->string(),
                'time'        => $s->string(),
                'treatmentId' => $s->string(),
                'branchId'    => $s->string(),
            ]),

            'missingField' => $schema->string()
                ->enum(['date', 'time', 'treatment', 'branch']),

            'message' => $schema->string(),
        ];
    }
}
