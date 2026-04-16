<?php

namespace App\Events;

use App\Models\Conflict;
use App\Models\Session;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast to the affected customer on their private channel when a
 * scheduling conflict is detected for their booking, providing rescheduling
 * suggestions.
 *
 * Requirements: 7.1, 7.2, 7.5
 */
class ReschedulingSuggestion implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $payload;

    public function __construct(
        public readonly Conflict $conflict,
        public readonly Session  $booking,
        public readonly int      $customerId
    ) {
        $this->payload = [
            'conflict_id'            => $conflict->id,
            'booking_id'             => $conflict->booking_id,
            'conflicting_booking_id' => $conflict->conflicting_booking_id,
            'conflict_type'          => $conflict->conflict_type,
            'detection_timestamp'    => $conflict->detection_timestamp?->toIso8601String(),
            'alternative_slots'      => $conflict->alternative_slots ?? [],
            'customer_id'            => $customerId,
            'branch_id'              => $conflict->branch_id,
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("customer.{$this->customerId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ReschedulingSuggestion';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
