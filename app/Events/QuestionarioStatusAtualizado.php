<?php

namespace App\Events;

use App\Models\Questionario;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuestionarioStatusAtualizado implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Questionario $questionario) {
        $questionario->refresh()->load(['documentos','user']);
    }

    public function broadcastOn(): Channel {
        return new Channel('questionarios');
    }

    public function broadcastAs(): string {
        return 'questionario-status-atualizado';
    }

    public function broadcastWith(): array {
        return $this->questionario->toArray();
    }
}
