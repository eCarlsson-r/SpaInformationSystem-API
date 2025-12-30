<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EntityUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public string $type) {}

    public function broadcastOn(): array
    {
        // Public channel for shared metadata like branches/categories
        return [new Channel('app-sync')];
    }

    public function broadcastAs(): string
    {
        return 'entity.updated';
    }
}