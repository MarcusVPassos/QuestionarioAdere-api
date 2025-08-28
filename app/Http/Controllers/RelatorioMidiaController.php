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

    $inicio = \Carbon\Carbon::createFromDate($ano, $mes, 1)->startOfDay();
    $fim    = (clone $inicio)->endOfMonth();

    $totalMes = \App\Models\Cotacao::whereBetween('created_at', [$inicio, $fim])->count();

    // agrega por ORIGEM (não existe coluna "nome" em cotacoes)
    $linhas = \App\Models\Cotacao::query()
        ->selectRaw("
            CASE
                WHEN origem IN ('instagram','facebook','Instagram','Facebook') THEN 'Meta Ads'
                WHEN origem IS NULL OR origem = '' THEN 'outro'
                ELSE origem
            END AS origem_nome,
            COUNT(*) AS total
        ")
        ->whereBetween('created_at', [$inicio, $fim])
        ->groupBy('origem_nome')
        ->orderByDesc('total')
        ->get();

    // mídias ativas cadastradas
    $midias = \App\Models\Midia::where('ativo', true)->get(['id','nome']);

    $itens = [];

    // monta os itens já retornados pelo agrupamento
    foreach ($linhas as $row) {
        $nome = (string) $row->origem_nome; // vem do alias do CASE
        // procura pela coluna 'nome' dentro das mídias
        $m   = $midias->firstWhere('nome', $nome);
        $id  = $m?->id;
        $qtd = (int) $row->total;
        $pct = $totalMes > 0 ? round($qtd * 100 / $totalMes, 1) : 0.0;

        $itens[$nome] = [
            'midia'   => ['id' => $id, 'nome' => $nome],
            'total'   => $qtd,
            'percent' => $pct,
        ];
    }

    // completa com zeros para mídias que não apareceram no período
    // (Instagram/Facebook colapsam em 'meta-ads')
    $nomesZero = $midias->pluck('nome')
        ->map(fn ($n) => in_array(strtolower($n), ['instagram','facebook', 'Facebook', 'Instagram']) ? 'Meta Ads' : $n)
        ->unique()
        ->values();

    foreach ($nomesZero as $nome) {
        if (!isset($itens[$nome])) {
            $m  = $midias->firstWhere('nome', $nome);
            $id = $m?->id; // pode ser null para 'meta-ads'

            $itens[$nome] = [
                'midia'   => ['id' => $id, 'nome' => $nome],
                'total'   => 0,
                'percent' => 0.0,
            ];
        }
    }

    // ordena por total desc e transforma em array sequencial
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