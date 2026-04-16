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
 * Broadcast to SpaCashier staff on the branch's private channel when a
 * scheduling conflict is detected.
 *
 * Requirements: 6.4, 6.5
 */
class ConflictDetected implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $payload;

    public function __construct(
        public readonly Conflict $conflict,
        public readonly Session  $booking,
        public readonly Session  $conflictingBooking
    ) {
        $this->payload = [
            'conflict_id'              => $conflict->id,
            'booking_id'               => $conflict->booking_id,
            'conflicting_booking_id'   => $conflict->conflicting_booking_id,
            'conflict_type'            => $conflict->conflict_type,
            'detection_timestamp'      => $conflict->detection_timestamp?->toIso8601String(),
            'resolution_status'        => $conflict->resolution_status,
            'alternative_slots'        => $conflict->alternative_slots ?? [],
            'branch_id'                => $conflict->branch_id,
            'booking_start'            => $booking->start,
            'booking_end'              => $booking->end,
            'conflicting_booking_start' => $conflictingBooking->start,
            'conflicting_booking_end'  => $conflictingBooking->end,
            'employee_id'              => $booking->employee_id,
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("branch.{$this->conflict->branch_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ConflictDetected';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
