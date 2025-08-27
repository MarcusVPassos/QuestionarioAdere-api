<?php

namespace App\Http\Controllers;

use App\Models\Cotacao;
use App\Models\Midia;
use App\Support\Periodo;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RelatorioMidiaController extends Controller
{
    public function distribuicao(Request $request)
    {
        $ano = (int) $request->query('ano', (int) now()->format('Y'));
        $mes = (int) $request->query('mes', (int) now()->format('n'));

        $inicio = Carbon::createFromDate($ano, $mes, 1)->startOfDay();
        $fim    = (clone $inicio)->endOfMonth();

        $totalMes = Cotacao::whereBetween('created_at', [$inicio, $fim])->count();

        $linhas = Cotacao::query()
            ->selectRaw("
                CASE
                    WHEN origem IN ('instagram','facebook') THEN 'meta-ads'
                    WHEN origem IS NULL OR origem = '' THEN 'outro'
                    ELSE origem
                END AS origem_slug,
                COUNT(*) as total
            ")
            ->whereBetween('created_at', [$inicio, $fim])
            ->groupBy('origem_slug')
            ->orderByDesc('total')
            ->get();

        $midias = Midia::where('ativo', true)->get(['id','nome','slug'])->keyBy('slug');

        $itens = [];
        foreach ($linhas as $row) {
            $slug = $row->origem_slug;
            $m = $midias->get($slug);
            $nome = $m?->nome ?? Str::headline(str_replace('-', ' ', $slug));
            $id   = $m?->id ?? null;
            $qtd  = (int) $row->total;
            $pct  = $totalMes > 0 ? round($qtd * 100 / $totalMes, 1) : 0.0;

            $itens[$slug] = [
                'midia'   => ['id'=>$id,'nome'=>$nome,'slug'=>$slug],
                'total'   => $qtd,
                'percent' => $pct,
            ];
        }

        foreach ($midias as $slug => $m) {
            if (!isset($itens[$slug])) {
                $itens[$slug] = [
                    'midia'   => ['id'=>$m->id,'nome'=>$m->nome,'slug'=>$slug],
                    'total'   => 0,
                    'percent' => 0.0,
                ];
            }
        }

        $itens = array_values(collect($itens)->sortByDesc('total')->all());

        return response()->json([
            'periodo' => [
                'inicio' => $inicio->toDateString(),
                'fim'    => $fim->toDateString(),
                'label'  => $inicio->translatedFormat('F/Y'),
            ],
            'total' => $totalMes,
            'itens' => $itens,
        ]);
    }
}