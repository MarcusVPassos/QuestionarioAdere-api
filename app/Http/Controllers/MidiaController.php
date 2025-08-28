<?php

namespace App\Http\Controllers;

use App\Models\Midia;
use Illuminate\Http\Request;

function podeGerenciarMarketing(Request $request): bool {
    $role = $request->user()?->role;
    return in_array($role, ['diretoria','marketing'], true);
}

class MidiaController extends Controller
{
    public function index() {
        return Midia::orderBy('nome')->get();
    }

    public function store(Request $request) {
        if (!podeGerenciarMarketing($request)) return response()->json(['error'=>'Sem permissão'], 403);
        $data = $request->validate([
            'nome' => 'required|string|unique:midias,nome',
            'ativo'=> 'boolean',
        ]);
        return response()->json(Midia::create($data), 201);
    }

    public function update(Request $request, Midia $midia) {
        if (!podeGerenciarMarketing($request)) return response()->json(['error'=>'Sem permissão'], 403);
        $data = $request->validate([
            'nome' => 'required|string|unique:midias,nome,'.$midia->id,
            'ativo'=> 'boolean',
        ]);
        $midia->update($data);
        return response()->json($midia);
    }

    public function destroy(Request $request, Midia $midia) {
        if (!podeGerenciarMarketing($request)) return response()->json(['error'=>'Sem permissão'], 403);
        $midia->delete();
        return response()->json(['message'=>'Excluída']);
    }
}
