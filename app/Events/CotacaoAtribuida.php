<?php

namespace App\Events;

use App\Models\Cotacao;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CotacaoAtribuida implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public Cotacao $cotacao)
    {
        $this->cotacao = $cotacao->fresh(['vendedor', 'questionario']);
    }

    public function broadcastOn(): Channel
    {
        return new Channel('cotacoestwo');
    }

    public function broadcastAs(): string
    {
        return 'cotacao-atribuida';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->cotacao->id,
            'vendedor_id' => $this->cotacao->vendedor_id,
            'status' => $this->cotacao->status,
            'status_detalhe' => $this->cotacao->status_detalhe,
            'nome_razao' => $this->cotacao->nome_razao,
            'origem' => $this->cotacao->origem,
            // outros campos se quiser
        ];
    }
}
