<?php

namespace Tests\Feature;

use App\Events\ConflictDetected;
use App\Events\FeedbackAnalyzed;
use App\Events\FeedbackPrompt;
use App\Events\ReschedulingSuggestion;
use App\Jobs\EvaluateConflictJob;
use App\Jobs\SentimentAnalysisJob;
use App\Models\Bed;
use App\Models\Branch;
use App\Models\Conflict;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Feedback;
use App\Models\Room;
use App\Models\Session;
use App\Models\Treatment;
use App\Models\User;
use App\Services\ChatbotService;
use App\Services\RecommendationService;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Integration tests for AI features end-to-end flows.
 *
 * Uses Queue::fake() and Event::fake() to avoid actual AI calls while
 * verifying timing, dispatch, and broadcast behaviour.
 *
 * Validates: Requirements 1.4, 4.5, 6.3, 6.4, 7.5, 9.1, 10.2, 11.4, 11.7
 */
class AiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // Shared helpers
    // =========================================================================

    /** Create a customer User with a linked Customer record and authenticate. */
    private function actAsCustomer(): array
    {
        $user     = User::factory()->create(['type' => 'CUSTOMER']);
        $customer = Customer::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);
        return [$user, $customer];
    }

    /** Create a manager User with an Employee linked to the given branch and authenticate. */
    private function actAsManager(Branch $branch): User
    {
        $user = User::factory()->create(['type' => 'MANAGER']);
        Employee::factory()->create(['user_id' => $user->id, 'branch_id' => $branch->id]);
        Sanctum::actingAs($user);
        return $user;
    }

    /** Create a staff User with an Employee linked to the given branch and authenticate. */
    private function actAsStaff(Branch $branch, string $type = 'CASHIER'): User
    {
        $user = User::factory()->create(['type' => $type]);
        Employee::factory()->create(['user_id' => $user->id, 'branch_id' => $branch->id]);
        Sanctum::actingAs($user);
        return $user;
    }

    /** Build a full scenario: branch, room, bed, employee, treatment, customer, session. */
    private function buildScenario(?Branch $branch = null): array
    {
        $branch    = $branch ?? Branch::factory()->create();
        $room      = Room::factory()->create(['branch_id' => $branch->id]);
        $bed       = Bed::factory()->create(['room_id' => $room->id]);
        $employee  = Employee::factory()->create(['branch_id' => $branch->id]);
        $treatment = Treatment::factory()->create();
        $userC     = User::factory()->create(['type' => 'CUSTOMER']);
        $customer  = Customer::factory()->create(['user_id' => $userC->id]);

        $session = Session::factory()->create([
            'customer_id'  => $customer->id,
            'employee_id'  => $employee->id,
            'bed_id'       => $bed->id,
            'treatment_id' => $treatment->id,
            'date'         => now()->toDateString(),
            'start'        => '10:00:00',
            'end'          => '11:00:00',
            'status'       => 'waiting',
        ]);

        return compact('branch', 'room', 'bed', 'employee', 'treatment', 'customer', 'userC', 'session');
    }

    /** Bind a RecommendationService whose HTTP client returns a canned AI response. */
    private function bindRecommendationServiceWithResponse(array $aiItems): void
    {
        $json    = json_encode($aiItems);
        $mock    = new MockHandler([
            new Response(200, [], json_encode([
                'choices' => [['message' => ['content' => $json]]],
            ])),
        ]);
        $handler = HandlerStack::create($mock);
        $client  = new Client(['handler' => $handler]);

        $this->app->instance(RecommendationService::class, new RecommendationService($client));
    }

    /** Bind a ChatbotService whose HTTP client returns a canned AI response. */
    private function bindChatbotServiceWithResponse(string $aiJson): void
    {
        $mock    = new MockHandler([
            new Response(200, [], json_encode([
                'choices' => [['message' => ['content' => $aiJson]]],
            ])),
        ]);
        $handler = HandlerStack::create($mock);
        $client  = new Client(['handler' => $handler]);

        $this->app->instance(ChatbotService::class, new ChatbotService($client));
    }

    // =========================================================================
    // Test 1: Recommendation endpoint responds within 2 seconds
    // Validates: Requirement 1.4
    // =========================================================================

    /**
     * The recommendation endpoint must respond within 2 seconds under realistic load.
     * Uses a mocked OpenAI client to avoid network latency.
     *
     * Validates: Requirement 1.4
     */
    public function test_recommendation_endpoint_responds_within_2_seconds(): void
    {
        $branch    = Branch::factory()->create();
        $treatment = Treatment::factory()->create();

        // Bind a fast mock AI response
        $this->bindRecommendationServiceWithResponse([
            ['treatment_id' => $treatment->id, 'rank' => 1, 'rationale' => 'Great match for you.'],
        ]);

        [$user, $customer] = $this->actAsCustomer();

        // Create enough sessions to trigger the AI path
        for ($i = 0; $i < 3; $i++) {
            Session::factory()->create([
                'customer_id'  => $customer->id,
                'treatment_id' => $treatment->id,
                'date'         => now()->subDays($i + 1)->toDateString(),
            ]);
        }

        $start = microtime(true);

        $response = $this->getJson("/api/ai/recommendations?branch_id={$branch->id}&customer_id={$customer->id}");

        $elapsed = microtime(true) - $start;

        $response->assertStatus(200);
        $this->assertLessThan(
            2.0,
            $elapsed,
            "Recommendation endpoint took {$elapsed}s — must respond within 2 seconds (Requirement 1.4)"
        );
    }

    // =========================================================================
    // Test 2: Chatbot endpoint responds within 5 seconds
    // Validates: Requirement 4.5
    // =========================================================================

    /**
     * The customer chatbot endpoint must respond within 5 seconds.
     * Uses a mocked OpenAI client to avoid network latency.
     *
     * Validates: Requirement 4.5
     */
    public function test_chatbot_endpoint_responds_within_5_seconds(): void
    {
        [$user] = $this->actAsCustomer();

        $clarificationJson = json_encode([
            'type'         => 'clarification',
            'missingField' => 'date',
            'message'      => 'What date would you like?',
        ]);

        $this->bindChatbotServiceWithResponse($clarificationJson);

        $start = microtime(true);

        $response = $this->postJson('/api/ai/chat', [
            'message' => 'I want to book a relaxing massage',
        ]);

        $elapsed = microtime(true) - $start;

        $response->assertStatus(200);
        $this->assertLessThan(
            5.0,
            $elapsed,
            "Chatbot endpoint took {$elapsed}s — must respond within 5 seconds (Requirement 4.5)"
        );
    }

    // =========================================================================
    // Test 3: Conflict evaluation job is dispatched within 3 seconds of session creation
    // Validates: Requirement 6.3
    // =========================================================================

    /**
     * When a new booking (Session) is created, EvaluateConflictJob must be dispatched
     * within 3 seconds of the creation event.
     *
     * Validates: Requirement 6.3
     */
    public function test_conflict_evaluation_job_dispatched_within_3_seconds_of_session_creation(): void
    {
        Queue::fake();

        $branch    = Branch::factory()->create();
        $room      = Room::factory()->create(['branch_id' => $branch->id]);
        $bed       = Bed::factory()->create(['room_id' => $room->id]);
        $employee  = Employee::factory()->create(['branch_id' => $branch->id]);
        $treatment = Treatment::factory()->create();
        $userC     = User::factory()->create(['type' => 'CUSTOMER']);
        $customer  = Customer::factory()->create(['user_id' => $userC->id]);

        $this->actAsStaff($branch);

        $start = microtime(true);

        // Creating a session triggers BookingObserver@created → EvaluateConflictJob::dispatch
        $session = Session::create([
            'customer_id'  => $customer->id,
            'employee_id'  => $employee->id,
            'bed_id'       => $bed->id,
            'treatment_id' => $treatment->id,
            'date'         => now()->toDateString(),
            'start'        => '14:00:00',
            'end'          => '15:00:00',
            'status'       => 'waiting',
        ]);

        $elapsed = microtime(true) - $start;

        // The job must have been dispatched
        Queue::assertPushedOn('conflict-evaluation', EvaluateConflictJob::class);

        // The dispatch itself (synchronous part) must complete within 3 seconds
        $this->assertLessThan(
            3.0,
            $elapsed,
            "Conflict job dispatch took {$elapsed}s — must complete within 3 seconds (Requirement 6.3)"
        );
    }

    // =========================================================================
    // Test 4: ConflictDetected event is broadcast to private-branch channel
    // Validates: Requirement 6.4
    // =========================================================================

    /**
     * When a conflict is detected, ConflictDetected must be broadcast on the
     * private-branch.{branchId} channel (SpaCashier staff alert).
     *
     * Validates: Requirement 6.4
     */
    public function test_conflict_detected_event_broadcast_to_private_branch_channel(): void
    {
        Event::fake([ConflictDetected::class, ReschedulingSuggestion::class]);

        $scenario = $this->buildScenario();
        /** @var Branch $branch */
        $branch  = $scenario['branch'];
        /** @var Session $session */
        $session = $scenario['session'];

        // Create a conflicting session (same employee, overlapping time)
        $conflictingSession = Session::factory()->create([
            'customer_id'  => $scenario['customer']->id,
            'employee_id'  => $scenario['employee']->id,
            'bed_id'       => $scenario['bed']->id,
            'treatment_id' => $scenario['treatment']->id,
            'date'         => $session->date,
            'start'        => '10:30:00',
            'end'          => '11:30:00',
            'status'       => 'waiting',
        ]);

        // Create a Conflict record and broadcast the event directly
        $conflict = Conflict::create([
            'booking_id'             => $session->id,
            'conflicting_booking_id' => $conflictingSession->id,
            'conflict_type'          => 'therapist',
            'detection_timestamp'    => now(),
            'resolution_status'      => 'pending',
            'resolution_action'      => null,
            'resolution_timestamp'   => null,
            'alternative_slots'      => [],
            'branch_id'              => $branch->id,
        ]);

        broadcast(new ConflictDetected($conflict, $session, $conflictingSession));

        // Assert the event was dispatched
        Event::assertDispatched(ConflictDetected::class, function (ConflictDetected $event) use ($conflict, $branch) {
            // Verify it broadcasts on the correct private-branch channel
            $channels = $event->broadcastOn();
            $channelNames = array_map(fn ($ch) => $ch->name, $channels);

            return $event->conflict->id === $conflict->id
                && in_array("branch.{$branch->id}", $channelNames, true);
        });
    }

    // =========================================================================
    // Test 5: ReschedulingSuggestion event is broadcast to private-customer channel
    // Validates: Requirement 7.5
    // =========================================================================

    /**
     * When a conflict is detected for a customer's booking, ReschedulingSuggestion
     * must be broadcast on the private-customer.{customerId} channel.
     * The SpaBooking app must be able to receive it within 10 seconds of conflict detection.
     *
     * Validates: Requirement 7.5
     */
    public function test_rescheduling_suggestion_broadcast_to_private_customer_channel(): void
    {
        Event::fake([ConflictDetected::class, ReschedulingSuggestion::class]);

        $scenario   = $this->buildScenario();
        $session    = $scenario['session'];
        $customer   = $scenario['customer'];
        $branch     = $scenario['branch'];

        $conflictingSession = Session::factory()->create([
            'customer_id'  => $customer->id,
            'employee_id'  => $scenario['employee']->id,
            'bed_id'       => $scenario['bed']->id,
            'treatment_id' => $scenario['treatment']->id,
            'date'         => $session->date,
            'start'        => '10:30:00',
            'end'          => '11:30:00',
            'status'       => 'waiting',
        ]);

        $conflict = Conflict::create([
            'booking_id'             => $session->id,
            'conflicting_booking_id' => $conflictingSession->id,
            'conflict_type'          => 'therapist',
            'detection_timestamp'    => now(),
            'resolution_status'      => 'pending',
            'resolution_action'      => null,
            'resolution_timestamp'   => null,
            'alternative_slots'      => [],
            'branch_id'              => $branch->id,
        ]);

        $detectionTime = microtime(true);

        broadcast(new ReschedulingSuggestion($conflict, $session, $customer->id));

        $broadcastTime = microtime(true) - $detectionTime;

        // Assert the event was dispatched
        Event::assertDispatched(ReschedulingSuggestion::class, function (ReschedulingSuggestion $event) use ($conflict, $customer) {
            $channels     = $event->broadcastOn();
            $channelNames = array_map(fn ($ch) => $ch->name, $channels);

            return $event->conflict->id === $conflict->id
                && in_array("customer.{$customer->id}", $channelNames, true);
        });

        // The broadcast dispatch must complete well within the 10-second delivery window
        $this->assertLessThan(
            10.0,
            $broadcastTime,
            "ReschedulingSuggestion broadcast took {$broadcastTime}s — must be delivered within 10 seconds (Requirement 7.5)"
        );
    }

    // =========================================================================
    // Test 6: FeedbackPrompt event is broadcast when session is finished
    // Validates: Requirement 9.1
    // =========================================================================

    /**
     * When a session is marked as completed via the finish endpoint,
     * a FeedbackPrompt event must be broadcast to the customer's private channel.
     *
     * Validates: Requirement 9.1
     */
    public function test_feedback_prompt_event_broadcast_on_session_completion(): void
    {
        Event::fake([FeedbackPrompt::class]);

        $scenario = $this->buildScenario();
        $session  = $scenario['session'];
        $customer = $scenario['customer'];

        // Authenticate as staff to call the finish endpoint
        $this->actAsStaff($scenario['branch']);

        $response = $this->postJson("/api/sessions/{$session->id}/finish");

        $response->assertStatus(200);

        // FeedbackPrompt must have been broadcast
        Event::assertDispatched(FeedbackPrompt::class, function (FeedbackPrompt $event) use ($session, $customer) {
            $channels     = $event->broadcastOn();
            $channelNames = array_map(fn ($ch) => $ch->name, $channels);

            return $event->session->id === $session->id
                && $event->customerId === $customer->id
                && in_array("customer.{$customer->id}", $channelNames, true);
        });
    }

    // =========================================================================
    // Test 7: SentimentAnalysisJob is queued when feedback is submitted
    // Validates: Requirement 10.2
    // =========================================================================

    /**
     * When a customer submits feedback, SentimentAnalysisJob must be queued
     * on the 'sentiment-analysis' queue for end-to-end processing.
     *
     * Validates: Requirement 10.2
     */
    public function test_sentiment_analysis_job_queued_on_feedback_submission(): void
    {
        Queue::fake();

        $scenario = $this->buildScenario();
        $session  = $scenario['session'];
        $customer = $scenario['customer'];
        $userC    = $scenario['userC'];

        // Mark session as completed
        $session->update([
            'status' => 'completed',
            'end'    => now()->format('H:i:s'),
            'date'   => now()->toDateString(),
        ]);

        Sanctum::actingAs($userC);

        $response = $this->postJson('/api/feedback', [
            'session_id' => $session->id,
            'rating'     => 4,
            'comment'    => 'Wonderful experience, very relaxing!',
        ]);

        $response->assertStatus(201);

        // Job must be queued on the correct queue
        Queue::assertPushedOn('sentiment-analysis', SentimentAnalysisJob::class);
    }

    /**
     * End-to-end: SentimentAnalysisJob processes feedback and broadcasts FeedbackAnalyzed.
     * Uses a mocked HTTP client to simulate OpenAI response.
     *
     * Validates: Requirement 10.2
     */
    public function test_sentiment_analysis_job_processes_feedback_end_to_end(): void
    {
        Event::fake([FeedbackAnalyzed::class]);

        $scenario = $this->buildScenario();
        $session  = $scenario['session'];
        $customer = $scenario['customer'];

        // Create a feedback record directly
        $feedback = Feedback::create([
            'session_id'        => $session->id,
            'customer_id'       => $customer->id,
            'rating'            => 5,
            'comment'           => 'Absolutely wonderful service!',
            'analysis_status'   => 'pending',
            'analysis_attempts' => 0,
            'submitted_at'      => now(),
        ]);

        // Mock the OpenAI response for sentiment analysis
        $sentimentJson = json_encode(['score' => 0.9, 'label' => 'positive']);
        $mock    = new MockHandler([
            new Response(200, [], json_encode([
                'choices' => [['message' => ['content' => $sentimentJson]]],
            ])),
        ]);
        $handler = HandlerStack::create($mock);
        $client  = new Client(['handler' => $handler]);

        $job = new SentimentAnalysisJob($feedback->id);
        $job->withHttpClient($client);

        $start = microtime(true);
        $job->handle();
        $elapsed = microtime(true) - $start;

        // Job must complete within 10 seconds (Requirement 10.2)
        $this->assertLessThan(
            10.0,
            $elapsed,
            "SentimentAnalysisJob took {$elapsed}s — must complete within 10 seconds (Requirement 10.2)"
        );

        // Feedback record must be updated with sentiment data
        $feedback->refresh();
        $this->assertEquals('completed', $feedback->analysis_status);
        $this->assertNotNull($feedback->sentiment_score);
        $this->assertNotNull($feedback->sentiment_label);

        // FeedbackAnalyzed event must have been broadcast
        Event::assertDispatched(FeedbackAnalyzed::class, function (FeedbackAnalyzed $event) use ($feedback) {
            return $event->feedback->id === $feedback->id;
        });
    }

    // =========================================================================
    // Test 8: Sentiment dashboard endpoint responds within 3 seconds
    // Validates: Requirement 11.4
    // =========================================================================

    /**
     * The sentiment dashboard endpoint must respond within 3 seconds when a
     * manager applies a filter.
     *
     * Validates: Requirement 11.4
     */
    public function test_sentiment_dashboard_responds_within_3_seconds(): void
    {
        $branch  = Branch::factory()->create();
        $manager = $this->actAsManager($branch);

        // Seed some feedback data to make the query realistic
        $room      = Room::factory()->create(['branch_id' => $branch->id]);
        $bed       = Bed::factory()->create(['room_id' => $room->id]);
        $employee  = Employee::factory()->create(['branch_id' => $branch->id]);
        $treatment = Treatment::factory()->create();
        $userC     = User::factory()->create(['type' => 'CUSTOMER']);
        $customer  = Customer::factory()->create(['user_id' => $userC->id]);

        $session = Session::factory()->create([
            'customer_id'  => $customer->id,
            'employee_id'  => $employee->id,
            'bed_id'       => $bed->id,
            'treatment_id' => $treatment->id,
            'date'         => now()->subDays(5)->toDateString(),
            'start'        => '10:00:00',
            'end'          => '11:00:00',
            'status'       => 'completed',
        ]);

        Feedback::create([
            'session_id'        => $session->id,
            'customer_id'       => $customer->id,
            'rating'            => 4,
            'comment'           => 'Great session!',
            'sentiment_score'   => 0.8,
            'sentiment_label'   => 'positive',
            'analysis_status'   => 'completed',
            'analysis_attempts' => 1,
            'submitted_at'      => now()->subDays(5),
            'analyzed_at'       => now()->subDays(5),
        ]);

        $start = microtime(true);

        $response = $this->getJson("/api/ai/sentiment/dashboard?branch_id={$branch->id}&period=30");

        $elapsed = microtime(true) - $start;

        $response->assertStatus(200);
        $this->assertLessThan(
            3.0,
            $elapsed,
            "Sentiment dashboard took {$elapsed}s — must respond within 3 seconds (Requirement 11.4)"
        );
    }

    // =========================================================================
    // Test 9: Real-time dashboard update on new FeedbackAnalyzed event
    // Validates: Requirement 11.7
    // =========================================================================

    /**
     * When a FeedbackAnalyzed event is broadcast, it must be dispatched on the
     * private-branch.{branchId} channel so SpaCashier can update the dashboard in real time.
     *
     * Validates: Requirement 11.7
     */
    public function test_feedback_analyzed_event_broadcast_to_private_branch_channel(): void
    {
        Event::fake([FeedbackAnalyzed::class]);

        $scenario = $this->buildScenario();
        $session  = $scenario['session'];
        $customer = $scenario['customer'];
        $branch   = $scenario['branch'];

        $feedback = Feedback::create([
            'session_id'        => $session->id,
            'customer_id'       => $customer->id,
            'rating'            => 5,
            'comment'           => 'Excellent!',
            'sentiment_score'   => 0.95,
            'sentiment_label'   => 'positive',
            'analysis_status'   => 'completed',
            'analysis_attempts' => 1,
            'submitted_at'      => now(),
            'analyzed_at'       => now(),
        ]);

        broadcast(new FeedbackAnalyzed($feedback, $branch->id));

        Event::assertDispatched(FeedbackAnalyzed::class, function (FeedbackAnalyzed $event) use ($feedback, $branch) {
            $channels     = $event->broadcastOn();
            $channelNames = array_map(fn ($ch) => $ch->name, $channels);

            return $event->feedback->id === $feedback->id
                && $event->branchId === $branch->id
                && in_array("branch.{$branch->id}", $channelNames, true);
        });
    }
}
