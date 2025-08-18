<?php

namespace App\Services;

use App\Events\RotacaoAtualizada;
use App\Models\Cotacao;
use App\Models\Rotacao;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class RotacaoVendedoresService
{
    public function calcular(Cotacao $cotacao)
    {
        $ids = User::where('role', 'user')
            ->where('ativo', true)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $rotacao = Rotacao::firstOrCreate(['id' => 1], ['ultimo_id' => null, 'proximo_id' => $ids[0]]);
        $ultimoId = $cotacao->vendedor_id; // ✅ ATUAL: vendedor recém atribuído

        // Calcula próximo com base no novo último
        $proximoId = $ids[0];
        if ($ultimoId && in_array($ultimoId, $ids)) {
            $idx = array_search($ultimoId, $ids);
            $proximoId = $ids[($idx + 1) % count($ids)];
        }

        $rotacao->update([
            'ultimo_id' => $ultimoId,
            'proximo_id' => $proximoId,
        ]);

        return [
            'ultimo_id' => $ultimoId,
            'ultimo_nome' => User::find($ultimoId)?->name,
            'proximo_id' => $proximoId,
            'proximo_nome' => User::find($proximoId)?->name,
        ];
    }
}
