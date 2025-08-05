<?php

namespace App\Http\Controllers;

use App\Models\CotacaoGoogle;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CotacaoConsultaController extends Controller
{
    // 🔹 Retorna lista de dias com total
    public function listar(Request $request)
    {
        $mes = $request->query('mes');
        $ano = $request->query('ano');

        if (!$mes || !$ano) {
            return response()->json(['error' => 'Informe mes e ano'], 400);
        }

        $dados = CotacaoGoogle::whereMonth('data', $mes)
            ->whereYear('data', $ano)
            ->orderBy('data')
            ->get()
            ->map(fn($registro) => [
                'dia' => Carbon::parse($registro->data)->day,
                'total' => $registro->total,
            ]);

        return response()->json($dados);
    }

    // 🔹 Retorna apenas o total do mês
    public function resumo(Request $request)
    {
        $mes = $request->query('mes');
        $ano = $request->query('ano');

        if (!$mes || !$ano) {
            return response()->json(['error' => 'Informe mes e ano'], 400);
        }

        $totalMes = CotacaoGoogle::whereMonth('data', $mes)
            ->whereYear('data', $ano)
            ->sum('total');

        return response()->json([
            'totalDia' => 0,
            'totalMes' => $totalMes,
        ]);
    }
}
