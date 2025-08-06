<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class CotacoesAtualizadas implements ShouldBroadcastNow
{
    use SerializesModels;

    public function broadcastOn()
    {
        return new Channel('cotacoes');
    }

    public function broadcastAs()
    {
        return 'CotacoesAtualizadas';
    }
}
