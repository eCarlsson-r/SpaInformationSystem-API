<?php

namespace App\Services;

use App\Ai\Agents\RecommendationAgent;
use App\Models\Session;
use App\Models\Treatment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * RecommendationService
 *
 * Generates personalized treatment recommendations using the Laravel AI SDK.
 * Results are cached in Redis per customer+branch for 24 hours.
 *
 * Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 2.1, 2.2, 2.3, 3.1, 3.3
 */
class RecommendationService
{
    private const CACHE_TTL_SECONDS = 86400; // 24 hours
    private const RECENCY_DAYS      = 90;

    /**
     * Get recommendations for a customer at a branch.
     *
     * @param  int    $customerId
     * @param  int    $branchId
     * @param  string $context  'customer' (max 5) or 'pos' (max 3)
     * @return array<int, array{treatment_id: int, rank: int, rationale: string}>
     */
    public function getRecommendations(int $customerId, int $branchId, string $context = 'customer'): array
    {
        $maxItems = $context === 'pos' ? 3 : 5;
        $cacheKey = "rec:{$customerId}:{$branchId}";

        // Requirement 3.3: check Redis cache first (24h TTL)
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return array_slice($cached, 0, $maxItems);
        }

        // Fetch recent booking history (last 90 days) — Requirement 3.1
        $recentBookings = Session::where('customer_id', $customerId)
            ->where('date', '>=', Carbon::now()->subDays(self::RECENCY_DAYS)->toDateString())
            ->where('status', 'completed')
            ->with('treatment')
            ->orderByDesc('date')
            ->get();

        // Requirement 1.3 / 2.3: fall back to popular treatments if < 3 bookings
        if ($recentBookings->count() < 3) {
            return $this->getPopularTreatments($branchId, $maxItems);
        }

        // Fetch available treatments at the branch — Requirement 1.6
        $today = Carbon::now()->format('D');
        $availableTreatments = Treatment::where(function ($q) use ($today) {
            $q->where('applicable_days', $today)
              ->orWhere('applicable_days', 'LIKE', "{$today},%")
              ->orWhere('applicable_days', 'LIKE', "%,{$today}")
              ->orWhere('applicable_days', 'LIKE', "%,{$today},%");
        })
            ->whereTime('applicable_time_start', '<=', Carbon::now())
            ->whereTime('applicable_time_end', '>=', Carbon::now())
            ->get();

        try {
            $historyText = $recentBookings->map(fn ($s) => "- {$s->treatment?->name} on {$s->date}")->implode("\n");
            $treatmentList = $availableTreatments->map(fn ($t) => "ID:{$t->id} {$t->name}")->implode(', ');

            $prompt = <<<PROMPT
Booking history:
{$historyText}

Available treatments: {$treatmentList}

Return the top {$maxItems} recommendations.
PROMPT;

            $response = (new RecommendationAgent)->prompt($prompt);

            // $response is a StructuredAgentResponse — access like an array
            $recommendations = $response['recommendations'] ?? [];

            if (empty($recommendations)) {
                throw new \RuntimeException('Empty recommendations from AI agent.');
            }

            // Cache the full result (5 items) for 24 hours
            Cache::put($cacheKey, $recommendations, self::CACHE_TTL_SECONDS);

            return array_slice($recommendations, 0, $maxItems);
        } catch (\Throwable $e) {
            Log::warning('RecommendationService: AI agent call failed, falling back to popular treatments', [
                'customer_id' => $customerId,
                'error'       => $e->getMessage(),
            ]);

            // Requirement 1.5 / 2.5: fall back silently on AI unavailability
            return $this->getPopularTreatments($branchId, $maxItems);
        }
    }

    /**
     * Invalidate the recommendation cache for a customer.
     *
     * Requirement: 3.2
     */
    public function invalidateCache(int $customerId, ?int $branchId = null): void
    {
        if ($branchId !== null) {
            Cache::forget("rec:{$customerId}:{$branchId}");
            return;
        }

        Cache::forget("rec:{$customerId}:*");
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Return globally popular treatments at the branch as a fallback.
     *
     * @return array<int, array{treatment_id: int, rank: int, rationale: string}>
     */
    private function getPopularTreatments(int $branchId, int $maxItems): array
    {
        $popular = Session::where('status', 'completed')
            ->selectRaw('treatment_id, COUNT(*) as booking_count')
            ->groupBy('treatment_id')
            ->orderByDesc('booking_count')
            ->limit($maxItems)
            ->pluck('treatment_id')
            ->toArray();

        return array_values(array_map(function ($treatmentId, $index) {
            return [
                'treatment' => Treatment::find($treatmentId),
                'rank'      => $index + 1,
                'rationale' => 'Popular treatment at this branch.',
            ];
        }, $popular, array_keys($popular)));
    }
}
