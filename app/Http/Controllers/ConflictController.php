<?php

namespace App\Http\Controllers;

use App\Models\Conflict;
use App\Models\Session;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ConflictController
 *
 * Handles conflict history, rescheduling, and dismissal endpoints.
 *
 * Routes:
 *   GET  /api/conflicts              — conflict history (manager only, filtered by branch + date range)
 *   POST /api/bookings/{id}/reschedule — customer applies a selected alternative slot
 *   POST /api/conflicts/{id}/dismiss   — customer dismisses a rescheduling suggestion
 *   GET  /api/conflicts/pending        — customer fetches persisted suggestions on login
 *
 * Requirements: 7.3, 7.4, 7.5, 7.6, 8.1, 8.2, 8.3
 */
class ConflictController extends Controller
{
    // =========================================================================
    // GET /api/conflicts — conflict history (manager only)
    // Requirements: 8.3
    // =========================================================================

    /**
     * Return all conflict records for the authenticated manager's branch,
     * optionally filtered by date range.
     *
     * Validates: Requirements 8.3
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->type !== 'MANAGER') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $employee = $user->employee;

        if (!$employee) {
            return response()->json(['message' => 'Employee profile not found'], 422);
        }

        $branchId = $employee->branch_id;

        $query = Conflict::where('branch_id', $branchId)
            ->orderByDesc('detection_timestamp');

        // Optional date range filter
        if ($request->filled('from')) {
            $query->where('detection_timestamp', '>=', Carbon::parse($request->input('from'))->startOfDay());
        }

        if ($request->filled('to')) {
            $query->where('detection_timestamp', '<=', Carbon::parse($request->input('to'))->endOfDay());
        }

        return response()->json($query->get());
    }

    // =========================================================================
    // POST /api/bookings/{id}/reschedule — customer applies selected slot
    // Requirements: 7.3, 8.2
    // =========================================================================

    /**
     * Apply a selected alternative slot to reschedule the booking.
     *
     * Updates the Session record with the new date/time/therapist/room,
     * and updates the Conflict record with resolution_action='accepted'.
     *
     * Validates: Requirements 7.3, 8.2
     */
    public function reschedule(Request $request, int $bookingId): JsonResponse
    {
        $user = $request->user();

        if ($user->type !== 'CUSTOMER') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $customer = $user->customer;

        if (!$customer) {
            return response()->json(['message' => 'Customer profile not found'], 422);
        }

        $session = Session::find($bookingId);

        if (!$session) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        // Ensure the booking belongs to this customer
        if ((int) $session->customer_id !== (int) $customer->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'date'         => 'required|date_format:Y-m-d',
            'start_time'   => 'required|date_format:H:i',
            'end_time'     => 'required|date_format:H:i',
            'therapist_id' => 'required|integer|exists:employees,id',
            'room_id'      => 'nullable|string',
        ]);

        // Update the session with the new slot
        $session->update([
            'date'        => $validated['date'],
            'start'       => $validated['start_time'],
            'end'         => $validated['end_time'],
            'employee_id' => $validated['therapist_id'],
        ]);

        // Update the conflict record — mark as accepted
        $conflict = Conflict::where('booking_id', $bookingId)
            ->where('resolution_status', 'pending')
            ->latest('detection_timestamp')
            ->first();

        if ($conflict) {
            $conflict->update([
                'resolution_status'    => 'accepted',
                'resolution_action'    => 'accepted',
                'resolution_timestamp' => Carbon::now(),
            ]);
        }

        return response()->json([
            'message' => 'Booking rescheduled successfully.',
            'session' => $session->fresh(),
        ]);
    }

    // =========================================================================
    // POST /api/conflicts/{id}/dismiss — customer dismisses a suggestion
    // Requirements: 7.4, 8.2
    // =========================================================================

    /**
     * Dismiss a rescheduling suggestion.
     *
     * Persists the dismissal so the suggestion is not re-displayed.
     *
     * Validates: Requirements 7.4, 8.2
     */
    public function dismiss(Request $request, int $conflictId): JsonResponse
    {
        $user = $request->user();

        if ($user->type !== 'CUSTOMER') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $customer = $user->customer;

        if (!$customer) {
            return response()->json(['message' => 'Customer profile not found'], 422);
        }

        $conflict = Conflict::find($conflictId);

        if (!$conflict) {
            return response()->json(['message' => 'Conflict not found'], 404);
        }

        // Verify the conflict belongs to a booking owned by this customer
        $booking = Session::find($conflict->booking_id);

        if (!$booking || (int) $booking->customer_id !== (int) $customer->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Persist the dismissal — prevents re-display (Requirement 7.4)
        $conflict->update([
            'resolution_status'    => 'dismissed',
            'resolution_action'    => 'dismissed',
            'resolution_timestamp' => Carbon::now(),
        ]);

        return response()->json(['message' => 'Suggestion dismissed.']);
    }

    // =========================================================================
    // GET /api/conflicts/pending — fetch persisted suggestions for offline customer
    // Requirements: 7.6
    // =========================================================================

    /**
     * Return pending (non-dismissed, non-accepted) rescheduling suggestions
     * for the authenticated customer. Called on login to surface offline suggestions.
     *
     * Validates: Requirement 7.6
     */
    public function pending(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->type !== 'CUSTOMER') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $customer = $user->customer;

        if (!$customer) {
            return response()->json(['message' => 'Customer profile not found'], 422);
        }

        // Find all pending conflicts for bookings belonging to this customer
        $pendingConflicts = Conflict::where('resolution_status', 'pending')
            ->whereHas('booking', function ($query) use ($customer) {
                $query->where('customer_id', $customer->id);
            })
            ->orderByDesc('detection_timestamp')
            ->get();

        return response()->json($pendingConflicts);
    }
}
