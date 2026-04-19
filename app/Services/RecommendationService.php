<?php

namespace App\Services;

use App\Models\Session;
use App\Models\Treatment;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * RecommendationService
 *
 * Generates personalized treatment recommendations using OpenAI.
 * Results are cached in Redis per customer+branch for 24 hours.
 *
 * Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 2.1, 2.2, 2.3, 3.1, 3.3
 */
class RecommendationService
{
    private const CACHE_TTL_SECONDS = 86400; // 24 hours
    private const RECENCY_DAYS      = 90;
    private const OPENAI_TIMEOUT    = 8.0;

    private Client $httpClient;

    public function __construct(?Client $httpClient = null)
    {
        $this->httpClient = $httpClient ?? new Client([
            'base_uri' => 'https://api.openai.com',
            'timeout'  => self::OPENAI_TIMEOUT,
        ]);
    }

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

        // Fetch available treatments at the branch — Requirement 1.6
        $today = Carbon::now()->format('D');
        $availableTreatments = Treatment::where(function($q) use ($today) {
            $q->where('applicable_days', $today)
              ->orWhere('applicable_days', 'LIKE', "{$today},%")
              ->orWhere('applicable_days', 'LIKE', "%,{$today}")
              ->orWhere('applicable_days', 'LIKE', "%,{$today},%");
        })
            ->whereTime('applicable_time_start', '<=', Carbon::now())
            ->whereTime('applicable_time_end', '>=', Carbon::now())->get();

        // Requirement 1.3 / 2.3: fall back to popular treatments if < 3 bookings
        if ($recentBookings->count() < 3) {
            return $this->getPopularTreatments($branchId, $maxItems);
        }

        // Call OpenAI for personalized recommendations
        try {
            $recommendations = $this->callOpenAI($recentBookings, $availableTreatments, $maxItems);

            // Cache the full result (5 items) for 24 hours
            Cache::put($cacheKey, $recommendations, self::CACHE_TTL_SECONDS);

            return array_slice($recommendations, 0, $maxItems);
        } catch (\Throwable $e) {
            Log::warning('RecommendationService: OpenAI call failed, falling back to popular treatments', [
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

        // Invalidate all branches for this customer by pattern (best-effort)
        // In production, use Redis SCAN to find all matching keys.
        // For simplicity, we rely on the BookingObserver passing the branch_id.
        Cache::forget("rec:{$customerId}:*");
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Call OpenAI to generate ranked treatment recommendations.
     *
     * @return array<int, array{treatment_id: int, rank: int, rationale: string}>
     */
    private function callOpenAI($recentBookings, $availableTreatments, int $maxItems): array
    {
        $apiKey = config('services.openai.api_key');

        if (empty($apiKey)) {
            throw new \RuntimeException('OpenAI API key is not configured.');
        }

        $historyText = $recentBookings->map(function ($s) {
            return "- {$s->treatment?->name} on {$s->date}";
        })->implode("\n");

        $treatmentList = $availableTreatments->map(function ($t) {
            return "ID:{$t->id} {$t->name}";
        })->implode(', ');

        $systemPrompt = <<<PROMPT
You are a spa treatment recommendation engine. Based on the customer's booking history, recommend up to {$maxItems} treatments from the available list. Return a JSON array of objects with fields: treatment_id (integer), rank (1-based integer), rationale (string, max 20 words). Return ONLY valid JSON, no markdown.
PROMPT;

        $userPrompt = "Booking history:\n{$historyText}\n\nAvailable treatments: {$treatmentList}\n\nReturn top {$maxItems} recommendations as JSON array.";

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
                'max_tokens'  => 400,
            ],
        ]);

        $body    = json_decode($response->getBody()->getContents(), true);
        $content = $body['choices'][0]['message']['content'] ?? '[]';

        // Strip markdown fences if present
        $cleaned = preg_replace('/^```(?:json)?\s*/i', '', trim($content));
        $cleaned = preg_replace('/\s*```$/', '', $cleaned);

        $items = json_decode(trim($cleaned), true);

        if (!is_array($items)) {
            throw new \RuntimeException('Invalid AI response format.');
        }

        return $items;
    }

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
