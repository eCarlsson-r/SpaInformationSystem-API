<?php

namespace App\Http\Controllers;

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
 * Requirements: 1.1, 1.4, 1.5, 2.1, 2.2, 2.5, 3.2
 */
class RecommendationController extends Controller
{
    public function __construct(private readonly RecommendationService $service) {}

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
}
