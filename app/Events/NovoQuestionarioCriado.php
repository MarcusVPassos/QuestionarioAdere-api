<?php

namespace App\Events;

use App\Models\Questionario;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NovoQuestionarioCriado implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $questionario;

    public function __construct(Questionario $q)
    {
        // já garantimos no controller que vem carregado
        $this->questionario = $q;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('questionarios');
    }

    public function broadcastAs(): string
    {
        return 'novo-questionario';
    }

    public function broadcastWith(): array
    {
        // return [
        //     // aqui virá id, tipo, dados, status, created_at E 'documentos'
        //     // 'questionario' => $this->questionario->toArray(),
        //     $this->questionario->load('documentos')->toArray(),
        // ];
        return $this->questionario->load('documentos')->toArray();
    }
}