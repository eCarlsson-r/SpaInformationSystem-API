<?php

namespace App\Services;

use App\Ai\Agents\CustomerChatAgent;
use App\Ai\Agents\StaffChatAgent;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Messages\Message;

/**
 * ChatbotService
 *
 * Processes natural-language messages for both customer booking assistant
 * and staff operational query flows using the Laravel AI SDK.
 *
 * Requirements: 4.2, 4.3, 4.4, 4.5, 4.6, 4.7, 5.2, 5.3, 5.4, 5.5, 5.6
 */
class ChatbotService
{
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
        try {
            // Build conversation history as Message objects (Requirement 4.7)
            $messages = collect(array_slice($history, -9))
                ->map(fn ($msg) => new Message($msg['role'], $msg['content']))
                ->all();

            $response = (new CustomerChatAgent)
                ->withHistory($messages)
                ->prompt($message);

            // $response is a StructuredAgentResponse — access like an array
            $result = ['type' => $response['type'] ?? 'error'];

            if ($result['type'] === 'booking_intent' && isset($response['params'])) {
                $result['params'] = $response['params'];
            }

            if ($result['type'] === 'clarification') {
                $result['missingField'] = $response['missingField'] ?? null;
                $result['message']      = $response['message'] ?? null;
            }

            if ($result['type'] === 'error') {
                $result['message'] = $response['message'] ?? 'Unexpected response from assistant.';
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
        $role     = $staffContext['role']      ?? 'staff';
        $branchId = $staffContext['branch_id'] ?? null;

        $prompt = "Staff role: {$role}, Branch ID: {$branchId}\nQuery: {$query}";

        try {
            $response = (new StaffChatAgent)->prompt($prompt);

            // $response is a StructuredAgentResponse — access like an array
            $result = ['type' => $response['type'] ?? 'error'];

            if ($result['type'] === 'data_response') {
                $result['intent']          = $response['intent']          ?? null;
                $result['value']           = $response['value']           ?? null;
                $result['period']          = $response['period']          ?? null;
                $result['branch']          = $response['branch']          ?? null;
                $result['formattedAnswer'] = $response['formattedAnswer'] ?? null;
            }

            if ($result['type'] === 'error') {
                $result['message'] = $response['message'] ?? 'Unexpected response from assistant.';
            }

            return $result;
        } catch (\Throwable $e) {
            Log::warning('ChatbotService: Staff query processing failed', ['error' => $e->getMessage()]);
            return ['type' => 'error', 'message' => 'Assistant is temporarily unavailable.'];
        }
    }
}
