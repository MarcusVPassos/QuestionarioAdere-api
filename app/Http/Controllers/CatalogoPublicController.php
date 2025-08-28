<?php

namespace App\Http\Controllers;

use App\Models\Carro;
use App\Models\Midia;
use App\Models\Modelo;
use Illuminate\Http\Request;

class CatalogoPublicController extends Controller
{
    public function midias()
    {
        return Midia::query()
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome']);
    }

    public function modelos(Request $request)
    {
        $tipo = $request->query('tipo'); // opcional

        return Modelo::query()
            ->where('ativo', true)
            ->when($tipo, fn($q) => $q->where('tipo', $tipo))
            ->orderBy('nome')
            ->get(['id', 'nome', 'tipo']);
    }

    public function carros(Request $request)
    {
        $tipos   = $request->query('tipos');   // opcional
        $modelo = $request->query('modelo'); // opcional (nome)

        $q = Carro::query()
            ->with('modelo:id,nome,tipo')
            ->where('ativo', true)
            ->when(
                $modelo,
                fn($w) => $w->whereHas('modelo', fn($m) => $m->where('nome', $modelo))
            )
            ->when($tipos, function ($w) use ($tipos) {
                $w->where(function ($x) use ($tipos) {
                    $x->whereHas('modelo', fn($m) => $m->where('tipo', $tipos))
                      ->orWhereJsonContains('tipos', $tipos);
                });
            })
            ->orderBy('titulo');

        return $q->get(['id', 'modelo_id', 'titulo', 'descricao', 'imagem_url', 'tipos']);
    }
}
