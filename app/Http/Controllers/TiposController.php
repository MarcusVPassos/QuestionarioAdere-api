<?php

namespace App\Http\Controllers;

use App\Models\Tipos;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TiposController extends Controller
{
    public function index(Request $request) {
        $somenteAtivos = $request->boolean('ativos');
        return Tipos::query()
            ->when($somenteAtivos, fn($q)=>$q->where('ativo',true))
            ->orderBy('nome')
            ->get();
    }

    public function store(Request $request) {
        $data = $request->validate([
            'nome'  => ['required','string','max:80','unique:tipos,nome'],
            'ativo' => ['boolean']
        ]);
        return response()->json(Tipos::create($data), 201);
    }

    public function update(Request $request, Tipos $tipos) {
        $data = $request->validate([
            'nome'  => ['required','string','max:80', Rule::unique('tipos','nome')->ignore($tipos->id)],
            'ativo' => ['boolean']
        ]);
        $tipos->update($data);
        return response()->json($tipos);
    }

    public function destroy(Tipos $tipos) {
        $tipos->delete();
        return response()->json(['message'=>'Excluído']);
    }
}
