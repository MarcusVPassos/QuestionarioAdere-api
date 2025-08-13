<?php

namespace App\Http\Controllers;

use App\Events\CotacaoAtualizada;
use App\Events\CotacaoCriada;
use App\Http\Requests\StoreCotacaoSite;
use App\Models\Cotacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CotacaoSiteController extends Controller
{
    public function store(StoreCotacaoSite $request): JsonResponse
    {
        $data = $request->validated();

        // normalizações
        $pessoa = strtolower($data['pessoa'] ?? '');
        if ($pessoa === 'física' || $pessoa === 'fisica') $pessoa = 'pf';
        if ($pessoa === 'jurídica' || $pessoa === 'juridica') $pessoa = 'pj';

        // origem META
        $origem = $data['origem'] ?? null;
        if (in_array($origem, ['Instagram','Facebook'], true)) {
            $origem = 'META ADS';
        }

        $cotacao = Cotacao::create([
            'pessoa'         => in_array($pessoa, ['pf','pj']) ? $pessoa : 'pf',
            'nome_razao'     => $data['nome'],
            'cpf'            => $data['cpf'] ?? null,
            'cnpj'           => $data['cnpj'] ?? null,
            'telefone'       => $data['telefone'],
            'email'          => $data['email'],
            'origem'         => $origem,
            'mensagem'       => $data['mensagem'] ?? null,
            'tipo_veiculo'   => $data['tipo_veiculo'] ?? null,
            'modelo_veiculo' => $data['modelo_veiculo'] ?? null,
            'data_precisa'   => $data['data_precisa'] ?? null,
            'tempo_locacao'  => $data['tempo_locacao'] ?? null,
            'status'         => 'novo',
            'canal_raw'      => $data['raw'] ?? null,
        ]);

        // broadcast
        event(new CotacaoAtualizada($cotacao));

        return response()->json([
            'status' => 'success',
            'id'     => $cotacao->id,
            'status_cotacao' => $cotacao->status,
        ], 201);
    }
}
