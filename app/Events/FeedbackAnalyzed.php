<?php

namespace App\Events;

use App\Models\Feedback;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast to SpaCashier managers on the branch's private channel
 * after sentiment analysis completes, enabling real-time dashboard updates.
 *
 * Requirement: 11.7
 */
class FeedbackAnalyzed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $payload;

    public function __construct(
        public readonly Feedback $feedback,
        public readonly int      $branchId
    ) {
        $this->payload = [
            'feedback_id'      => $feedback->id,
            'session_id'       => $feedback->session_id,
            'customer_id'      => $feedback->customer_id,
            'rating'           => $feedback->rating,
            'sentiment_score'  => $feedback->sentiment_score,
            'sentiment_label'  => $feedback->sentiment_label,
            'analysis_status'  => $feedback->analysis_status,
            'analyzed_at'      => $feedback->analyzed_at?->toIso8601String(),
            'branch_id'        => $branchId,
        ];
    }

    /**
     * Broadcast on the private branch channel.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("branch.{$this->branchId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'feedback.analyzed';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
