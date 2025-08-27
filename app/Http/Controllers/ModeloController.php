<?php

namespace App\Http\Controllers;

use App\Models\Modelo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

function podeGerenciarMarketing(Request $request): bool {
    $role = $request->user()?->role;
    return in_array($role, ['diretoria','marketing'], true);
}

class ModeloController extends Controller
{
    public function index(Request $request) {
        $tipo = $request->query('tipo');
        return Modelo::query()
            ->when($tipo, fn($q) => $q->where('tipo', $tipo))
            ->orderBy('nome')
            ->get();
    }

    public function store(Request $request) {

        if (!podeGerenciarMarketing($request)) {
            return response()->json(['error'=>'Sem permissão'], 403);
        }

        $data = $request->validate([
            'nome' => 'required|string|unique:modelos,nome',
            'tipo' => ['required','string', Rule::exists('tipos','nome')->where('ativo', 1)],
            'ativo'=> 'boolean',
        ]);

        return response()->json(Modelo::create($data), 201);
    }

    public function update(Request $request, Modelo $modelo) {

        if (!podeGerenciarMarketing($request)) {
            return response()->json(['error'=>'Sem permissão'], 403);
        }

        $data = $request->validate([
            'nome' => 'required|string|unique:modelos,nome,'.$modelo->id,
            'tipo' => ['required','string', Rule::exists('tipos','nome')->where('ativo', 1)],
            'ativo'=> 'boolean',
        ]);

        $modelo->update($data);
        return response()->json($modelo);
    }

    public function destroy(Request $request, Modelo $modelo) {
        if (!podeGerenciarMarketing($request)) return response()->json(['error'=>'Sem permissão'], 403);
        $modelo->delete();
        return response()->json(['message'=>'Excluído']);
    }
}
