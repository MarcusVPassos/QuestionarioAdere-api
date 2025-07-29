<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CotacaoGoogleController extends Controller
{
    public function index(Request $request)
    {
        $mes = $request->query('mes');
        $ano = $request->query('ano');
        $dia = $request->query('dia');

        if (!$mes || !$ano || !$dia) {
            return response()->json(['error' => 'Parâmetros mes, ano e dia são obrigatórios'], 400);
        }

        $url = 'https://script.google.com/macros/s/AKfycbwlXklGk2skVhaG-Qw7masPcapFrNkgpmn8ycvDzNuWLTpWQB1346MkSvh9tYquaBqing/exec';
        $data = sprintf('%d/%d/%d', $dia, $mes, $ano);

        $params = [
            'action' => 'resumoCotacoes',
            'data' => $data,
            'mes' => $mes,
            'ano' => $ano,
        ];

        $response = Http::get($url, $params);

        if (!$response->successful()) {
            return response()->json(['error' => 'Erro ao acessar planilha'], 500);
        }

        return response()->json($response->json());
    }

public function porDia(Request $request)
{
    $mes = $request->query('mes');
    $ano = $request->query('ano');

    if (!$mes || !$ano) {
        return response()->json(['error' => 'Parâmetros mes e ano são obrigatórios'], 400);
    }

    $url = 'https://script.google.com/macros/s/AKfycbwlXklGk2skVhaG-Qw7masPcapFrNkgpmn8ycvDzNuWLTpWQB1346MkSvh9tYquaBqing/exec';

    $params = [
        'action' => 'resumoCotacoesPorDia',
        'mes' => $mes,
        'ano' => $ano,
    ];

    $response = Http::get($url, $params);

    if (!$response->successful()) {
        return response()->json(['error' => 'Erro ao acessar planilha'], 500);
    }

    return response()->json($response->json());
}

}