<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/**
 * ChatbotService
 *
 * Processes natural-language messages for both customer booking assistant
 * and staff operational query flows using OpenAI.
 *
 * Requirements: 4.2, 4.3, 4.4, 4.5, 4.6, 4.7, 5.2, 5.3, 5.4, 5.5, 5.6
 */
class ChatbotService
{
    private const OPENAI_TIMEOUT = 9.0;

    private Client $httpClient;

    public function __construct(?Client $httpClient = null)
    {
        $this->httpClient = $httpClient ?? new Client([
            'base_uri' => 'https://api.openai.com',
            'timeout'  => self::OPENAI_TIMEOUT,
        ]);
    }

    /**
     * Process a customer booking assistant message.
     *
     * @param  string $message
     * @param  array  $history  Last 10 messages [['role' => ..., 'content' => ...]]
     * @return array  ChatResponse payload
     *
     * Requirements: 4.2, 4.3, 4.4, 4.5, 4.6, 4.7
     */
    public function processCustomerMessage(string $message, array $history = []): array
    {
        $apiKey = config('services.openai.api_key');

        if (empty($apiKey)) {
            return ['type' => 'error', 'message' => 'Assistant is temporarily unavailable.'];
        }

        $systemPrompt = <<<PROMPT
You are a spa booking assistant. Extract booking intent from the customer's message.
If all four parameters are present (date, time, treatment, branch), return:
{"type":"booking_intent","params":{"date":"YYYY-MM-DD","time":"HH:MM","treatmentId":"<id>","branchId":"<id>"}}

If any parameter is missing, return:
{"type":"clarification","missingField":"<date|time|treatment|branch>","message":"<question>"}

Return ONLY valid JSON, no markdown.
PROMPT;

        try {
            $messages = [['role' => 'system', 'content' => $systemPrompt]];

            // Include conversation history for context retention (Requirement 4.7)
            foreach (array_slice($history, -9) as $msg) {
                $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
            }

            $messages[] = ['role' => 'user', 'content' => $message];

            $response = $this->httpClient->post('/v1/chat/completions', [
                'headers' => [
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'       => 'gpt-4o-mini',
                    'messages'    => $messages,
                    'temperature' => 0.2,
                    'max_tokens'  => 200,
                ],
            ]);

            $body    = json_decode($response->getBody()->getContents(), true);
            $content = $body['choices'][0]['message']['content'] ?? '{}';

            $cleaned = preg_replace('/^```(?:json)?\s*/i', '', trim($content));
            $cleaned = preg_replace('/\s*```$/', '', $cleaned);

            $result = json_decode(trim($cleaned), true);

            if (!is_array($result) || !isset($result['type'])) {
                return ['type' => 'error', 'message' => 'Unexpected response from assistant.'];
            }

            return $result;
        } catch (\Throwable $e) {
            Log::warning('ChatbotService: Customer message processing failed', ['error' => $e->getMessage()]);
            return ['type' => 'error', 'message' => 'Assistant is temporarily unavailable.'];
        }
    }

    /**
     * Process a staff operational query.
     *
     * @param  string $query
     * @param  array  $staffContext  ['role' => ..., 'branch_id' => ...]
     * @return array  ChatResponse payload
     *
     * Requirements: 5.2, 5.3, 5.4, 5.5, 5.6
     */
    public function processStaffQuery(string $query, array $staffContext = []): array
    {
        $apiKey = config('services.openai.api_key');

        if (empty($apiKey)) {
            return ['type' => 'error', 'message' => 'Assistant is temporarily unavailable.'];
        }

        $role     = $staffContext['role']      ?? 'staff';
        $branchId = $staffContext['branch_id'] ?? null;

        $systemPrompt = <<<PROMPT
You are a spa operations assistant for staff. Classify the query intent as one of:
revenue_query, booking_query, staff_query, session_query.

Then return a structured JSON response:
{"type":"data_response","intent":"<intent>","value":<value>,"period":"<period>","branch":"<branch>","formattedAnswer":"<answer>"}

If the query is outside the staff member's authorization scope, return:
{"type":"authorization_error"}

Return ONLY valid JSON, no markdown.
PROMPT;

        $userPrompt = "Staff role: {$role}, Branch ID: {$branchId}\nQuery: {$query}";

        try {
            $response = $this->httpClient->post('/v1/chat/completions', [
                'headers' => [
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'       => 'gpt-4o-mini',
                    'messages'    => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user',   'content' => $userPrompt],
                    ],
                    'temperature' => 0.1,
                    'max_tokens'  => 300,
                ],
            ]);

            $body    = json_decode($response->getBody()->getContents(), true);
            $content = $body['choices'][0]['message']['content'] ?? '{}';

            $cleaned = preg_replace('/^```(?:json)?\s*/i', '', trim($content));
            $cleaned = preg_replace('/\s*```$/', '', $cleaned);

            $result = json_decode(trim($cleaned), true);

            if (!is_array($result) || !isset($result['type'])) {
                return ['type' => 'error', 'message' => 'Unexpected response from assistant.'];
            }

            return $result;
        } catch (\Throwable $e) {
            Log::warning('ChatbotService: Staff query processing failed', ['error' => $e->getMessage()]);
            return ['type' => 'error', 'message' => 'Assistant is temporarily unavailable.'];
        }
    }
}
