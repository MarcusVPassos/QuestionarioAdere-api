<?php

namespace App\Services;

use App\Models\Cotacao;
use App\Models\User;

class RotacaoVendedoresService
{
    /** Retorna [ultimoId, proximoId, ultimoNome, proximoNome] */
    public static function calcular(): array
    {
        // Vendedores elegíveis (ativos) em ordem crescente de ID
        $ids = User::query()
            ->where('role', 'user')
            ->where('ativo', true) // ajuste se sua coluna for diferente
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if (empty($ids)) {
            return [null, null, null, null];
        }

        // Último vendedor atribuído (pela cotação mais recente com vendedor_id)
        $ultimo = Cotacao::query()
            ->whereNotNull('vendedor_id')
            ->orderByDesc('id')
            ->value('vendedor_id');

        // Se nunca houve atribuição, o "último" é nulo e o próximo será o primeiro da lista
        $proximoId = $ids[0];
        $ultimoId = $ultimo;

        if ($ultimo && in_array($ultimo, $ids, true)) {
            // Pega o índice do último e move para o próximo (com loop)
            $idx = array_search($ultimo, $ids, true);
            $proximoId = $ids[ ($idx + 1) % count($ids) ];
        }

        $ultimoNome  = $ultimoId ? User::whereKey($ultimoId)->value('name') : null;
        $proximoNome = User::whereKey($proximoId)->value('name');

        return [$ultimoId, $proximoId, $ultimoNome, $proximoNome];
    }
}
