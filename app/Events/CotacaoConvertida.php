<?php

namespace App\Events;

use App\Models\Cotacao;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CotacaoConvertida implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public Cotacao $cotacao)
    {
        $cotacao->refresh(); // caso precise garantir os relacionamentos
    }

    public function broadcastOn(): Channel
    {
        return new Channel('cotacoestwo'); // mesmo canal
    }

    public function broadcastAs(): string
    {
        return 'cotacao-convertida';
    }

    public function broadcastWith(): array
    {
        return $this->cotacao->only([
            'id', 'status', 'status_detalhe', 'vendedor_id', 'updated_at'
        ]);
    }
}
