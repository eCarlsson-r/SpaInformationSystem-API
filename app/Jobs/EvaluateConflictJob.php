<?php

namespace App\Jobs;

use App\Events\ConflictDetected;
use App\Events\ReschedulingSuggestion;
use App\Models\Conflict;
use App\Models\Session;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Evaluates a newly created or updated booking for scheduling conflicts.
 *
 * Dispatched by BookingObserver on Session created/updated events.
 *
 * Requirements: 6.1, 6.2, 6.3, 6.4, 7.1, 7.5
 */
class EvaluateConflictJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 15;

    public function __construct(public readonly int $sessionId)
    {
        $this->onQueue('conflict-evaluation');
    }

    /**
     * Execute the job.
     *
     * Requirements: 6.1, 6.2, 6.3, 6.4, 7.1
     */
    public function handle(): void
    {
        $session = Session::find($this->sessionId);

        if (!$session) {
            Log::warning('EvaluateConflictJob: Session not found', ['session_id' => $this->sessionId]);
            return;
        }

        // Only evaluate sessions that have a scheduled time
        if (!$session->date || !$session->start || !$session->end) {
            return;
        }

        // Find overlapping sessions for the same employee (therapist conflict)
        $overlapping = Session::where('id', '!=', $session->id)
            ->where('date', $session->date)
            ->where('employee_id', $session->employee_id)
            ->where('status', '!=', 'cancelled')
            ->where(function ($query) use ($session) {
                // Overlap condition: session1.start < session2.end AND session2.start < session1.end
                $query->where('start', '<', $session->end)
                      ->where('end', '>', $session->start);
            })
            ->first();

        if (!$overlapping) {
            // Also check room conflict via bed
            $overlapping = Session::where('id', '!=', $session->id)
                ->where('date', $session->date)
                ->where('bed_id', $session->bed_id)
                ->where('status', '!=', 'cancelled')
                ->where(function ($query) use ($session) {
                    $query->where('start', '<', $session->end)
                          ->where('end', '>', $session->start);
                })
                ->first();

            $conflictType = 'room';
        } else {
            $conflictType = 'therapist';
        }

        if (!$overlapping) {
            return;
        }

        // Determine branch_id from the session's employee
        $branchId = $session->employee?->branch_id ?? 0;

        // Create the Conflict record
        $conflict = Conflict::create([
            'booking_id'             => $session->id,
            'conflicting_booking_id' => $overlapping->id,
            'conflict_type'          => $conflictType,
            'detection_timestamp'    => Carbon::now(),
            'resolution_status'      => 'pending',
            'resolution_action'      => null,
            'resolution_timestamp'   => null,
            'alternative_slots'      => [],
            'branch_id'              => $branchId,
        ]);

        // Broadcast ConflictDetected to SpaCashier staff (private-branch channel)
        // Requirement: 6.4
        try {
            broadcast(new ConflictDetected($conflict, $session, $overlapping));
        } catch (\Throwable $e) {
            Log::warning('EvaluateConflictJob: Failed to broadcast ConflictDetected', [
                'conflict_id' => $conflict->id,
                'error'       => $e->getMessage(),
            ]);
        }

        // Broadcast ReschedulingSuggestion to the affected customer (private-customer channel)
        // Requirement: 7.1
        $customerId = $session->customer_id;
        if ($customerId) {
            try {
                broadcast(new ReschedulingSuggestion($conflict, $session, (int) $customerId));
            } catch (\Throwable $e) {
                Log::warning('EvaluateConflictJob: Failed to broadcast ReschedulingSuggestion', [
                    'conflict_id' => $conflict->id,
                    'customer_id' => $customerId,
                    'error'       => $e->getMessage(),
                ]);
            }
        }
    }
}
