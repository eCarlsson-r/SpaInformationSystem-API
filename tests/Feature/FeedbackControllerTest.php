<?php

namespace Tests\Feature;

use App\Jobs\SentimentAnalysisJob;
use App\Models\Bed;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Feedback;
use App\Models\Room;
use App\Models\Session;
use App\Models\Treatment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Feature tests for FeedbackController (POST /api/feedback).
 *
 * Validates: Requirements 9.1, 9.2, 9.3, 9.4, 9.5, 9.6, 9.7
 */
class FeedbackControllerTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Create a customer User with a linked Customer record. */
    private function createCustomer(): array
    {
        $user     = User::factory()->create(['type' => 'CUSTOMER']);
        $customer = Customer::factory()->create(['user_id' => $user->id]);
        return [$user, $customer];
    }

    /** Create a completed Session for the given customer, completed N hours ago. */
    private function createCompletedSession(int $customerId, int $hoursAgo = 1): Session
    {
        $branch    = Branch::factory()->create();
        $room      = Room::factory()->create(['branch_id' => $branch->id]);
        $bed       = Bed::factory()->create(['room_id' => $room->id]);
        $employee  = Employee::factory()->create(['branch_id' => $branch->id]);
        $treatment = Treatment::factory()->create();

        $completedAt = Carbon::now()->subHours($hoursAgo);

        return Session::factory()->create([
            'customer_id'  => $customerId,
            'employee_id'  => $employee->id,
            'bed_id'       => $bed->id,
            'treatment_id' => $treatment->id,
            'date'         => $completedAt->toDateString(),
            'end'          => $completedAt->format('H:i:s'),
            'status'       => 'completed',
        ]);
    }

    // =========================================================================
    // Happy path — 201 Created
    // =========================================================================

    /**
     * A customer can submit feedback for a completed session within 48 hours.
     * The response is 201 and a SentimentAnalysisJob is dispatched.
     *
     * Validates: Requirements 9.3, 9.4
     */
    public function test_customer_can_submit_feedback_within_48h_window(): void
    {
        Queue::fake();

        [$user, $customer] = $this->createCustomer();
        $session = $this->createCompletedSession($customer->id, hoursAgo: 2);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/feedback', [
            'session_id' => $session->id,
            'rating'     => 5,
            'comment'    => 'Excellent service!',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('feedbacks', [
            'session_id'  => $session->id,
            'customer_id' => $customer->id,
            'rating'      => 5,
            'comment'     => 'Excellent service!',
        ]);

        Queue::assertPushedOn('sentiment-analysis', SentimentAnalysisJob::class);
    }

    /**
     * Feedback submitted exactly at the boundary (just under 48h) is accepted.
     *
     * Validates: Requirement 9.4
     */
    public function test_feedback_accepted_just_within_48h_boundary(): void
    {
        Queue::fake();

        [$user, $customer] = $this->createCustomer();
        $session = $this->createCompletedSession($customer->id, hoursAgo: 47);

        Sanctum::actingAs($user);

        $this->postJson('/api/feedback', [
            'session_id' => $session->id,
            'rating'     => 3,
            'comment'    => 'Good session.',
        ])->assertStatus(201);
    }

    // =========================================================================
    // 422 — feedback_window_closed (Requirements 9.4, 9.5)
    // =========================================================================

    /**
     * Feedback submitted after the 48-hour window is rejected with 422
     * and error code 'feedback_window_closed'.
     *
     * Validates: Requirements 9.4, 9.5
     */
    public function test_feedback_rejected_after_48h_window(): void
    {
        Queue::fake();

        [$user, $customer] = $this->createCustomer();
        $session = $this->createCompletedSession($customer->id, hoursAgo: 49);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/feedback', [
            'session_id' => $session->id,
            'rating'     => 4,
            'comment'    => 'Late feedback.',
        ]);

        $response->assertStatus(422)
                 ->assertJson(['error' => 'feedback_window_closed']);

        Queue::assertNotPushed(SentimentAnalysisJob::class);
    }

    // =========================================================================
    // 409 — feedback_already_submitted (Requirements 9.6, 9.7)
    // =========================================================================

    /**
     * A second feedback submission for the same customer + session is rejected
     * with 409 and error code 'feedback_already_submitted'.
     *
     * Validates: Requirements 9.6, 9.7
     */
    public function test_duplicate_feedback_rejected_with_409(): void
    {
        Queue::fake();

        [$user, $customer] = $this->createCustomer();
        $session = $this->createCompletedSession($customer->id, hoursAgo: 1);

        // First submission
        Feedback::create([
            'session_id'   => $session->id,
            'customer_id'  => $customer->id,
            'rating'       => 4,
            'comment'      => 'First feedback.',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($user);

        // Second submission attempt
        $response = $this->postJson('/api/feedback', [
            'session_id' => $session->id,
            'rating'     => 5,
            'comment'    => 'Trying again.',
        ]);

        $response->assertStatus(409)
                 ->assertJson(['error' => 'feedback_already_submitted']);

        // Only one feedback record should exist
        $this->assertDatabaseCount('feedbacks', 1);
    }

    // =========================================================================
    // Authorization checks
    // =========================================================================

    /**
     * A non-customer (staff) user receives 403.
     */
    public function test_staff_user_receives_403(): void
    {
        $user = User::factory()->create(['type' => 'CASHIER']);
        Sanctum::actingAs($user);

        $this->postJson('/api/feedback', [
            'session_id' => 1,
            'rating'     => 5,
            'comment'    => 'Test',
        ])->assertStatus(403);
    }

    /**
     * A customer cannot submit feedback for another customer's session.
     */
    public function test_customer_cannot_submit_feedback_for_another_customers_session(): void
    {
        Queue::fake();

        [$userA, $customerA] = $this->createCustomer();
        [$userB]             = $this->createCustomer();

        $session = $this->createCompletedSession($customerA->id, hoursAgo: 1);

        Sanctum::actingAs($userB);

        $this->postJson('/api/feedback', [
            'session_id' => $session->id,
            'rating'     => 5,
            'comment'    => 'Not my session.',
        ])->assertStatus(403);
    }

    // =========================================================================
    // Validation errors
    // =========================================================================

    /**
     * Missing required fields return 422 validation error.
     *
     * Validates: Requirement 9.3
     */
    public function test_missing_fields_return_422_validation_error(): void
    {
        [$user] = $this->createCustomer();
        Sanctum::actingAs($user);

        $this->postJson('/api/feedback', [])->assertStatus(422);
    }

    /**
     * Rating outside 1–5 range returns 422 validation error.
     *
     * Validates: Requirement 9.2
     */
    public function test_invalid_rating_returns_422(): void
    {
        [$user, $customer] = $this->createCustomer();
        $session = $this->createCompletedSession($customer->id, hoursAgo: 1);

        Sanctum::actingAs($user);

        $this->postJson('/api/feedback', [
            'session_id' => $session->id,
            'rating'     => 6,
            'comment'    => 'Test',
        ])->assertStatus(422);
    }

    /**
     * Comment exceeding 1000 characters returns 422 validation error.
     *
     * Validates: Requirement 9.2
     */
    public function test_comment_exceeding_1000_chars_returns_422(): void
    {
        [$user, $customer] = $this->createCustomer();
        $session = $this->createCompletedSession($customer->id, hoursAgo: 1);

        Sanctum::actingAs($user);

        $this->postJson('/api/feedback', [
            'session_id' => $session->id,
            'rating'     => 4,
            'comment'    => str_repeat('a', 1001),
        ])->assertStatus(422);
    }

    /**
     * Unauthenticated request returns 401.
     */
    public function test_unauthenticated_request_returns_401(): void
    {
        $this->postJson('/api/feedback', [
            'session_id' => 1,
            'rating'     => 5,
            'comment'    => 'Test',
        ])->assertStatus(401);
    }
}
