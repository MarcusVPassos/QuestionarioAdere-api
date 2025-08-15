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

class RotacaoAtualizada implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $payload;
    /**
     * Create a new event instance.
     */
    public function __construct(?string $ultimoVendedor = null, ?string $proximoVendedor = null)
    {
        $this->payload = [
            'ultimoVendedor'  => $ultimoVendedor,
            'proximoVendedor' => $proximoVendedor,
        ];
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('cotacoesloop'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'rotacao-atualizada';
    }
}
