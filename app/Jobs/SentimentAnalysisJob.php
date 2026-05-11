<?php

namespace App\Jobs;

use App\Events\FeedbackAnalyzed;
use App\Models\Feedback;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Analyses the sentiment of a submitted Feedback record using the AI SDK.
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
    public int $tries = 5;

    /**
     * Delay between retries in seconds.
     * Requirements: 10.6
     */
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(public readonly int $feedbackId)
    {
        $this->onQueue('sentiment-analysis');
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

        // Requirement 10.1, 10.3: call AI and parse score + label
        [$score, $label] = $this->analyzeWithAI($feedback->comment);
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
     * Call SentimentAgent and return [score, label].
     *
     * @return array{float, string}  [score ∈ [-1.0, 1.0], label ∈ {positive, neutral, negative}]
     * @throws \RuntimeException on AI failure (triggers retry)
     */
    private function analyzeWithAI(string $comment): array
    {
        try {
            $response = (new \App\Ai\Agents\SentimentAgent())->prompt($comment);

            return [
                (float) ($response['score'] ?? 0.0),
                (string) ($response['label'] ?? 'neutral'),
            ];
        } catch (\Throwable $e) {
            Log::warning('SentimentAnalysisJob: AI request failed', [
                'feedback_id' => $this->feedbackId,
                'error'       => $e->getMessage(),
            ]);
            throw new \RuntimeException('AI request failed: ' . $e->getMessage(), 0, $e);
        }
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
