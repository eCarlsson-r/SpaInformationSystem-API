<?php

namespace App\Events;

use App\Models\Session;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast to the customer on their private channel when a session is completed,
 * prompting them to submit post-session feedback.
 *
 * Requirement: 9.1
 */
class FeedbackPrompt implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $payload;

    public function __construct(
        public readonly Session $session,
        public readonly int     $customerId
    ) {
        $this->payload = [
            'session_id'   => $session->id,
            'customer_id'  => $customerId,
            'completed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Broadcast on the private customer channel.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("customer.{$this->customerId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'FeedbackPrompt';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
