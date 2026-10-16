<?php

namespace App\Observers;

use App\Jobs\EvaluateConflictJob;
use App\Models\Session;
use Illuminate\Support\Facades\Log;
use App\Services\RecommendationService;

/**
 * Observes Session (Booking) model events and triggers AI-related side effects.
 *
 * On created:
 *   - Dispatches EvaluateConflictJob to the conflict-evaluation queue (Requirement 6.1)
 *   - Invalidates the recommendation cache for the customer directly (Requirement 3.2)
 *
 * On updated:
 *   - Dispatches EvaluateConflictJob to the conflict-evaluation queue (Requirement 6.1)
 */
class BookingObserver
{
    /**
     * Handle the Session "created" event.
     *
     * Requirements: 3.2, 6.1
     */
    public function created(Session $session): void
    {
        // Requirement 6.1: evaluate for scheduling conflicts
        EvaluateConflictJob::dispatch($session->id)->onQueue('conflict-evaluation')->afterCommit();

        // Requirement 3.2: invalidate recommendation cache for this customer
        $this->invalidateRecommendationCache($session);
    }

    /**
     * Handle the Session "updated" event.
     *
     * Requirement: 6.1
     */
    public function updated(Session $session): void
    {
        // Only re-evaluate conflicts if scheduling fields changed
        if ($session->wasChanged(['date', 'start', 'end', 'employee_id', 'bed_id'])) {
            EvaluateConflictJob::dispatch($session->id)->onQueue('conflict-evaluation')->afterCommit();
        }

        if ($session->wasChanged('status') && $session->status === 'completed') {
            $this->invalidateRecommendationCache($session);
        }
    }

    /**
     * Invalidate the recommendation cache for the session's customer.
     *
     * Requirement: 3.2
     */
    private function invalidateRecommendationCache(Session $session): void
    {
        $customerId = $session->customer_id;

        if (!$customerId) {
            return;
        }

        try {
            $branchId = $session->bed?->room?->branch_id;
            app(RecommendationService::class)->invalidateCache((int) $customerId, $branchId ? (int) $branchId : null);
        } catch (\Throwable $e) {
            Log::warning('BookingObserver: Failed to invalidate recommendation cache', [
                'customer_id' => $customerId,
                'error'       => $e->getMessage(),
            ]);
        }
    }
}
