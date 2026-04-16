<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use App\Models\Session;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * SentimentController
 *
 * Provides sentiment analytics endpoints for the manager dashboard.
 *
 * Requirements: 11.1, 11.2, 11.3, 11.4, 11.5, 11.6
 */
class SentimentController extends Controller
{
    /**
     * OpenAI request timeout in seconds.
     */
    private const OPENAI_TIMEOUT = 10.0;

    /**
     * Maximum number of feedback records to include in the AI summary.
     */
    private const SUMMARY_RECORD_LIMIT = 50;

    /**
     * Maximum number of recent negative feedback records to surface.
     */
    private const RECENT_NEGATIVE_LIMIT = 5;

    private Client $httpClient;

    public function __construct(?Client $httpClient = null)
    {
        $this->httpClient = $httpClient ?? new Client([
            'base_uri' => 'https://api.openai.com',
            'timeout'  => self::OPENAI_TIMEOUT,
        ]);
    }

    /**
     * GET /api/ai/sentiment/dashboard
     *
     * Returns aggregated sentiment metrics for the manager dashboard.
     *
     * Query params:
     *   branch_id    integer  optional  Filter by branch.
     *   treatment_id integer  optional  Filter by treatment.
     *   therapist_id integer  optional  Filter by therapist (employee).
     *   period       integer  optional  7, 30, or 90 days (default: 30).
     *
     * Response:
     *   averageScore      float
     *   labelDistribution { positive: int, neutral: int, negative: int }
     *   timeSeries        [{ date: string, averageScore: float }]
     *   recentNegative    [{ customerFirstName, treatmentName, sentimentScore, comment }] (max 5)
     *
     * Requirements: 11.1, 11.2, 11.3, 11.4, 11.6
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        // Requirement 11.1: manager role only
        if (strtoupper($user->type) !== 'MANAGER') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'branch_id'    => 'nullable|integer|exists:branches,id',
            'treatment_id' => 'nullable|integer|exists:treatments,id',
            'therapist_id' => 'nullable|integer|exists:employees,id',
            'period'       => 'nullable|integer|in:7,30,90',
        ]);

        $period      = (int) ($validated['period'] ?? 30);
        $branchId    = isset($validated['branch_id'])    ? (int) $validated['branch_id']    : null;
        $treatmentId = isset($validated['treatment_id']) ? (int) $validated['treatment_id'] : null;
        $therapistId = isset($validated['therapist_id']) ? (int) $validated['therapist_id'] : null;

        $startDate = Carbon::now()->subDays($period)->startOfDay();

        // Build the base query for completed-analysis feedbacks within the period
        $baseQuery = $this->buildBaseQuery($startDate, $branchId, $treatmentId, $therapistId);

        // --- Average score ---
        $averageScore = (float) (clone $baseQuery)->avg('feedbacks.sentiment_score') ?? 0.0;

        // --- Label distribution ---
        $distribution = (clone $baseQuery)
            ->selectRaw('sentiment_label, COUNT(*) as count')
            ->groupBy('sentiment_label')
            ->pluck('count', 'sentiment_label')
            ->toArray();

        $labelDistribution = [
            'positive' => (int) ($distribution['positive'] ?? 0),
            'neutral'  => (int) ($distribution['neutral']  ?? 0),
            'negative' => (int) ($distribution['negative'] ?? 0),
        ];

        // --- Time series: daily average score over the period ---
        $timeSeries = $this->buildTimeSeries(clone $baseQuery, $startDate, $period);

        // --- Recent negative feedback (top 5 most recent) ---
        $recentNegative = $this->buildRecentNegative(clone $baseQuery);

        return response()->json([
            'averageScore'      => round($averageScore, 4),
            'labelDistribution' => $labelDistribution,
            'timeSeries'        => $timeSeries,
            'recentNegative'    => $recentNegative,
        ]);
    }

    /**
     * GET /api/ai/sentiment/summary
     *
     * Calls OpenAI to generate a ≤150-word summary of the last 50 feedback records
     * matching the selected filter.
     *
     * Query params: same as dashboard (branch_id, treatment_id, therapist_id, period).
     *
     * Response:
     *   summary  string  (max 150 words)
     *
     * Requirements: 11.1, 11.5
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        // Requirement 11.1: manager role only
        if (strtoupper($user->type) !== 'MANAGER') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'branch_id'    => 'nullable|integer|exists:branches,id',
            'treatment_id' => 'nullable|integer|exists:treatments,id',
            'therapist_id' => 'nullable|integer|exists:employees,id',
            'period'       => 'nullable|integer|in:7,30,90',
        ]);

        $period      = (int) ($validated['period'] ?? 30);
        $branchId    = isset($validated['branch_id'])    ? (int) $validated['branch_id']    : null;
        $treatmentId = isset($validated['treatment_id']) ? (int) $validated['treatment_id'] : null;
        $therapistId = isset($validated['therapist_id']) ? (int) $validated['therapist_id'] : null;

        $startDate = Carbon::now()->subDays($period)->startOfDay();

        // Fetch last 50 feedback records for the selected filter
        $records = $this->buildBaseQuery($startDate, $branchId, $treatmentId, $therapistId)
            ->select('feedbacks.rating', 'feedbacks.comment', 'feedbacks.sentiment_label', 'feedbacks.sentiment_score', 'feedbacks.submitted_at')
            ->orderByDesc('feedbacks.submitted_at')
            ->limit(self::SUMMARY_RECORD_LIMIT)
            ->get();

        if ($records->isEmpty()) {
            return response()->json(['summary' => 'No feedback records found for the selected filters.']);
        }

        $summary = $this->generateAiSummary($records->toArray());

        return response()->json(['summary' => $summary]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Build the base Eloquent query for feedbacks, joining sessions and employees
     * to support branch/treatment/therapist filtering.
     *
     * Only includes feedbacks with analysis_status = 'completed'.
     */
    private function buildBaseQuery(
        Carbon $startDate,
        ?int $branchId,
        ?int $treatmentId,
        ?int $therapistId
    ) {
        $query = Feedback::query()
            ->join('sessions', 'feedbacks.session_id', '=', 'sessions.id')
            ->join('employees', 'sessions.employee_id', '=', 'employees.id')
            ->where('feedbacks.analysis_status', 'completed')
            ->where('feedbacks.submitted_at', '>=', $startDate);

        // Requirement 11.3: filter by branch
        if ($branchId !== null) {
            $query->where('employees.branch_id', $branchId);
        }

        // Requirement 11.3: filter by treatment
        if ($treatmentId !== null) {
            $query->where('sessions.treatment_id', $treatmentId);
        }

        // Requirement 11.3: filter by therapist
        if ($therapistId !== null) {
            $query->where('sessions.employee_id', $therapistId);
        }

        return $query;
    }

    /**
     * Build a daily time-series array of average sentiment scores.
     *
     * @return array<int, array{date: string, averageScore: float}>
     */
    private function buildTimeSeries($query, Carbon $startDate, int $period): array
    {
        $rows = (clone $query)
            ->selectRaw('DATE(feedbacks.submitted_at) as date, AVG(feedbacks.sentiment_score) as avg_score')
            ->groupByRaw('DATE(feedbacks.submitted_at)')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $series = [];
        for ($i = $period - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->toDateString();
            $series[] = [
                'date'         => $date,
                'averageScore' => isset($rows[$date]) ? round((float) $rows[$date]->avg_score, 4) : 0.0,
            ];
        }

        return $series;
    }

    /**
     * Build the top-5 most recent negative feedback records.
     *
     * @return array<int, array{customerFirstName: string, treatmentName: string, sentimentScore: float, comment: string}>
     */
    private function buildRecentNegative($query): array
    {
        $records = (clone $query)
            ->join('customers', 'feedbacks.customer_id', '=', 'customers.id')
            ->join('treatments', 'sessions.treatment_id', '=', 'treatments.id')
            ->where('feedbacks.sentiment_label', 'negative')
            ->select(
                'customers.name as customer_name',
                'treatments.name as treatment_name',
                'feedbacks.sentiment_score',
                'feedbacks.comment',
                'feedbacks.submitted_at'
            )
            ->orderByDesc('feedbacks.submitted_at')
            ->limit(self::RECENT_NEGATIVE_LIMIT)
            ->get();

        return $records->map(function ($row) {
            // Extract first name from the customer's full name
            $firstName = explode(' ', trim($row->customer_name ?? ''))[0] ?? '';

            return [
                'customerFirstName' => $firstName,
                'treatmentName'     => $row->treatment_name ?? '',
                'sentimentScore'    => (float) $row->sentiment_score,
                'comment'           => $row->comment ?? '',
            ];
        })->toArray();
    }

    /**
     * Call OpenAI to generate a ≤150-word summary of the provided feedback records.
     *
     * Requirement 11.5
     */
    private function generateAiSummary(array $records): string
    {
        $apiKey = config('services.openai.api_key');

        if (empty($apiKey)) {
            return $this->buildFallbackSummary($records);
        }

        // Build a compact text representation of the feedback records
        $feedbackText = collect($records)->map(function ($r, $i) {
            $label = $r['sentiment_label'] ?? 'unknown';
            $score = isset($r['sentiment_score']) ? number_format((float) $r['sentiment_score'], 2) : '0.00';
            $comment = $r['comment'] ?? '';
            return ($i + 1) . ". [{$label}, score: {$score}] \"{$comment}\"";
        })->implode("\n");

        $systemPrompt = <<<PROMPT
You are a customer satisfaction analyst for a spa business. Summarize the following customer feedback records in 150 words or fewer. Focus on overall sentiment trends, common themes, and any notable positive or negative patterns. Be concise and actionable.
PROMPT;

        $userPrompt = "Here are the most recent customer feedback records:\n\n{$feedbackText}\n\nProvide a summary in 150 words or fewer.";

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
                    'temperature' => 0.3,
                    'max_tokens'  => 250,
                ],
            ]);

            $body    = json_decode($response->getBody()->getContents(), true);
            $summary = trim($body['choices'][0]['message']['content'] ?? '');

            if (empty($summary)) {
                return $this->buildFallbackSummary($records);
            }

            return $summary;
        } catch (GuzzleException $e) {
            Log::warning('SentimentController: OpenAI summary request failed', ['error' => $e->getMessage()]);
            return $this->buildFallbackSummary($records);
        } catch (\Throwable $e) {
            Log::error('SentimentController: Unexpected error generating summary', ['error' => $e->getMessage()]);
            return $this->buildFallbackSummary($records);
        }
    }

    /**
     * Build a simple fallback summary without AI when OpenAI is unavailable.
     */
    private function buildFallbackSummary(array $records): string
    {
        $total    = count($records);
        $positive = count(array_filter($records, fn ($r) => ($r['sentiment_label'] ?? '') === 'positive'));
        $neutral  = count(array_filter($records, fn ($r) => ($r['sentiment_label'] ?? '') === 'neutral'));
        $negative = count(array_filter($records, fn ($r) => ($r['sentiment_label'] ?? '') === 'negative'));

        $scores = array_filter(array_column($records, 'sentiment_score'), fn ($s) => $s !== null);
        $avg    = count($scores) > 0 ? array_sum($scores) / count($scores) : 0.0;

        return sprintf(
            'Based on %d recent feedback records: %d positive, %d neutral, %d negative. Average sentiment score: %.2f.',
            $total,
            $positive,
            $neutral,
            $negative,
            $avg
        );
    }
}
