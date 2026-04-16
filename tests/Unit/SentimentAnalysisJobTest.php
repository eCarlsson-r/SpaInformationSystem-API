<?php

namespace Tests\Unit;

use App\Events\FeedbackAnalyzed;
use App\Jobs\SentimentAnalysisJob;
use App\Models\Bed;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Feedback;
use App\Models\Room;
use App\Models\Session;
use App\Models\Treatment;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use App\Models\User;
use Tests\TestCase;

/**
 * Unit tests for SentimentAnalysisJob.
 *
 * Validates: Requirements 10.4, 10.5
 */
class SentimentAnalysisJobTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Create a Feedback record with the given comment.
     */
    private function createFeedback(string $comment): Feedback
    {
        $branch    = Branch::factory()->create();
        $room      = Room::factory()->create(['branch_id' => $branch->id]);
        $bed       = Bed::factory()->create(['room_id' => $room->id]);
        $employee  = Employee::factory()->create(['branch_id' => $branch->id]);
        $treatment = Treatment::factory()->create();
        $customer  = Customer::factory()->create();

        $session = Session::factory()->create([
            'customer_id'  => $customer->id,
            'employee_id'  => $employee->id,
            'bed_id'       => $bed->id,
            'treatment_id' => $treatment->id,
            'date'         => Carbon::now()->toDateString(),
            'end'          => Carbon::now()->format('H:i:s'),
            'status'       => 'completed',
        ]);

        return Feedback::create([
            'session_id'        => $session->id,
            'customer_id'       => $customer->id,
            'rating'            => rand(1, 5),
            'comment'           => $comment,
            'analysis_status'   => 'pending',
            'analysis_attempts' => 0,
            'submitted_at'      => Carbon::now(),
        ]);
    }

    /**
     * Build a Guzzle mock client that returns the given score and label.
     */
    private function makeMockClient(float $score, string $label): Client
    {
        $content = json_encode(['score' => $score, 'label' => $label]);
        $body    = json_encode([
            'choices' => [['message' => ['content' => $content]]],
        ]);

        $mock    = new MockHandler([new Response(200, [], $body)]);
        $handler = HandlerStack::create($mock);

        return new Client(['handler' => $handler]);
    }

    /**
     * Create a customer User with a linked Customer record and a completed session.
     */
    private function createCustomerWithSession(): array
    {
        $user     = User::factory()->create(['type' => 'CUSTOMER']);
        $customer = Customer::factory()->create(['user_id' => $user->id]);

        $branch    = Branch::factory()->create();
        $room      = Room::factory()->create(['branch_id' => $branch->id]);
        $bed       = Bed::factory()->create(['room_id' => $room->id]);
        $employee  = Employee::factory()->create(['branch_id' => $branch->id]);
        $treatment = Treatment::factory()->create();

        $completedAt = Carbon::now()->subHour();

        $session = Session::factory()->create([
            'customer_id'  => $customer->id,
            'employee_id'  => $employee->id,
            'bed_id'       => $bed->id,
            'treatment_id' => $treatment->id,
            'date'         => $completedAt->toDateString(),
            'end'          => $completedAt->format('H:i:s'),
            'status'       => 'completed',
        ]);

        return [$user, $customer, $session];
    }

    // =========================================================================
    // Test: empty comment sets score=0.0, label='neutral', no AI call made
    // Validates: Requirement 10.4
    // =========================================================================

    /**
     * When a Feedback record has an empty comment, the job sets sentiment_score=0.0,
     * sentiment_label='neutral', analysis_status='completed', and makes no HTTP call.
     *
     * Validates: Requirement 10.4
     */
    public function test_empty_comment_sets_neutral_score_without_ai_call(): void
    {
        Event::fake();
        config(['services.openai.api_key' => 'test-key']);

        $feedback = $this->createFeedback('');

        // Inject a client whose handler throws immediately if any request is made.
        // If the job incorrectly calls the AI for an empty comment, the test will fail.
        $httpCallMade = false;
        $throwingMock = new MockHandler([
            function () use (&$httpCallMade) {
                $httpCallMade = true;
                throw new \RuntimeException('Unexpected HTTP call for empty comment');
            },
        ]);
        $throwingClient = new Client(['handler' => HandlerStack::create($throwingMock)]);

        $job = new SentimentAnalysisJob($feedback->id);
        $job->withHttpClient($throwingClient);
        $job->handle();

        $feedback->refresh();

        $this->assertFalse($httpCallMade, 'No HTTP call should be made for empty comments');
        $this->assertEquals(0.0, $feedback->sentiment_score, 'Empty comment must set sentiment_score to 0.0');
        $this->assertEquals('neutral', $feedback->sentiment_label, 'Empty comment must set sentiment_label to neutral');
        $this->assertEquals('completed', $feedback->analysis_status, 'Empty comment must set analysis_status to completed');
        $this->assertNotNull($feedback->analyzed_at, 'analyzed_at must be set after processing');
    }

    /**
     * Whitespace-only comment is treated as empty — same neutral outcome, no AI call.
     *
     * Validates: Requirement 10.4
     */
    public function test_whitespace_only_comment_treated_as_empty(): void
    {
        Event::fake();
        config(['services.openai.api_key' => 'test-key']);

        foreach (['   ', "\t", "\n", "  \n  "] as $whitespace) {
            $feedback = $this->createFeedback($whitespace);

            $job = new SentimentAnalysisJob($feedback->id);
            $job->handle();

            $feedback->refresh();

            $this->assertEquals(0.0, $feedback->sentiment_score, "Whitespace comment '{$whitespace}' must set score to 0.0");
            $this->assertEquals('neutral', $feedback->sentiment_label, "Whitespace comment '{$whitespace}' must set label to neutral");
            $this->assertEquals('completed', $feedback->analysis_status);
        }
    }

    // =========================================================================
    // Test: job queued and feedback submission returns 201 immediately
    // Validates: Requirement 10.5
    // =========================================================================

    /**
     * Submitting feedback via POST /api/feedback returns 201 immediately and
     * dispatches SentimentAnalysisJob to the queue (non-blocking).
     *
     * Validates: Requirement 10.5
     */
    public function test_feedback_submission_returns_201_and_queues_job(): void
    {
        Queue::fake();

        [$user, $customer, $session] = $this->createCustomerWithSession();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/feedback', [
            'session_id' => $session->id,
            'rating'     => 4,
            'comment'    => 'Great session, very relaxing!',
        ]);

        // Response must be 201 immediately (non-blocking)
        $response->assertStatus(201);

        // SentimentAnalysisJob must be pushed to the queue, not processed synchronously
        Queue::assertPushedOn('sentiment-analysis', SentimentAnalysisJob::class);

        // Verify the job was pushed with the correct feedback ID
        Queue::assertPushed(SentimentAnalysisJob::class, function (SentimentAnalysisJob $job) use ($response) {
            $feedbackId = $response->json('id');
            return $job->feedbackId === $feedbackId;
        });
    }

    /**
     * The feedback record is persisted with analysis_status='pending' immediately
     * after submission, before the job runs.
     *
     * Validates: Requirement 10.5
     */
    public function test_feedback_persisted_with_pending_status_before_job_runs(): void
    {
        Queue::fake();

        [$user, $customer, $session] = $this->createCustomerWithSession();

        Sanctum::actingAs($user);

        $this->postJson('/api/feedback', [
            'session_id' => $session->id,
            'rating'     => 3,
            'comment'    => 'Decent experience.',
        ])->assertStatus(201);

        // The feedback record must exist with pending status (job not yet run)
        $this->assertDatabaseHas('feedbacks', [
            'session_id'      => $session->id,
            'customer_id'     => $customer->id,
            'analysis_status' => 'pending',
        ]);
    }

    // =========================================================================
    // Test: FeedbackAnalyzed event broadcast after successful analysis
    // Validates: Requirement 10.5 (non-blocking + event broadcast)
    // =========================================================================

    /**
     * After the SentimentAnalysisJob runs successfully on a non-empty comment,
     * the FeedbackAnalyzed event is broadcast.
     *
     * Validates: Requirement 10.5
     */
    public function test_feedback_analyzed_event_broadcast_after_successful_analysis(): void
    {
        Event::fake();
        config(['services.openai.api_key' => 'test-key']);

        $feedback = $this->createFeedback('The massage was absolutely wonderful!');

        $job    = new SentimentAnalysisJob($feedback->id);
        $client = $this->makeMockClient(0.85, 'positive');
        $job->withHttpClient($client);
        $job->handle();

        // FeedbackAnalyzed must have been broadcast
        Event::assertDispatched(FeedbackAnalyzed::class, function (FeedbackAnalyzed $event) use ($feedback) {
            return $event->feedback->id === $feedback->id;
        });
    }

    /**
     * FeedbackAnalyzed event is also broadcast for empty comments (neutral path).
     *
     * Validates: Requirement 10.5
     */
    public function test_feedback_analyzed_event_broadcast_for_empty_comment(): void
    {
        Event::fake();
        config(['services.openai.api_key' => 'test-key']);

        $feedback = $this->createFeedback('');

        $job = new SentimentAnalysisJob($feedback->id);
        $job->handle();

        Event::assertDispatched(FeedbackAnalyzed::class, function (FeedbackAnalyzed $event) use ($feedback) {
            return $event->feedback->id === $feedback->id;
        });
    }

    /**
     * The FeedbackAnalyzed event payload contains the correct sentiment data.
     *
     * Validates: Requirement 10.5
     */
    public function test_feedback_analyzed_event_payload_contains_correct_data(): void
    {
        Event::fake();
        config(['services.openai.api_key' => 'test-key']);

        $feedback = $this->createFeedback('Excellent service, highly recommend!');

        $job    = new SentimentAnalysisJob($feedback->id);
        $client = $this->makeMockClient(0.9, 'positive');
        $job->withHttpClient($client);
        $job->handle();

        $feedback->refresh();

        Event::assertDispatched(FeedbackAnalyzed::class, function (FeedbackAnalyzed $event) use ($feedback) {
            return $event->payload['feedback_id']     === $feedback->id
                && $event->payload['sentiment_score']  === $feedback->sentiment_score
                && $event->payload['sentiment_label']  === $feedback->sentiment_label
                && $event->payload['analysis_status']  === 'completed';
        });
    }
}
