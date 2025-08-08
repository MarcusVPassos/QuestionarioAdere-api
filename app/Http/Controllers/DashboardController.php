<?php

namespace App\Http\Controllers;

use App\Events\NovoQuestionarioCriado;
use App\Models\Questionario;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $ano = (int) $request->input('ano', now()->year);
        $mes = (int) $request->input('mes', now()->month);
        $dia = $request->input('dia');

        if (!is_numeric($mes) || $mes < 1 || $mes > 12) {
            return response()->json(['error' => 'Mês inválido'], 422);
        }

        if (!is_numeric($ano)) {
            return response()->json(['error' => 'Ano inválido'], 422);
        }

        // ⚡️ Quando há filtro por dia, ignora cache
        if ($dia) {
            return response()->json($this->calcularDashboard($ano, $mes, $dia));
        }

        // ✅ Com cache apenas para visão mensal
        $cacheKey = "dashboard:mes:$ano-$mes";

        return Cache::remember($cacheKey, now()->addMinutes(2), function () use ($ano, $mes) {
            return $this->calcularDashboard($ano, $mes);
        });
    }

    private function calcularDashboard(int $ano, int $mes, ?int $dia = null): array
    {
        if ($dia) {
            $data = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);

            return [
                'mensal' => [
                    'aprovados' => Questionario::whereDate('created_at', $data)->where('status', 'aprovado')->count(),
                    'pendentes' => Questionario::whereDate('created_at', $data)->where('status', 'pendente')->count(),
                    'negados'   => Questionario::whereDate('created_at', $data)->where('status', 'negado')->count(),
                    'correcao'  => Questionario::whereDate('created_at', $data)->where('status', 'correcao')->count(),
                    'cotacoes'  => 0,
                ],
                'diario' => [[
                    'data' => $data,
                    'questionarios' => Questionario::whereDate('created_at', $data)->count(),
                    'cotacoes' => 0
                ]]
            ];
        }

        // 📊 MENSAL
        $questionariosMensal = Questionario::selectRaw("status, COUNT(*) as total")
            ->whereYear('created_at', $ano)
            ->whereMonth('created_at', $mes)
            ->groupBy('status')
            ->pluck('total', 'status');

        $totalAprovados = $questionariosMensal['aprovado'] ?? 0;
        $totalCorrecao = $questionariosMensal['correcao'] ?? 0;

        $vendas = Questionario::where('status', 'aprovado')
            ->whereYear('created_at', $ano)
            ->whereMonth('created_at', $mes)
            ->get();

        $vendasPorTipo = ['diario'=>0,'mensal'=>0,'assinatura'=>0];
        $comissaoPorTipo = ['diario'=>0.0,'mensal'=>0.0,'assinatura'=>0.0];
        $comissaoTotal = 0.0;

        foreach ($vendas as $q) {
            $tipo = $q->tipo_venda ?? null;
            if (!$tipo) continue;
            if (!isset($vendasPorTipo[$tipo])) continue;
            $vendasPorTipo[$tipo]++;
            $comissaoPorTipo[$tipo] += (float) ($q->valor_comissao_calculado ?? 0);
            $comissaoTotal += (float) ($q->valor_comissao_calculado ?? 0);
        }



        $diasNoMes = Carbon::createFromDate($ano, $mes, 1)->daysInMonth;

        $diasDoMes = collect(range(1, $diasNoMes))->map(function ($dia) use ($ano, $mes) {
            $data = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
            return [
                'data' => $data,
                'questionarios' => Questionario::whereDate('created_at', $data)->count(),
                'cotacoes' => 0
            ];
        });

        return [
            'mensal' => [
                'aprovados' => $totalAprovados,
                'pendentes' => $questionariosMensal['pendente'] ?? 0,
                'negados'   => $questionariosMensal['negado'] ?? 0,
                'correcao'  => $totalCorrecao,
                'cotacoes'  => 0,
                'vendas_por_tipo' => $vendasPorTipo,
                'aproveitamento'  => 0,
                'comissao_total' => round($comissaoTotal, 2),
                'comissao_por_tipo' => array_map(fn($v)=>round($v,2), $comissaoPorTipo),
            ],
            'diario' => $diasDoMes
        ];
    }

    public function disponiveis()
    {
        $questionarios = Questionario::selectRaw('YEAR(created_at) as ano, MONTH(created_at) as mes')
            ->groupBy('ano', 'mes')
            ->get()
            ->toArray();

        return response()->json($questionarios);
    }

    public function completo(Request $request)
    {
        $ano = (int) $request->input('ano', now()->year);
        $mes = (int) $request->input('mes', now()->month);
        $dia = $request->input('dia');

        // ✅ Verifica se já tem cotações para o mês
        $temCotacoes = \App\Models\CotacaoGoogle::whereYear('data', $ano)
            ->whereMonth('data', $mes)
            ->exists();

        // ⚠️ Se não tiver, importa via controller
        if (!$temCotacoes) {
            app(\App\Http\Controllers\CotacaoImportadorController::class)->importar(new Request([
                'mes' => $mes,
                'ano' => $ano
            ]));
        }

        $cacheKey = "dashboard:completo:$ano-$mes-" . ($dia ?? 'todos');

        return Cache::remember($cacheKey, now()->addSeconds(120), function () use ($ano, $mes, $dia) {
            $dashboardReq = Request::create('/api/dashboard', 'GET', [
                'ano' => $ano,
                'mes' => $mes,
                'dia' => $dia
            ]);
            $dashboardResponse = app()->handle($dashboardReq);
            $dashboardData = json_decode($dashboardResponse->getContent(), true);

            $resumoReq = Request::create('/api/cotacoes', 'GET', [
                'ano' => $ano,
                'mes' => $mes,
                'dia' => $dia
            ]);
            $resumoResponse = app()->handle($resumoReq);
            $resumoData = json_decode($resumoResponse->getContent(), true);

            $porDiaReq = Request::create('/api/cotacoes/por-dia', 'GET', [
                'ano' => $ano,
                'mes' => $mes
            ]);
            $porDiaResponse = app()->handle($porDiaReq);
            $porDiaData = json_decode($porDiaResponse->getContent(), true);

            return [
                'dashboard' => $dashboardData,
                'resumo' => $resumoData,
                'agrupadas' => $porDiaData
            ];
        });
    }

public function porUsuario($id, Request $request)
{
    $ano = (int) $request->input('ano', now()->year);
    $mes = (int) $request->input('mes', now()->month);

    $baseQuery = Questionario::where('user_id', $id)
        ->whereYear('created_at', $ano)
        ->whereMonth('created_at', $mes);

    // Status
    $statusPorTipo = (clone $baseQuery)
        ->selectRaw('status, count(*) as total')
        ->groupBy('status')
        ->pluck('total', 'status');

    // Apenas aprovados para vendas e comissão
    $vendasAprovadas = (clone $baseQuery)
        ->where('status', 'aprovado')
        ->get(['tipo_venda','valor_comissao_calculado']);

    $vendasPorTipo = ['diario'=>0,'mensal'=>0,'assinatura'=>0];
    $comissaoPorTipo = ['diario'=>0.0,'mensal'=>0.0,'assinatura'=>0.0];
    $comissaoTotal = 0.0;

    foreach ($vendasAprovadas as $v) {
        $tipo = $v->tipo_venda ?? null;
        if (!isset($vendasPorTipo[$tipo])) continue;
        $vendasPorTipo[$tipo]++;
        $valor = (float) ($v->valor_comissao_calculado ?? 0);
        $comissaoPorTipo[$tipo] += $valor;
        $comissaoTotal += $valor;
    }

    return response()->json([
        'status' => [
            'aprovado' => $statusPorTipo['aprovado'] ?? 0,
            'pendente' => $statusPorTipo['pendente'] ?? 0,
            'correcao' => $statusPorTipo['correcao'] ?? 0,
            'negado'   => $statusPorTipo['negado'] ?? 0,
        ],
        'vendas_por_tipo' => $vendasPorTipo,
        'comissao_total' => round($comissaoTotal, 2),
        'comissao_por_tipo' => array_map(fn($v)=>round($v,2), $comissaoPorTipo),
    ]);
}

}
