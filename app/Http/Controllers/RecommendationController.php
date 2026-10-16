<?php

namespace App\Http\Controllers;

use App\Services\AITranslationService;
use App\Services\RecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'branch_id'   => 'nullable|integer|exists:branches,id',
        ]);

        abort_unless((int) $request->user()->customer?->id === (int) $validated['customer_id'], 403, 'Recommendations are only available for your customer account.');

        // Before a customer chooses a branch we deliberately return no
        // recommendations instead of generating an unscoped global list.
        if (empty($validated['branch_id'])) return response()->json([]);

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

        $user = $request->user();
        abort_unless(in_array(strtoupper((string) $user->type), ['ADMIN', 'MANAGER', 'STAFF', 'CASHIER', 'THERAPIST'], true), 403);
        if (strtoupper((string) $user->type) !== 'ADMIN') {
            abort_unless((int) $user->employee?->branch_id === (int) $validated['branch_id'], 403, 'Recommendations are limited to your assigned branch.');
        }

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
        abort_unless((int) $request->user()->customer?->id === $customerId, 403, 'You may only invalidate your own recommendations.');
        $this->service->invalidateCache($customerId);

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
