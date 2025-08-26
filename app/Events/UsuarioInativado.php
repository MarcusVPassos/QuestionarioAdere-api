<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UsuarioInativado implements ShouldBroadcastNow
{
    public function __construct(public int $userId) {}

    public function broadcastOn(): Channel
    {
        return new Channel('usuarios');
    }

    public function broadcastAs(): string
    {
        return 'inativado';
    }

    public function broadcastWith(): array
    {
        return ['user_id' => $this->userId];
    }
}