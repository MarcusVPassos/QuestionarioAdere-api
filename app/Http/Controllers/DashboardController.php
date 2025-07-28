<?php

namespace App\Http\Controllers;

use App\Models\Cotacao;
use App\Models\Questionario;
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

        // 📅 FILTRO POR DIA
        if ($dia) {
            $dia = (int) $dia;
            $data = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);

            return response()->json([
                'mensal' => [
                    'aprovados' => Questionario::whereDate('created_at', $data)->where('status', 'aprovado')->count(),
                    'pendentes' => Questionario::whereDate('created_at', $data)->where('status', 'pendente')->count(),
                    'negados'   => Questionario::whereDate('created_at', $data)->where('status', 'negado')->count(),
                    'correcao'  => Questionario::whereDate('created_at', $data)->where('status', 'correcao')->count(),
                    'cotacoes'  => Cotacao::whereDate('data', $data)->count(),
                ],
                'diario' => [[
                    'data' => $data,
                    'questionarios' => Questionario::whereDate('created_at', $data)->count(),
                    'cotacoes' => Cotacao::whereDate('data', $data)->count(),
                ]]
            ]);
        }

        // 📆 LÓGICA MENSAL PADRÃO (caso não envie dia)
        $questionariosMensal = Questionario::selectRaw("status, COUNT(*) as total")
            ->whereYear('created_at', $ano)
            ->whereMonth('created_at', $mes)
            ->groupBy('status')
            ->pluck('total', 'status');

        $totalCorrecao = $questionariosMensal['correcao'] ?? 0;
        $totalAprovados = $questionariosMensal['aprovado'] ?? 0;

        $cotacoesMes = Cotacao::whereYear('data', $ano)
            ->whereMonth('data', $mes)
            ->count();

        // 💰 VENDAS POR TIPO DE CONTRATO (via dados->tempo)
        $vendas = Questionario::where('status', 'aprovado')
            ->whereYear('created_at', $ano)
            ->whereMonth('created_at', $mes)
            ->get();

        $vendasPorTipo = [
            'diario' => 0,
            'mensal' => 0,
            'anual' => 0,
        ];

        foreach ($vendas as $q) {
            $tempo = strtolower($q->dados['tempo'] ?? '');

            if (str_contains($tempo, 'dia')) {
                $vendasPorTipo['diario']++;
            } elseif (str_contains($tempo, 'mes') || str_contains($tempo, 'mensal')) {
                $vendasPorTipo['mensal']++;
            } elseif (str_contains($tempo, 'ano')) {
                $vendasPorTipo['anual']++;
            }
        }

        // 📈 Aproveitamento (%)
        $aproveitamento = $cotacoesMes > 0
            ? round(($totalAprovados / $cotacoesMes) * 100, 2)
            : 0;

        // ✅ Correção aqui com createFromDate
        $diasNoMes = Carbon::createFromDate($ano, $mes, 1)->daysInMonth;

        $diasDoMes = collect(range(1, $diasNoMes))->map(function ($dia) use ($ano, $mes) {
            $data = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);

            return [
                'data' => $data,
                'questionarios' => Questionario::whereDate('created_at', $data)->count(),
                'cotacoes' => Cotacao::whereDate('data', $data)->count(),
            ];
        });

        return response()->json([
            'mensal' => [
                'aprovados' => $totalAprovados,
                'pendentes' => $questionariosMensal['pendente'] ?? 0,
                'negados'   => $questionariosMensal['negado'] ?? 0,
                'correcao'  => $totalCorrecao,
                'cotacoes'  => $cotacoesMes,
                'vendas_por_tipo' => $vendasPorTipo,
                'aproveitamento'  => $aproveitamento
            ],
            'diario' => $diasDoMes
        ]);
    }

    public function disponiveis()
    {
        $questionarios = Questionario::selectRaw('YEAR(created_at) as ano, MONTH(created_at) as mes')
            ->groupBy('ano', 'mes')
            ->get()
            ->toArray();

        $cotacoes = Cotacao::selectRaw('YEAR(data) as ano, MONTH(data) as mes')
            ->groupBy('ano', 'mes')
            ->get()
            ->toArray();

        $todos = collect($questionarios)
            ->merge($cotacoes)
            ->unique(fn ($item) => $item['ano'] . '-' . $item['mes'])
            ->sortByDesc(fn ($item) => $item['ano'] * 100 + $item['mes'])
            ->values();

        return response()->json($todos);
    }
}
