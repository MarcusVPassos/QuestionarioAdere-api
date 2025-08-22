<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UsuarioCriado implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public User $user) {}

    public function broadcastOn(): Channel
    {
        return new Channel('usuarios');
    }

    public function broadcastAs(): string
    {
        return 'usuario-criado';
    }

    public function broadcastWith(): array
    {
        return $this->user->only(['id', 'name', 'role', 'ativo', 'created_at']);
    }
}
