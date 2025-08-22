<?php

namespace App\Http\Controllers;

use App\Models\Cotacao;
use App\Models\Questionario;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;

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
        if ($dia) {
            return response()->json($this->calcularDashboard($ano, $mes, $dia));
        }

        return response()->json($this->calcularDashboard($ano, $mes));
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
                    'cotacoes'  => Cotacao::whereDate('created_at', $data)->count(),
                ],
                'diario' => [[
                    'data' => $data,
                    'questionarios' => Questionario::whereDate('created_at', $data)->count(),
                    'cotacoes' => Cotacao::whereDate('created_at', $data)->count()
                ]]
            ];
        }

        $questionariosMensal = Questionario::selectRaw("status, COUNT(*) as total")
            ->whereYear('created_at', $ano)
            ->whereMonth('created_at', $mes)
            ->groupBy('status')
            ->pluck('total', 'status');

        $cotacoesMensal = Cotacao::whereYear('created_at', $ano)
            ->whereMonth('created_at', $mes)
            ->count();

        $tiposCotacao = ['diario', 'mensal', 'assinatura'];
        $cotacoesPorTipo = Cotacao::whereYear('created_at', $ano)
            ->whereMonth('created_at', $mes)
            ->selectRaw('LOWER(tipo_venda) as tipo, COUNT(*) as total')
            ->groupBy('tipo')
            ->pluck('total', 'tipo')
            ->toArray();

        $tiposCotacao = ['diario', 'mensal', 'assinatura'];
        foreach ($tiposCotacao as $tipo) {
            $cotacoesPorTipo[$tipo] = $cotacoesPorTipo[$tipo] ?? 0;
        }

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
                'cotacoes' => Cotacao::whereDate('created_at', $data)->count()
            ];
        });

        return [
            'mensal' => [
                'aprovados' => $totalAprovados,
                'pendentes' => $questionariosMensal['pendente'] ?? 0,
                'negados'   => $questionariosMensal['negado'] ?? 0,
                'correcao'  => $totalCorrecao,
                'cotacoes'  => $cotacoesMensal,
                'cotacoes_por_tipo' => $cotacoesPorTipo,
                'vendas_por_tipo' => $vendasPorTipo,
                'aproveitamento'  => $cotacoesMensal > 0 ? round(($totalAprovados / $cotacoesMensal) * 100) : 0,
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

        return response()->json([
            'dashboard' => $dashboardData,
            'resumo' => $resumoData,
            'agrupadas' => $porDiaData
        ]);
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

    public function vendedoresResumo(Request $request)
    {
        $ano  = (int) $request->input('ano', now()->year);
        $mes  = (int) $request->input('mes', now()->month);
        $incInativos = $request->boolean('incluir_inativos', false);

        // 1) Buscar todos os vendedores (role=user)
        $usuarios = User::query()
            ->select('id','name','role','ativo')
            ->where('role','user')
            ->when(!$incInativos, fn($q) => $q->where('ativo', true))
            ->orderBy('name')
            ->get();

        if ($usuarios->isEmpty()) {
            return response()->json([]);
        }

        $ids = $usuarios->pluck('id');

        // 2) Cotações recebidas no mês (com vendedor atribuído)
        $cotacoesPorVendedor = Cotacao::query()
            ->whereIn('vendedor_id', $ids)
            ->whereYear('created_at', $ano)
            ->whereMonth('created_at', $mes)
            ->selectRaw('vendedor_id, COUNT(*) as total')
            ->groupBy('vendedor_id')
            ->pluck('total', 'vendedor_id');

        // 3) Aprovados no mês por usuário (usando quem aprovou/criou o questionário)
        $aprovadosPorUsuario = Questionario::query()
            ->whereIn('user_id', $ids)
            ->where('status', 'aprovado')
            ->whereYear('created_at', $ano)
            ->whereMonth('created_at', $mes)
            ->selectRaw('user_id, COUNT(*) as total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        // 3b) Comissão total no mês por usuário (de questionários aprovados)
        $comissaoPorUsuario = Questionario::query()
            ->whereIn('user_id', $ids)
            ->where('status', 'aprovado')
            ->whereYear('created_at', $ano)
            ->whereMonth('created_at', $mes)
            ->selectRaw('user_id, SUM(valor_comissao_calculado) as total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        // 4) Montar resposta
        $resultado = $usuarios->map(function ($u) use ($cotacoesPorVendedor, $aprovadosPorUsuario, $comissaoPorUsuario) {
            $cot   = (int) ($cotacoesPorVendedor[$u->id] ?? 0);
            $aprov = (int) ($aprovadosPorUsuario[$u->id] ?? 0);
            $aprovPct = $cot > 0 ? round(($aprov / $cot) * 100) : 0;
            $comissao = (float) ($comissaoPorUsuario[$u->id] ?? 0);

            return [
                'id'               => $u->id,
                'name'             => $u->name,
                'ativo'            => (bool) $u->ativo,
                'cotacoes_mes'     => $cot,
                'aprovados_mes'    => $aprov,
                'aproveitamento'   => $aprovPct, // %
                'comissao'         => round((float) ($comissaoPorUsuario[$u->id] ?? 0), 2),
            ];
        })->values();

        return response()->json($resultado);
    }
}
