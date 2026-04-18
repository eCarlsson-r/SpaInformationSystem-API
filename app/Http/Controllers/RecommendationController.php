<?php

namespace App\Http\Controllers;

use App\Services\AITranslationService;
use App\Services\RecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * RecommendationController
 *
 * Exposes AI treatment recommendation endpoints for SpaBooking (customer)
 * and SpaCashier (POS staff).
 *
 * Requirements: 1.1, 1.4, 1.5, 2.1, 2.2, 2.5, 3.2, 8.1, 8.5
 */
class RecommendationController extends Controller
{
    use ResolvesLocale;

    public function __construct(
        private readonly RecommendationService $service,
        private readonly AITranslationService $translator,
    ) {}

    /**
     * GET /api/ai/recommendations
     *
     * Customer-facing recommendations (SpaBooking). Returns up to 5 items.
     *
     * Requirements: 1.1, 1.4, 1.5
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'branch_id'   => 'required|integer|exists:branches,id',
        ]);

        $recommendations = $this->service->getRecommendations(
            (int) $validated['customer_id'],
            (int) $validated['branch_id'],
            'customer'
        );

        // Translate rationale fields for the requested locale
        $locale = $this->resolveLocale($request);
        $recommendations = $this->translateRationales($recommendations, $locale);

        return response()->json($recommendations);
    }

    /**
     * GET /api/ai/recommendations/pos
     *
     * Staff-facing POS recommendations (SpaCashier). Returns up to 3 items.
     *
     * Requirements: 2.1, 2.2, 2.5
     */
    public function pos(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'branch_id'   => 'required|integer|exists:branches,id',
        ]);

        $recommendations = $this->service->getRecommendations(
            (int) $validated['customer_id'],
            (int) $validated['branch_id'],
            'pos'
        );

        // Translate rationale fields for the requested locale
        $locale = $this->resolveLocale($request);
        $recommendations = $this->translateRationales($recommendations, $locale);

        return response()->json($recommendations);
    }

    /**
     * POST /api/ai/recommendations/invalidate/{customerId}
     *
     * Invalidates the recommendation cache for a customer.
     * Called by BookingObserver on new booking creation.
     *
     * Requirement: 3.2
     */
    public function invalidate(Request $request, int $customerId): JsonResponse
    {
        $branchId = $request->input('branch_id');

        // Clear the Redis cache key rec:{customerId}:{branchId}
        if ($branchId !== null) {
            Cache::forget("rec:{$customerId}:{$branchId}");
        } else {
            // Invalidate all known branch keys for this customer (best-effort)
            $this->service->invalidateCache($customerId);
        }

        return response()->json(['message' => 'Cache invalidated.']);
    }

    /**
     * Translate the rationale text in each recommendation item.
     */
    private function translateRationales(array $recommendations, string $locale): array
    {
        return array_map(function (array $item) use ($locale) {
            if (isset($item['rationale'])) {
                $item['rationale'] = $this->translator->translate($item['rationale'], $locale);
            }
            return $item;
        }, $recommendations);
    }
}
