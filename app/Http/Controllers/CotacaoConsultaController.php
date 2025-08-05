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

        // ✅ Importa caso ainda não existam dados no banco
        $existe = CotacaoGoogle::whereMonth('data', $mes)
            ->whereYear('data', $ano)
            ->exists();

        if (!$existe) {
            app(\App\Http\Controllers\CotacaoImportadorController::class)->importar(new Request([
                'mes' => $mes,
                'ano' => $ano
            ]));
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

        // ✅ Importa caso ainda não existam dados no banco
        $existe = CotacaoGoogle::whereMonth('data', $mes)
            ->whereYear('data', $ano)
            ->exists();

        if (!$existe) {
            app(\App\Http\Controllers\CotacaoImportadorController::class)->importar(new Request([
                'mes' => $mes,
                'ano' => $ano
            ]));
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
