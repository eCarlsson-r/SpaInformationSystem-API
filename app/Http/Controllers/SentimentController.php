<?php

namespace App\Http\Controllers;

use App\Ai\Agents\SentimentSummaryAgent;
use App\Models\Feedback;
use App\Services\AITranslationService;
use Carbon\Carbon;
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
    use ResolvesLocale;

    /**
     * Maximum number of feedback records to include in the AI summary.
     */
    private const SUMMARY_RECORD_LIMIT = 50;

    /**
     * Maximum number of recent negative feedback records to surface.
     */
    private const RECENT_NEGATIVE_LIMIT = 5;

    public function __construct(private readonly AITranslationService $translator) {}

    /**
     * GET /api/ai/sentiment/dashboard
     *
     * Returns aggregated sentiment metrics for the manager dashboard.
     *
     * Requirements: 11.1, 11.2, 11.3, 11.4, 11.6
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        if (strtoupper($user->type) !== 'ADMIN') {
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

        $baseQuery = $this->buildBaseQuery($startDate, $branchId, $treatmentId, $therapistId);

        $averageScore = (float) (clone $baseQuery)->avg('feedbacks.sentiment_score') ?? 0.0;

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

        $timeSeries     = $this->buildTimeSeries(clone $baseQuery, $startDate, $period);
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
     * Calls the SentimentSummaryAgent to generate a ≤150-word summary of
     * the last 50 feedback records matching the selected filter.
     *
     * Requirements: 11.1, 11.5
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        if (strtoupper($user->type) !== 'ADMIN') {
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

        $records = $this->buildBaseQuery($startDate, $branchId, $treatmentId, $therapistId)
            ->select('feedbacks.rating', 'feedbacks.comment', 'feedbacks.sentiment_label', 'feedbacks.sentiment_score', 'feedbacks.submitted_at')
            ->orderByDesc('feedbacks.submitted_at')
            ->limit(self::SUMMARY_RECORD_LIMIT)
            ->get();

        if ($records->isEmpty()) {
            return response()->json(['summary' => 'No feedback records found for the selected filters.']);
        }

        $summary = $this->generateAiSummary($records->toArray());

        // Translate the summary for the requested locale (Requirement 8.1, 8.5)
        $locale  = $this->resolveLocale($request);
        $summary = $this->translator->translate($summary, $locale);

        return response()->json(['summary' => $summary]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function buildBaseQuery(Carbon $startDate, ?int $branchId, ?int $treatmentId, ?int $therapistId)
    {
        $query = Feedback::query()
            ->join('sessions', 'feedbacks.session_id', '=', 'sessions.id')
            ->join('employees', 'sessions.employee_id', '=', 'employees.id')
            ->where('feedbacks.analysis_status', 'completed')
            ->where('feedbacks.submitted_at', '>=', $startDate);

        if ($branchId !== null) {
            $query->where('employees.branch_id', $branchId);
        }
        if ($treatmentId !== null) {
            $query->where('sessions.treatment_id', $treatmentId);
        }
        if ($therapistId !== null) {
            $query->where('sessions.employee_id', $therapistId);
        }

        return $query;
    }

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
            $date     = Carbon::now()->subDays($i)->toDateString();
            $series[] = [
                'date'         => $date,
                'averageScore' => isset($rows[$date]) ? round((float) $rows[$date]->avg_score, 4) : 0.0,
            ];
        }

        return $series;
    }

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
     * Call the SentimentSummaryAgent to generate a ≤150-word summary.
     * Falls back to a statistical summary if the agent fails.
     *
     * Requirement 11.5
     */
    private function generateAiSummary(array $records): string
    {
        $feedbackText = collect($records)->map(function ($r, $i) {
            $label   = $r['sentiment_label'] ?? 'unknown';
            $score   = isset($r['sentiment_score']) ? number_format((float) $r['sentiment_score'], 2) : '0.00';
            $comment = $r['comment'] ?? '';
            return ($i + 1) . ". [{$label}, score: {$score}] \"{$comment}\"";
        })->implode("\n");

        $prompt = "Here are the most recent customer feedback records:\n\n{$feedbackText}\n\nProvide a summary in 150 words or fewer.";

        try {
            $summary = trim((string) (new SentimentSummaryAgent)->prompt($prompt));

            if (empty($summary)) {
                return $this->buildFallbackSummary($records);
            }

            return $summary;
        } catch (\Throwable $e) {
            Log::warning('SentimentController: AI summary failed, using fallback', ['error' => $e->getMessage()]);
            return $this->buildFallbackSummary($records);
        }
    }

    private function buildFallbackSummary(array $records): string
    {
        $total    = count($records);
        $positive = count(array_filter($records, fn ($r) => ($r['sentiment_label'] ?? '') === 'positive'));
        $neutral  = count(array_filter($records, fn ($r) => ($r['sentiment_label'] ?? '') === 'neutral'));
        $negative = count(array_filter($records, fn ($r) => ($r['sentiment_label'] ?? '') === 'negative'));
        $scores   = array_filter(array_column($records, 'sentiment_score'), fn ($s) => $s !== null);
        $avg      = count($scores) > 0 ? array_sum($scores) / count($scores) : 0.0;

        return sprintf(
            'Based on %d recent feedback records: %d positive, %d neutral, %d negative. Average sentiment score: %.2f.',
            $total, $positive, $neutral, $negative, $avg
        );
    }
}
