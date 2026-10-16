<?php

namespace App\Services;

use App\Ai\Agents\RecommendationAgent;
use App\Models\Session;
use App\Models\Treatment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RecommendationService
{
    private const CACHE_TTL_SECONDS = 86400;
    private const RECENCY_DAYS = 90;

    /** @return array<int, array{treatment: Treatment, rank: int, rationale: string}> */
    public function getRecommendations(int $customerId, int $branchId, string $context = 'customer'): array
    {
        $maxItems = $context === 'pos' ? 3 : 5;
        $now = Carbon::now();
        $today = $now->format('D');
        $eligible = Treatment::query()->where(function ($query) use ($today) {
            $query->where('applicable_days', $today)
                ->orWhere('applicable_days', 'LIKE', "{$today},%")
                ->orWhere('applicable_days', 'LIKE', "%,{$today}")
                ->orWhere('applicable_days', 'LIKE', "%,{$today},%");
        })->whereTime('applicable_time_start', '<=', $now->format('H:i:s'))
            ->whereTime('applicable_time_end', '>=', $now->format('H:i:s'))
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        if ($eligible->isEmpty()) return [];

        $eligibilityHash = hash('sha256', implode(',', $eligible->keys()->map(fn ($id) => (int) $id)->sort()->values()->all()));
        $generation = (int) Cache::get("rec:{$customerId}:{$branchId}:generation", 0);
        $cacheKey = "rec:{$customerId}:{$branchId}:{$context}:{$generation}:{$eligibilityHash}";
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) return array_slice($cached, 0, $maxItems);

        $branchHistory = fn ($query) => $query->whereHas('bed.room', fn ($room) => $room->where('rooms.branch_id', $branchId));
        $recentBookings = Session::query()
            ->where('customer_id', $customerId)
            ->where('date', '>=', Carbon::now()->subDays(self::RECENCY_DAYS)->toDateString())
            ->where('status', 'completed')
            ->where($branchHistory)
            ->with('treatment')
            ->orderByDesc('date')
            ->get();

        if ($recentBookings->count() < 3) {
            $recommendations = $this->getPopularTreatments($branchId, $maxItems, $eligible);
            Cache::put($cacheKey, $recommendations, self::CACHE_TTL_SECONDS);
            $this->rememberBranch($customerId, $branchId);
            return $recommendations;
        }

        try {
            $historyText = $recentBookings->map(fn ($session) => "- {$session->treatment?->name} on {$session->date}")->implode("\n");
            $treatmentList = $eligible->map(fn ($treatment) => "ID:{$treatment->id} {$treatment->name}")->implode(', ');
            $response = (new RecommendationAgent)->prompt(<<<PROMPT
Booking history:
{$historyText}

Available treatments: {$treatmentList}

Return the top {$maxItems} recommendations.
PROMPT);

            $recommendations = $this->normalize($response['recommendations'] ?? [], $eligible, $maxItems);
            if ($recommendations === []) throw new \RuntimeException('AI recommendations did not match eligible treatments.');

            Cache::put($cacheKey, $recommendations, self::CACHE_TTL_SECONDS);
            $this->rememberBranch($customerId, $branchId);
            return $recommendations;
        } catch (\Throwable $exception) {
            Log::warning('RecommendationService: AI call or response validation failed; using branch popularity.', [
                'customer_id' => $customerId,
                'branch_id' => $branchId,
                'error' => $exception->getMessage(),
            ]);

            $recommendations = $this->getPopularTreatments($branchId, $maxItems, $eligible);
            Cache::put($cacheKey, $recommendations, self::CACHE_TTL_SECONDS);
            $this->rememberBranch($customerId, $branchId);
            return $recommendations;
        }
    }

    private function normalize(mixed $items, $eligible, int $limit): array
    {
        if (!is_array($items)) return [];
        $result = [];
        $seen = [];
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $id = filter_var($item['treatment_id'] ?? null, FILTER_VALIDATE_INT);
            $rationale = trim((string) ($item['rationale'] ?? ''));
            if (!$id || !$eligible->has($id) || isset($seen[$id]) || $rationale === '' || str_word_count($rationale) > 20) continue;
            $seen[$id] = true;
            $result[] = ['treatment' => $eligible->get($id), 'rank' => count($result) + 1, 'rationale' => $rationale];
            if (count($result) >= $limit) break;
        }
        return $result;
    }

    public function invalidateCache(int $customerId, ?int $branchId = null): void
    {
        $branchIds = $branchId === null
            ? Cache::get("rec:customer:{$customerId}:branches", [])
            : [$branchId];
        foreach (array_unique(array_map('intval', $branchIds)) as $id) {
            $generationKey = "rec:{$customerId}:{$id}:generation";
            Cache::put($generationKey, (int) Cache::get($generationKey, 0) + 1, self::CACHE_TTL_SECONDS);
        }
        if ($branchId === null) Cache::forget("rec:customer:{$customerId}:branches");
    }

    private function rememberBranch(int $customerId, int $branchId): void
    {
        $key = "rec:customer:{$customerId}:branches";
        $update = function () use ($key, $branchId): void {
            $branches = Cache::get($key, []);
            $branches[] = $branchId;
            Cache::put($key, array_values(array_unique($branches)), self::CACHE_TTL_SECONDS);
        };

        // Cache stores with atomic locks serialize concurrent branch-list
        // updates; unsupported lock stores safely fall back to the update.
        try {
            Cache::lock("{$key}:lock", 5)->block(2, $update);
        } catch (\Throwable) {
            $update();
        }
    }

    private function getPopularTreatments(int $branchId, int $limit, $eligible): array
    {
        $today = Carbon::now()->format('D');
        $now = Carbon::now()->format('H:i:s');
        $popular = Session::query()->where('status', 'completed')
            ->whereHas('bed.room', fn ($room) => $room->where('rooms.branch_id', $branchId))
            ->whereHas('treatment', function ($query) use ($today, $now) {
                $query->where(function ($days) use ($today) {
                    $days->where('applicable_days', $today)
                        ->orWhere('applicable_days', 'LIKE', "{$today},%")
                        ->orWhere('applicable_days', 'LIKE', "%,{$today}")
                        ->orWhere('applicable_days', 'LIKE', "%,{$today},%");
                })->whereTime('applicable_time_start', '<=', $now)
                    ->whereTime('applicable_time_end', '>=', $now);
            })
            ->selectRaw('treatment_id, COUNT(*) as booking_count')
            ->groupBy('treatment_id')->orderByDesc('booking_count')->limit($limit)->get();

        $recommendations = $popular->values()->map(function ($row, $index) {
            $treatment = Treatment::find($row->treatment_id);
            return $treatment ? [
                'treatment' => $treatment,
                'rank' => $index + 1,
                'rationale' => 'Popular at your selected branch.',
            ] : null;
        })->filter()->values()->all();

        if ($recommendations !== []) return $recommendations;

        return $eligible->take($limit)->values()->map(fn (Treatment $treatment, int $index) => [
            'treatment' => $treatment,
            'rank' => $index + 1,
            'rationale' => 'Available during your selected day and time.',
        ])->all();
    }
}
