<?php

namespace App\Services;

use App\Ai\Agents\CustomerChatAgent;
use App\Ai\Agents\StaffChatAgent;
use App\Models\Sales;
use App\Models\SalesRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
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

            $type = $response['type'] ?? 'error';

            return match ($type) {
                'booking_intent' => [
                    'type'   => 'booking_intent',
                    'params' => $response['params'],
                ],
                'clarification' => [
                    'type'         => 'clarification',
                    'missingField' => $response['missingField'] ?: null,
                    'message'      => $response['message']      ?: null,
                ],
                'recommendation' => [
                    'type'    => 'recommendation',
                    'message' => $response['message'] ?: null,
                ],
                default => [
                    'type'    => 'error',
                    'message' => $response['message'] ?: 'Unexpected response from assistant.',
                ],
            };
        } catch (Throwable $e) {
            Log::warning('ChatbotService: Customer message processing failed', ['error' => $e->getMessage()]);
            return ['type' => 'error', 'message' => $e->getMessage()];
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
        $stats    = $this->fetchStaffStats($branchId);
        
        $prompt = "Staff role: {$role}, Branch ID: {$branchId}\n";
        $prompt .= "Current Statistics:\n{$stats}\n";
        $prompt .= "Query: {$query}";

        try {
            $response = (new StaffChatAgent)->prompt($prompt);

            $type = $response['type'] ?? 'error';

            if ($type === 'data_response') {
                return [
                    'type'            => 'data_response',
                    'intent'          => $response['intent']          ?: null,
                    'value'           => $response['value']           ?: null,
                    'period'          => $response['period']          ?: null,
                    'branch'          => $response['branch']          ?: null,
                    'formattedAnswer' => $response['formattedAnswer'] ?: null,
                ];
            }

            return ['type' => $type];
        } catch (Throwable $e) {
            Log::warning('ChatbotService: Staff query processing failed', ['error' => $e->getMessage()]);
            return ['type' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Fetch a summary of operational stats to provide as context to the staff agent.
     */
    private function fetchStaffStats(?int $branchId): string
    {
        $startOfMonth = now()->startOfMonth();
        
        $topTreatments = SalesRecord::whereHas('sales', function ($q) use ($branchId, $startOfMonth) {
                $q->where('date', '>=', $startOfMonth);
                if ($branchId) $q->where('branch_id', $branchId);
            })
            ->select('treatment_id', DB::raw('count(*) as count'))
            ->groupBy('treatment_id')
            ->orderByDesc('count')
            ->limit(3)
            ->with('treatment:id,name')
            ->get()
            ->map(fn($r) => "{$r->treatment->name} ({$r->count} sold)")
            ->implode(', ');

        $revenue = Sales::where('date', '>=', $startOfMonth);
        if ($branchId) $revenue->where('branch_id', $branchId);
        $revenue = $revenue->sum('total');

        $topCustomer = Sales::where('date', '>=', $startOfMonth)
            ->whereNotNull('customer_id');
        if ($branchId) $topCustomer->where('branch_id', $branchId);
        $topCustomer = $topCustomer->select('customer_id', DB::raw('count(*) as count'))
            ->groupBy('customer_id')
            ->orderByDesc('count')
            ->limit(1)
            ->with('customer:id,name')
            ->first();

        $customerStr = $topCustomer ? "{$topCustomer->customer->name} ({$topCustomer->count} visits)" : 'None yet';

        $topEmployee = Sales::where('date', '>=', $startOfMonth)
            ->whereNotNull('employee_id');
        if ($branchId) $topEmployee->where('branch_id', $branchId);
        $topEmployee = $topEmployee->select('employee_id', DB::raw('count(*) as count'))
            ->groupBy('employee_id')
            ->orderByDesc('count')
            ->limit(1)
            ->with('employee:id,name')
            ->first();

        $employeeStr = $topEmployee ? "{$topEmployee->employee->name} ({$topEmployee->count} sessions)" : 'None yet';

        return "Top treatments this month: " . ($topTreatments ?: 'None yet') . 
               "\nTotal revenue this month: " . number_format($revenue, 0) . " IDR" .
               "\nMost frequent customer this month: " . $customerStr .
               "\nTop employee this month: " . $employeeStr;
    }
}
