<?php

namespace App\Http\Controllers;

use App\Services\BlueFleetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Bluefeet extends Controller
{
    public function buscarVeiculos()
    {
        $token = Cache::get('bluefleet_access_token');

        if (!$token) {
            return response()->json(['erro' => 'Token não disponível'], 401);
        }

        $resposta = Http::withToken($token)
            ->withHeaders([
                'accept' => 'text/plain',
            ])
            ->get('https://api.bluefleet.com.br/vehicle');

        if ($resposta->unauthorized()) {
            return response()->json(['erro' => 'Token inválido'], 401);
        }

        return response()->json($resposta->json());
    }

    public function buscarContrato()
    {
        $token = Cache::get('bluefleet_access_token');

        if (!$token) {
            return response()->json(['erro' => 'Token não disponível'], 401);
        }

        $resposta = Http::withToken($token)
            ->withHeaders([
                'accept' => 'text/plain',
            ])
            ->get('https://api.bluefleet.com.br/commercial/contracts');
            
            // /contract-item/search/?licensePlate=OVQ-6G49

        if ($resposta->unauthorized()) {
            return response()->json(['erro' => 'Token inválido'], 401);
        }

        return response()->json($resposta->json());
    }

 public function buscarContratoPorDocumento(Request $request)
    {
        $request->validate([
            'documentNumber' => 'required|string',
        ]);

        $token = Cache::get('bluefleet_access_token');
        if (!$token) {
            return response()->json(['erro' => 'Token não disponível'], 401);
        }

        $documentNumber = $request->query('documentNumber');

        $resposta = Http::withToken($token)
            ->acceptJson()
            ->get('https://api.bluefleet.com.br/contract-item/search', [
                // Passando via array o Laravel cuida da URL-encode (inclui a "/" -> %2F)
                'documentNumber' => $documentNumber,
                // Se a API suportar paginação, já deixo exemplo:
                // 'page' => 1,
                // 'pageSize' => 50,
            ]);

        if ($resposta->unauthorized()) {
            return response()->json(['erro' => 'Token inválido'], 401);
        }

        if ($resposta->failed()) {
            Log::warning('BlueFleet /contract-item/search falhou', [
                'status' => $resposta->status(),
                'body'   => $resposta->body(),
                'doc'    => $documentNumber,
            ]);

            return response()->json([
                'erro' => 'Falha ao consultar itens de contrato',
            ], $resposta->status());
        }

        // Normaliza a resposta (pode vir array direto ou objeto com "data" / "items")
        $json  = $resposta->json();
        $lista = is_array($json) ? $json : ($json['data'] ?? ($json['items'] ?? []));

        // Filtro defensivo por igualdade exata do documentNumber
        // (caso a API retorne "contains/startsWith")
        $exatos = collect($lista)
            ->filter(fn ($i) => isset($i['documentNumber']) && $i['documentNumber'] === $documentNumber)
            ->values()
            ->all();

        if (empty($lista) && empty($exatos)) {
            return response()->json([
                'mensagem' => 'Nenhum item encontrado para o documentNumber informado.',
                'documentNumber' => $documentNumber,
            ], 404);
        }

        // Se houve match exato, prioriza retorno dos exatos
        return response()->json(!empty($exatos) ? $exatos : $lista);
    }

    /**
     * Opcional: busca por placa dinâmica via query
     * GET /api/bluefleet/contract-item?licensePlate=OVQ-6G49
     */
    public function buscarContratoPorPlaca(Request $request)
    {
        $request->validate([
            'licensePlate' => 'required|string',
        ]);

        $token = Cache::get('bluefleet_access_token');
        if (!$token) {
            return response()->json(['erro' => 'Token não disponível'], 401);
        }

        $licensePlate = $request->query('licensePlate');

        $resposta = Http::withToken($token)
            ->acceptJson()
            ->get('https://api.bluefleet.com.br/contract-item/search', [
                'licensePlate' => $licensePlate,
            ]);

        if ($resposta->unauthorized()) {
            return response()->json(['erro' => 'Token inválido'], 401);
        }

        if ($resposta->failed()) {
            Log::warning('BlueFleet /contract-item/search por placa falhou', [
                'status' => $resposta->status(),
                'body'   => $resposta->body(),
                'placa'  => $licensePlate,
            ]);

            return response()->json([
                'erro' => 'Falha ao consultar itens de contrato por placa',
            ], $resposta->status());
        }

        return response()->json($resposta->json());
    }
}

