<?php

namespace App\Http\Controllers;

use App\Models\CotacaoGoogle;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CotacaoImportadorController extends Controller
{
    public function importar(Request $request)
    {
        $mes = $request->query('mes') ?? now()->month;
        $ano = $request->query('ano') ?? now()->year;

        $url = 'https://script.google.com/macros/s/AKfycbwlXklGk2skVhaG-Qw7masPcapFrNkgpmn8ycvDzNuWLTpWQB1346MkSvh9tYquaBqing/exec';
        $params = [
            'action' => 'resumoCotacoesPorDia',
            'mes' => $mes,
            'ano' => $ano,
        ];

        $resposta = Http::timeout(15)->get($url, $params);

        if (!$resposta->successful()) {
            return response()->json(['erro' => 'Falha ao acessar Apps Script'], 500);
        }

        $dados = $resposta->json(); // array de { dia, total }

        foreach ($dados as $item) {
            $data = Carbon::createFromDate($ano, $mes, $item['dia'])->format('Y-m-d');

            CotacaoGoogle::updateOrCreate(
                ['data' => $data],
                ['total' => $item['total']]
            );
        }

        return response()->json([
            'status' => 'Importação concluída',
            'mes' => $mes,
            'ano' => $ano,
            'registros' => count($dados),
        ]);
    }
}
