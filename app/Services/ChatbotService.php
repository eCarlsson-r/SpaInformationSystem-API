<?php

namespace App\Services;

use App\Ai\Agents\CustomerChatAgent;
use App\Ai\Agents\StaffChatAgent;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Sales;
use App\Models\Session;
use Carbon\Carbon;
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
        try {
            $response = (new StaffChatAgent)->prompt("Classify this query for a spa operations dashboard. Query: {$query}");

            $type = $response['type'] ?? 'error';
            if ($type !== 'data_response') return ['type' => $type];

            $intent = $response['intent'] ?? '';
            if (!in_array($intent, ['revenue_query', 'booking_query', 'staff_query', 'session_query'], true)) {
                return ['type' => 'error', 'message' => 'This query is not supported yet.'];
            }

            $branchId = isset($staffContext['branch_id']) ? (int) $staffContext['branch_id'] : null;
            [$start, $end, $period] = $this->resolvePeriod($query);
            $branchName = $branchId === null
                ? 'All branches'
                : (Branch::whereKey($branchId)->value('name') ?? 'Assigned branch');

            if ($intent === 'revenue_query') {
                $value = Sales::query()
                    ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                    ->when($branchId !== null, fn ($sales) => $sales->where('branch_id', $branchId))
                    ->sum('total');
                $formattedAnswer = "Revenue for {$period} at {$branchName}: IDR " . number_format((float) $value, 0);
            } elseif ($intent === 'booking_query') {
                $value = Session::query()
                    ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                    ->whereIn('status', ['waiting', 'ongoing', 'completed'])
                    ->when($branchId !== null, fn ($sessions) => $sessions->whereHas('bed.room', fn ($room) => $room->where('rooms.branch_id', $branchId)))
                    ->count();
                $formattedAnswer = "There were {$value} bookings for {$period} at {$branchName}.";
            } elseif ($intent === 'staff_query') {
                $value = Employee::query()
                    ->where('status', 'active')
                    ->when($branchId !== null, fn ($employees) => $employees->where('branch_id', $branchId))
                    ->count();
                $formattedAnswer = "There are {$value} active staff at {$branchName}.";
            } else {
                $statuses = Session::query()
                    ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                    ->when($branchId !== null, fn ($sessions) => $sessions->whereHas('bed.room', fn ($room) => $room->where('rooms.branch_id', $branchId)))
                    ->select('status', DB::raw('count(*) as total'))
                    ->groupBy('status')
                    ->pluck('total', 'status');
                $value = $statuses->sum();
                $summary = $statuses->map(fn ($count, $status) => "{$status}: {$count}")->implode(', ');
                $formattedAnswer = "There were {$value} sessions for {$period} at {$branchName}" . ($summary ? " ({$summary})." : '.');
            }

            return [
                'type' => 'data_response',
                'intent' => $intent,
                'value' => (string) $value,
                'period' => $period,
                'branch' => $branchName,
                'formattedAnswer' => $formattedAnswer,
            ];
        } catch (Throwable $e) {
            Log::warning('ChatbotService: Staff query processing failed', ['error' => $e->getMessage()]);
            return ['type' => 'error', 'message' => $e->getMessage()];
        }
    }

    /** @return array{Carbon, Carbon, string} */
    private function resolvePeriod(string $query): array
    {
        $today = Carbon::today();
        $normalized = strtolower($query);

        if (str_contains($normalized, 'yesterday')) {
            $start = $today->copy()->subDay();
            return [$start, $start->copy(), 'yesterday'];
        }
        if (str_contains($normalized, 'last 30 days')) {
            return [$today->copy()->subDays(29), $today, 'the last 30 days'];
        }
        if (str_contains($normalized, 'last week')) {
            $start = $today->copy()->startOfWeek()->subWeek();
            return [$start, $start->copy()->endOfWeek(), 'last week'];
        }
        if (str_contains($normalized, 'this week')) {
            return [$today->copy()->startOfWeek(), $today, 'this week'];
        }
        if (str_contains($normalized, 'last month')) {
            $start = $today->copy()->subMonthNoOverflow()->startOfMonth();
            return [$start, $start->copy()->endOfMonth(), 'last month'];
        }
        if (str_contains($normalized, 'today')) {
            return [$today, $today->copy(), 'today'];
        }

        return [$today->copy()->startOfMonth(), $today, 'this month'];
    }
}
