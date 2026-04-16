<?php

namespace Tests\Unit;

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
 * Property-based tests for FeedbackController (POST /api/feedback).
 *
 * Eris is not available in this project; properties are verified using
 * PHPUnit data providers that generate 100+ random inputs per property.
 *
 * Feature: spa-ai-features
 * Validates: Requirements 9.3, 9.4, 9.5, 9.6, 9.7
 */
class FeedbackControllerPropertyTest extends TestCase
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

    /**
     * Create a completed Session for the given customer, completed at the
     * given Carbon timestamp.
     */
    private function createCompletedSessionAt(int $customerId, Carbon $completedAt): Session
    {
        $branch    = Branch::factory()->create();
        $room      = Room::factory()->create(['branch_id' => $branch->id]);
        $bed       = Bed::factory()->create(['room_id' => $room->id]);
        $employee  = Employee::factory()->create(['branch_id' => $branch->id]);
        $treatment = Treatment::factory()->create();

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
    // Property 21: Feedback submission payload completeness
    // Feature: spa-ai-features, Property 21: Feedback submission payload completeness
    // Validates: Requirements 9.3
    // =========================================================================

    /**
     * Generate 100 random valid feedback form submissions.
     *
     * Each case: [rating (1–5), comment (non-empty string)]
     *
     * @return array<int, array{int, string}>
     */
    public static function feedbackPayloadProvider(): array
    {
        $cases = [];

        $sampleComments = [
            'Great service!',
            'Very relaxing session.',
            'The therapist was professional.',
            'I enjoyed the treatment.',
            'Will come back again.',
            'Excellent atmosphere.',
            'Good value for money.',
            'The room was clean and comfortable.',
            'Highly recommended.',
            'A wonderful experience overall.',
        ];

        for ($i = 0; $i < 100; $i++) {
            $rating  = rand(1, 5);
            $comment = $sampleComments[$i % count($sampleComments)] . ' (iteration ' . $i . ')';
            $cases[] = [$rating, $comment];
        }

        return $cases;
    }

    /**
     * For any feedback form submission, the persisted record contains non-null
     * values for rating, comment, session_id, and customer_id.
     *
     * @dataProvider feedbackPayloadProvider
     */
    public function test_property21_feedback_payload_completeness(int $rating, string $comment): void
    {
        // Feature: spa-ai-features, Property 21: Feedback submission payload completeness
        Queue::fake();

        [$user, $customer] = $this->createCustomer();
        $session = $this->createCompletedSessionAt($customer->id, Carbon::now()->subHour());

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/feedback', [
            'session_id' => $session->id,
            'rating'     => $rating,
            'comment'    => $comment,
        ]);

        $response->assertStatus(201);

        $feedback = Feedback::where('session_id', $session->id)
            ->where('customer_id', $customer->id)
            ->first();

        $this->assertNotNull($feedback, 'Feedback record must be persisted');
        $this->assertNotNull($feedback->rating,      'rating must not be null');
        $this->assertNotNull($feedback->comment,     'comment must not be null');
        $this->assertNotNull($feedback->session_id,  'session_id must not be null');
        $this->assertNotNull($feedback->customer_id, 'customer_id must not be null');

        $this->assertEquals($rating,       $feedback->rating,      'rating must match submitted value');
        $this->assertEquals($comment,      $feedback->comment,     'comment must match submitted value');
        $this->assertEquals($session->id,  $feedback->session_id,  'session_id must match submitted value');
        $this->assertEquals($customer->id, $feedback->customer_id, 'customer_id must be the authenticated customer');
    }

    // =========================================================================
    // Property 22: Feedback time window enforcement
    // Feature: spa-ai-features, Property 22: Feedback time window enforcement
    // Validates: Requirements 9.4, 9.5
    // =========================================================================

    /**
     * Generate 100 random submission timestamps relative to session completion.
     *
     * Each case: [hoursAfterCompletion (float), shouldBeAccepted (bool)]
     * Accepted iff hoursAfterCompletion <= 48.
     *
     * @return array<int, array{float, bool}>
     */
    public static function timeWindowProvider(): array
    {
        $cases = [];

        // 50 cases within the 48-hour window (should be accepted)
        for ($i = 0; $i < 50; $i++) {
            // Random hours in [0, 47.99]
            $hours   = (rand(0, 4799)) / 100.0;
            $cases[] = [$hours, true];
        }

        // 50 cases outside the 48-hour window (should be rejected)
        for ($i = 0; $i < 50; $i++) {
            // Random hours in [48.01, 120]
            $hours   = 48.01 + (rand(0, 7199)) / 100.0;
            $cases[] = [$hours, false];
        }

        return $cases;
    }

    /**
     * For any feedback submission at time T for a session completed at time S,
     * the submission is accepted iff T − S ≤ 48 hours.
     *
     * @dataProvider timeWindowProvider
     */
    public function test_property22_feedback_time_window_enforcement(float $hoursAfterCompletion, bool $shouldBeAccepted): void
    {
        // Feature: spa-ai-features, Property 22: Feedback time window enforcement
        Queue::fake();

        [$user, $customer] = $this->createCustomer();

        // Session was completed $hoursAfterCompletion hours ago from "now"
        $completedAt = Carbon::now()->subHours($hoursAfterCompletion);
        $session     = $this->createCompletedSessionAt($customer->id, $completedAt);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/feedback', [
            'session_id' => $session->id,
            'rating'     => rand(1, 5),
            'comment'    => 'Test feedback for time window property.',
        ]);

        if ($shouldBeAccepted) {
            $response->assertStatus(
                201,
                "Expected 201 for submission {$hoursAfterCompletion}h after completion (within 48h window)"
            );
            $this->assertDatabaseHas('feedbacks', [
                'session_id'  => $session->id,
                'customer_id' => $customer->id,
            ]);
        } else {
            $response->assertStatus(
                422,
                "Expected 422 for submission {$hoursAfterCompletion}h after completion (outside 48h window)"
            );
            $response->assertJson(['error' => 'feedback_window_closed']);
            $this->assertDatabaseMissing('feedbacks', [
                'session_id'  => $session->id,
                'customer_id' => $customer->id,
            ]);
        }
    }

    // =========================================================================
    // Property 23: One feedback per customer per session
    // Feature: spa-ai-features, Property 23: One feedback per customer per session
    // Validates: Requirements 9.6, 9.7
    // =========================================================================

    /**
     * Generate 100 duplicate submission scenarios with varying ratings and comments.
     *
     * Each case: [firstRating, firstComment, secondRating, secondComment]
     *
     * @return array<int, array{int, string, int, string}>
     */
    public static function duplicateSubmissionProvider(): array
    {
        $cases = [];

        for ($i = 0; $i < 100; $i++) {
            $firstRating   = rand(1, 5);
            $secondRating  = rand(1, 5);
            $firstComment  = 'First feedback submission, iteration ' . $i;
            $secondComment = 'Second feedback submission, iteration ' . $i;
            $cases[]       = [$firstRating, $firstComment, $secondRating, $secondComment];
        }

        return $cases;
    }

    /**
     * For any customer-session pair, submitting a second feedback record results
     * in the Backend_API rejecting the duplicate, leaving exactly one feedback
     * record for that pair.
     *
     * @dataProvider duplicateSubmissionProvider
     */
    public function test_property23_one_feedback_per_customer_per_session(
        int    $firstRating,
        string $firstComment,
        int    $secondRating,
        string $secondComment
    ): void {
        // Feature: spa-ai-features, Property 23: One feedback per customer per session
        Queue::fake();

        [$user, $customer] = $this->createCustomer();
        $session = $this->createCompletedSessionAt($customer->id, Carbon::now()->subHour());

        Sanctum::actingAs($user);

        // First submission — must succeed
        $firstResponse = $this->postJson('/api/feedback', [
            'session_id' => $session->id,
            'rating'     => $firstRating,
            'comment'    => $firstComment,
        ]);

        $firstResponse->assertStatus(201, 'First submission must be accepted with 201');

        // Second submission — must be rejected
        $secondResponse = $this->postJson('/api/feedback', [
            'session_id' => $session->id,
            'rating'     => $secondRating,
            'comment'    => $secondComment,
        ]);

        $secondResponse->assertStatus(409, 'Second submission must be rejected with 409');
        $secondResponse->assertJson(['error' => 'feedback_already_submitted']);

        // Exactly one feedback record must exist for this customer+session pair
        $count = Feedback::where('session_id', $session->id)
            ->where('customer_id', $customer->id)
            ->count();

        $this->assertEquals(
            1,
            $count,
            "Exactly one feedback record must exist for customer {$customer->id} + session {$session->id}, found {$count}"
        );
    }
}
