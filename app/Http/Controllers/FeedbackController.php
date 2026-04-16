<?php

namespace App\Http\Controllers;

use App\Jobs\SentimentAnalysisJob;
use App\Models\Feedback;
use App\Models\Session;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    /**
     * POST /api/feedback
     *
     * Submit post-session feedback for an authenticated customer.
     *
     * Validations:
     *  - session exists and belongs to the authenticated customer
     *  - submission is within 48 hours of session completion (date + end time)
     *  - no duplicate feedback for the same customer + session pair
     *
     * On success: persists Feedback, dispatches SentimentAnalysisJob, returns 201.
     *
     * Requirements: 9.1, 9.2, 9.3, 9.4, 9.5, 9.6, 9.7
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->type !== 'CUSTOMER') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $customer = $user->customer;

        if (!$customer) {
            return response()->json(['message' => 'Customer profile not found'], 422);
        }

        $validated = $request->validate([
            'session_id' => 'required|integer|exists:sessions,id',
            'rating'     => 'required|integer|min:1|max:5',
            'comment'    => 'required|string|max:1000',
        ]);

        $session = Session::find($validated['session_id']);

        // Ensure the session belongs to this customer
        if ((int) $session->customer_id !== (int) $customer->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Determine session completion timestamp from date + end time
        // The sessions table stores date (DATE) and end (TIME) separately.
        // A session is considered completed when status = 'completed'.
        if ($session->status !== 'completed' || !$session->end) {
            return response()->json(['message' => 'Session is not completed yet'], 422);
        }

        $completedAt = Carbon::parse($session->date . ' ' . $session->end);

        // Requirement 9.4 / 9.5: reject if outside 48-hour window
        if (Carbon::now()->diffInHours($completedAt, false) < -48) {
            return response()->json(['error' => 'feedback_window_closed'], 422);
        }

        // Requirement 9.6 / 9.7: reject duplicate feedback for same customer + session
        $duplicate = Feedback::where('session_id', $validated['session_id'])
            ->where('customer_id', $customer->id)
            ->exists();

        if ($duplicate) {
            return response()->json(['error' => 'feedback_already_submitted'], 409);
        }

        // Persist the feedback record
        $feedback = Feedback::create([
            'session_id'   => $validated['session_id'],
            'customer_id'  => $customer->id,
            'rating'       => $validated['rating'],
            'comment'      => $validated['comment'],
            'submitted_at' => Carbon::now(),
        ]);

        // Dispatch sentiment analysis to Redis queue (non-blocking)
        SentimentAnalysisJob::dispatch($feedback->id)->onQueue('sentiment-analysis');

        return response()->json($feedback, 201);
    }
}
