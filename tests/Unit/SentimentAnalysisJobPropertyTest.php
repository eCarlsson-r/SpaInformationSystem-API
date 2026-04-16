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
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Property-based tests for SentimentAnalysisJob.
 *
 * Eris is not available in this project; properties are verified using
 * PHPUnit data providers that generate 100+ random inputs per property.
 *
 * Feature: spa-ai-features
 * Validates: Requirements 10.3, 10.6
 */
class SentimentAnalysisJobPropertyTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Create a Feedback record with the given comment and analysis_status.
     */
    private function createFeedback(string $comment, string $status = 'pending'): Feedback
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
            'analysis_status'   => $status,
            'analysis_attempts' => 0,
            'submitted_at'      => Carbon::now(),
        ]);
    }

    /**
     * Build a Guzzle mock client that returns the given score and label as an
     * OpenAI-style chat completion response.
     *
     * @param  float   $score  Sentiment score to return from the mock AI
     * @param  string  $label  Sentiment label to return from the mock AI
     * @param  int     $times  Number of responses to queue (default 1)
     */
    private function makeMockClient(float $score, string $label, int $times = 1): Client
    {
        $content  = json_encode(['score' => $score, 'label' => $label]);
        $body     = json_encode([
            'choices' => [['message' => ['content' => $content]]],
        ]);

        $responses = array_fill(0, $times, new Response(200, [], $body));
        $mock      = new MockHandler($responses);
        $handler   = HandlerStack::create($mock);

        return new Client(['handler' => $handler]);
    }

    // =========================================================================
    // Property 24: Sentiment output validity
    // Feature: spa-ai-features, Property 24: Sentiment output validity
    // Validates: Requirements 10.3
    // =========================================================================

    /**
     * Generate 100 random (score, label) pairs that the mock AI returns.
     *
     * Covers:
     *  - Scores already within [-1.0, 1.0] with matching labels
     *  - Scores outside [-1.0, 1.0] that must be clamped
     *  - Invalid labels that must be derived from the score
     *
     * Each case: [rawScore (float), rawLabel (string), comment (string)]
     *
     * @return array<int, array{float, string, string}>
     */
    public static function sentimentOutputProvider(): array
    {
        $cases = [];

        $validLabels = ['positive', 'neutral', 'negative'];

        $sampleComments = [
            'The massage was absolutely wonderful.',
            'Service was okay, nothing special.',
            'Very disappointed with the experience.',
            'Loved the ambiance and the staff.',
            'The treatment was too short.',
            'Excellent therapist, highly recommend.',
            'Room was a bit cold but overall fine.',
            'Will definitely come back again.',
            'Not worth the price.',
            'Relaxing and rejuvenating session.',
        ];

        // 40 cases: valid scores in [-1.0, 1.0] with correct labels
        for ($i = 0; $i < 40; $i++) {
            // Random score in [-1.0, 1.0] with 3 decimal places
            $score   = round((rand(-1000, 1000)) / 1000.0, 3);
            $label   = $validLabels[array_key_first(array_filter($validLabels, fn ($l) =>
                ($score >= 0.2 && $l === 'positive') ||
                ($score <= -0.2 && $l === 'negative') ||
                ($score > -0.2 && $score < 0.2 && $l === 'neutral')
            ))];
            $comment = $sampleComments[$i % count($sampleComments)] . ' (case ' . $i . ')';
            $cases[] = [$score, $label, $comment];
        }

        // 30 cases: scores outside [-1.0, 1.0] that must be clamped
        for ($i = 0; $i < 30; $i++) {
            // Alternate between too-high and too-low
            if ($i % 2 === 0) {
                $score = round(1.001 + (rand(0, 500)) / 1000.0, 3); // > 1.0
                $label = 'positive';
            } else {
                $score = round(-1.001 - (rand(0, 500)) / 1000.0, 3); // < -1.0
                $label = 'negative';
            }
            $comment = $sampleComments[$i % count($sampleComments)] . ' (clamp case ' . $i . ')';
            $cases[] = [$score, $label, $comment];
        }

        // 30 cases: valid scores but invalid labels (must be derived from score)
        $invalidLabels = ['POSITIVE', 'bad', 'unknown', 'ok', 'great', 'terrible'];
        for ($i = 0; $i < 30; $i++) {
            $score   = round((rand(-1000, 1000)) / 1000.0, 3);
            $label   = $invalidLabels[$i % count($invalidLabels)];
            $comment = $sampleComments[$i % count($sampleComments)] . ' (invalid label case ' . $i . ')';
            $cases[] = [$score, $label, $comment];
        }

        return $cases;
    }

    /**
     * For any non-empty feedback comment processed by the Sentiment_Analyzer,
     * the resulting sentiment_score is in [-1.0, 1.0] and sentiment_label is
     * one of {positive, neutral, negative}.
     *
     * @dataProvider sentimentOutputProvider
     */
    public function test_property24_sentiment_output_validity(
        float  $rawScore,
        string $rawLabel,
        string $comment
    ): void {
        // Feature: spa-ai-features, Property 24: Sentiment output validity
        Event::fake();
        config(['services.openai.api_key' => 'test-key']);

        $feedback = $this->createFeedback($comment);

        $job    = new SentimentAnalysisJob($feedback->id);
        $client = $this->makeMockClient($rawScore, $rawLabel);
        $job->withHttpClient($client);
        $job->handle();

        $feedback->refresh();

        // Score must be in [-1.0, 1.0]
        $this->assertGreaterThanOrEqual(
            -1.0,
            $feedback->sentiment_score,
            "sentiment_score {$feedback->sentiment_score} must be >= -1.0 (raw score was {$rawScore})"
        );
        $this->assertLessThanOrEqual(
            1.0,
            $feedback->sentiment_score,
            "sentiment_score {$feedback->sentiment_score} must be <= 1.0 (raw score was {$rawScore})"
        );

        // Label must be one of the three valid values
        $this->assertContains(
            $feedback->sentiment_label,
            ['positive', 'neutral', 'negative'],
            "sentiment_label '{$feedback->sentiment_label}' must be positive, neutral, or negative"
        );

        // Analysis must be marked completed
        $this->assertEquals(
            'completed',
            $feedback->analysis_status,
            'analysis_status must be completed after successful analysis'
        );
    }

    // =========================================================================
    // Property 25: Sentiment retry exhaustion
    // Feature: spa-ai-features, Property 25: Sentiment retry exhaustion
    // Validates: Requirements 10.6
    // =========================================================================

    /**
     * Generate 100 random failure scenarios.
     *
     * Each case: [comment (string), exceptionMessage (string)]
     *
     * @return array<int, array{string, string}>
     */
    public static function retryExhaustionProvider(): array
    {
        $cases = [];

        $comments = [
            'Great session overall.',
            'The therapist was very skilled.',
            'Room temperature was perfect.',
            'I enjoyed the aromatherapy.',
            'Will book again next month.',
            'The staff were very friendly.',
            'Excellent value for money.',
            'Very relaxing atmosphere.',
            'The treatment exceeded expectations.',
            'Highly recommend this spa.',
        ];

        $errorMessages = [
            'Connection timed out',
            'OpenAI API returned 500',
            'Network unreachable',
            'Request failed with status 429',
            'SSL certificate error',
        ];

        for ($i = 0; $i < 100; $i++) {
            $comment   = $comments[$i % count($comments)] . ' (retry case ' . $i . ')';
            $errorMsg  = $errorMessages[$i % count($errorMessages)];
            $cases[]   = [$comment, $errorMsg];
        }

        return $cases;
    }

    /**
     * For any queued sentiment analysis job that fails on every attempt,
     * after exactly 5 failed attempts the feedback record's analysis_status
     * is set to 'analysis_failed'.
     *
     * Tests the failed() method directly — it is called by Laravel's queue
     * worker after all $tries are exhausted.
     *
     * @dataProvider retryExhaustionProvider
     */
    public function test_property25_sentiment_retry_exhaustion(
        string $comment,
        string $errorMessage
    ): void {
        // Feature: spa-ai-features, Property 25: Sentiment retry exhaustion
        $feedback = $this->createFeedback($comment);

        // Verify initial state
        $this->assertEquals(
            'pending',
            $feedback->analysis_status,
            'analysis_status must start as pending'
        );

        // Simulate the job's failed() method being called after all retries exhausted
        $job       = new SentimentAnalysisJob($feedback->id);
        $exception = new \RuntimeException($errorMessage);
        $job->failed($exception);

        $feedback->refresh();

        // After retry exhaustion, analysis_status must be 'analysis_failed'
        $this->assertEquals(
            'analysis_failed',
            $feedback->analysis_status,
            "analysis_status must be 'analysis_failed' after retry exhaustion (error: {$errorMessage})"
        );

        // Verify the job is configured for exactly 5 tries
        $this->assertEquals(
            5,
            $job->tries,
            'Job must be configured for exactly 5 retry attempts'
        );

        // Verify the backoff is 60 seconds
        $this->assertEquals(
            60,
            $job->backoff,
            'Job backoff must be 60 seconds between retries'
        );
    }
}
