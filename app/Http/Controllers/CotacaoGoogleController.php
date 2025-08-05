<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CotacaoGoogleController extends Controller
{
    private function proximaRenovacao()
    {
        $agora = now();
        $horarios = [
            ['hora' => 8,  'min' => 0],
            ['hora' => 10, 'min' => 0],
            ['hora' => 12, 'min' => 0],
            ['hora' => 14, 'min' => 0],
            ['hora' => 16, 'min' => 0],
            ['hora' => 17, 'min' => 45],
        ];

        foreach ($horarios as $h) {
            $proxima = now()->setTime($h['hora'], $h['min']);
            if ($proxima->isAfter($agora)) {
                return $proxima;
            }
        }

        // Se passou das 17:45, expira no próximo dia às 08:00
        return now()->addDay()->setTime(8, 0);
    }

    public function index(Request $request)
    {
        $mes = $request->query('mes');
        $ano = $request->query('ano');
        $dia = $request->query('dia');

        if (!$mes || !$ano) {
            return response()->json(['error' => 'Parâmetros mes e ano são obrigatórios'], 400);
        }

        $expire = $this->proximaRenovacao();

        // 📅 Modo diário
        if ($dia) {
            $cacheKey = "cotacoes_google_dia_{$ano}_{$mes}_{$dia}";

            $cached = Cache::remember($cacheKey, $expire, function () use ($mes, $ano, $dia) {
                $url = 'https://script.google.com/macros/s/AKfycbwlXklGk2skVhaG-Qw7masPcapFrNkgpmn8ycvDzNuWLTpWQB1346MkSvh9tYquaBqing/exec';
                $data = sprintf('%d/%d/%d', $dia, $mes, $ano);
                $params = [
                    'action' => 'resumoCotacoes',
                    'data' => $data,
                    'mes' => $mes,
                    'ano' => $ano,
                ];

                $response = Http::get($url, $params);
                return $response->successful() ? $response->json() : null;
            });

            if (!$cached) {
                return response()->json(['error' => 'Erro ao acessar planilha'], 500);
            }

            return response()->json($cached);
        }

        // 📆 Modo mensal
    $cacheKey = "cotacoes_google_mes_total_{$ano}_{$mes}";
    $cached = Cache::remember($cacheKey, $expire, function () use ($mes, $ano) {
        $url = 'https://script.google.com/macros/s/AKfycbwlXklGk2skVhaG-Qw7masPcapFrNkgpmn8ycvDzNuWLTpWQB1346MkSvh9tYquaBqing/exec';
        $params = [
            'action' => 'resumoCotacoesPorDia',
            'mes' => $mes,
            'ano' => $ano,
        ];

        $response = Http::timeout(10)->get($url, $params);
        if (!$response->successful()) return null;

        $dados = $response->json(); // array de { dia, total }
        $soma = collect($dados)->sum('total');

        return [
            'totalDia' => 0, // ou null
            'totalMes' => $soma
        ];
    });

        if (!$cached) {
            return response()->json(['error' => 'Erro ao acessar planilha'], 500);
        }

        return response()->json($cached);
    }



    public function porDia(Request $request)
    {
        $mes = $request->query('mes');
        $ano = $request->query('ano');

        if (!$mes || !$ano) {
            return response()->json(['error' => 'Parâmetros mes e ano são obrigatórios'], 400);
        }

        $cacheKey = "cotacoes_google_mes_{$ano}_{$mes}";
        $expire = $this->proximaRenovacao();
        $cached = Cache::remember($cacheKey, $expire, function () use ($mes, $ano) {
            $url = 'https://script.google.com/macros/s/AKfycbwlXklGk2skVhaG-Qw7masPcapFrNkgpmn8ycvDzNuWLTpWQB1346MkSvh9tYquaBqing/exec';
            $params = [
                'action' => 'resumoCotacoesPorDia',
                'mes' => $mes,
                'ano' => $ano,
            ];

            $response = Http::get($url, $params);
            return $response->successful() ? $response->json() : null;
        });

        if (!$cached) {
            return response()->json(['error' => 'Erro ao acessar planilha'], 500);
        }

        return response()->json($cached);
    }

    public function forcarAtualizacaoResumo(Request $request)
    {
        $mes = $request->query('mes');
        $ano = $request->query('ano');
        $dia = $request->query('dia');

        if (!$mes || !$ano || !$dia) {
            return response()->json(['error' => 'Parâmetros obrigatórios: dia, mes e ano'], 400);
        }

        // 🔥 Chaves que serão limpas
        $keysApagadas = [];

        // Cache diário
        $dailyKey = "cotacoes_google_dia_{$ano}_{$mes}_{$dia}";
        if (Cache::has($dailyKey)) {
            Cache::forget($dailyKey);
            $keysApagadas[] = $dailyKey;
        }

        // Cache mensal total (soma)
        $monthlyTotalKey = "cotacoes_google_mes_total_{$ano}_{$mes}";
        if (Cache::has($monthlyTotalKey)) {
            Cache::forget($monthlyTotalKey);
            $keysApagadas[] = $monthlyTotalKey;
        }

        // Cache mensal por dia
        $monthlyPerDayKey = "cotacoes_google_mes_{$ano}_{$mes}";
        if (Cache::has($monthlyPerDayKey)) {
            Cache::forget($monthlyPerDayKey);
            $keysApagadas[] = $monthlyPerDayKey;
        }

        return response()->json([
            'status' => 'cache apagado',
            'chaves' => $keysApagadas
        ]);
    }


}