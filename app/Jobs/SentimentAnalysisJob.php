<?php

namespace App\Jobs;

use App\Events\FeedbackAnalyzed;
use App\Models\Feedback;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Analyses the sentiment of a submitted Feedback record using OpenAI.
 *
 * Requirements: 10.1, 10.2, 10.3, 10.4, 10.5, 10.6
 */
class SentimentAnalysisJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum number of attempts before marking as analysis_failed.
     * Requirements: 10.6
     */
    /** Queue name for this job. Requirements: 10.5, 10.6 */
    public int $tries = 5;

    /**
     * Delay between retries in seconds.
     * Requirements: 10.6
     */
    public int $backoff = 60;

    /**
     * OpenAI request timeout in seconds.
     * Requirement: 10.2 (complete within 10 seconds)
     */
    private const OPENAI_TIMEOUT = 9.0;

    /**
     * Optional HTTP client override (used in tests to inject a mock).
     */
    private ?Client $httpClient = null;

    /**
     * Create a new job instance.
     */
    public function __construct(public readonly int $feedbackId)
    {
        $this->onQueue('sentiment-analysis');
    }

    /**
     * Inject a custom HTTP client (for testing only).
     */
    public function withHttpClient(Client $client): static
    {
        $this->httpClient = $client;
        return $this;
    }

    /**
     * Execute the job.
     *
     * Requirements: 10.1, 10.2, 10.3, 10.4, 10.5
     */
    public function handle(): void
    {
        $feedback = Feedback::find($this->feedbackId);

        if (!$feedback) {
            Log::warning('SentimentAnalysisJob: Feedback not found', [
                'feedback_id' => $this->feedbackId,
            ]);
            return;
        }

        // Increment attempt counter
        $feedback->increment('analysis_attempts');

        // Requirement 10.4: empty comment → neutral, skip AI call
        if (empty(trim((string) $feedback->comment))) {
            $feedback->update([
                'sentiment_score'  => 0.0,
                'sentiment_label'  => 'neutral',
                'analysis_status'  => 'completed',
                'analyzed_at'      => Carbon::now(),
            ]);

            $this->broadcastAnalyzed($feedback);
            return;
        }

        // Requirement 10.1, 10.3: call OpenAI and parse score + label
        [$score, $label] = $this->analyzeWithOpenAI($feedback->comment);
        $feedback->update([
            'sentiment_score'  => $score,
            'sentiment_label'  => $label,
            'analysis_status'  => 'completed',
            'analyzed_at'      => Carbon::now(),
        ]);

        $this->broadcastAnalyzed($feedback);
    }

    /**
     * Handle a job failure after all retries are exhausted.
     * Requirements: 10.6
     */
    public function failed(\Throwable $exception): void
    {
        $feedback = Feedback::find($this->feedbackId);

        if ($feedback) {
            $feedback->update(['analysis_status' => 'analysis_failed']);
        }

        Log::error('SentimentAnalysisJob: Failed after all attempts', [
            'feedback_id' => $this->feedbackId,
            'error'       => $exception->getMessage(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Call OpenAI Sentiment_Analyzer and return [score, label].
     *
     * @return array{float, string}  [score ∈ [-1.0, 1.0], label ∈ {positive, neutral, negative}]
     * @throws \RuntimeException on AI failure (triggers retry)
     */
    private function analyzeWithOpenAI(string $comment): array
    {
        $apiKey = config('services.openai.api_key');

        // If a mock client is injected (for testing), skip the API key check
        if (empty($apiKey) && $this->httpClient === null) {
            throw new \RuntimeException('OpenAI API key is not configured.');
        }

        $client = $this->httpClient ?? new Client([
            'base_uri' => 'https://api.openai.com',
            'timeout'  => self::OPENAI_TIMEOUT,
        ]);

        $systemPrompt = <<<PROMPT
You are a sentiment analysis engine for a spa customer feedback system.
Analyze the sentiment of the provided customer comment and return a JSON object with exactly two fields:
- "score": a float between -1.0 (most negative) and 1.0 (most positive)
- "label": one of "positive", "neutral", or "negative"

Rules:
- score >= 0.2 → label must be "positive"
- score <= -0.2 → label must be "negative"
- -0.2 < score < 0.2 → label must be "neutral"
- Return ONLY valid JSON, no markdown, no explanation.

Example: {"score": 0.85, "label": "positive"}
PROMPT;

        try {
            $headers = ['Content-Type' => 'application/json'];
            if (!empty($apiKey)) {
                $headers['Authorization'] = "Bearer {$apiKey}";
            }

            $response = $client->post('/v1/chat/completions', [
                'headers' => $headers,
                'json' => [
                    'model'       => 'gpt-4o-mini',
                    'messages'    => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user',   'content' => $comment],
                    ],
                    'temperature' => 0.1,
                    'max_tokens'  => 60,
                ],
            ]);

            $body    = json_decode($response->getBody()->getContents(), true);
            $content = $body['choices'][0]['message']['content'] ?? '{}';

            return $this->parseAiResponse($content);
        } catch (GuzzleException $e) {
            Log::warning('SentimentAnalysisJob: OpenAI request failed', [
                'feedback_id' => $this->feedbackId,
                'error'       => $e->getMessage(),
            ]);
            throw new \RuntimeException('OpenAI request failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Parse and validate the OpenAI JSON response.
     *
     * @return array{float, string}
     * @throws \RuntimeException on invalid response
     */
    private function parseAiResponse(string $content): array
    {
        // Strip markdown fences if present
        $cleaned = preg_replace('/^```(?:json)?\s*/i', '', trim($content));
        $cleaned = preg_replace('/\s*```$/', '', $cleaned);

        $data = json_decode(trim($cleaned), true);

        if (!is_array($data) || !isset($data['score'], $data['label'])) {
            throw new \RuntimeException('Invalid AI response format: ' . $content);
        }

        $score = (float) $data['score'];
        $label = (string) $data['label'];

        // Clamp score to [-1.0, 1.0]
        $score = max(-1.0, min(1.0, $score));

        // Validate label
        $validLabels = ['positive', 'neutral', 'negative'];
        if (!in_array($label, $validLabels, true)) {
            // Derive label from score if AI returned an unexpected value
            $label = $this->deriveLabelFromScore($score);
        }

        return [$score, $label];
    }

    /**
     * Derive a sentiment label from a numeric score.
     */
    private function deriveLabelFromScore(float $score): string
    {
        if ($score >= 0.2) {
            return 'positive';
        }

        if ($score <= -0.2) {
            return 'negative';
        }

        return 'neutral';
    }

    /**
     * Broadcast FeedbackAnalyzed event to the branch's private channel.
     * Resolves branch_id via session → employee → branch_id.
     *
     * Requirement: 11.7
     */
    private function broadcastAnalyzed(Feedback $feedback): void
    {
        try {
            $branchId = $this->resolveBranchId($feedback);

            broadcast(new FeedbackAnalyzed($feedback, $branchId));
        } catch (\Throwable $e) {
            Log::warning('SentimentAnalysisJob: Failed to broadcast FeedbackAnalyzed', [
                'feedback_id' => $this->feedbackId,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    /**
     * Resolve branch_id from feedback → session → employee → branch_id.
     */
    private function resolveBranchId(Feedback $feedback): int
    {
        try {
            $session  = $feedback->session ?? $feedback->load('session')->session;
            $employee = $session?->employee ?? $session?->load('employee')->employee;
            return (int) ($employee?->branch_id ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }
}
