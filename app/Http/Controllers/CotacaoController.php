<?php

namespace App\Http\Controllers;

use App\Models\Cotacao;
use Illuminate\Http\Request;

class CotacaoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Usa a data enviada ou assume hoje
        $data = $request->input('data', now()->toDateString());

        // Cria a cotação, evita duplicadas no mesmo dia (opcional)
        Cotacao::create(['data' => $data]);

        return response()->json(['message' => 'Cotação registrada com sucesso.']);
    }

    /**
     * Display the specified resource.
     */
    public function show(Cotacao $cotacao)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Cotacao $cotacao)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Cotacao $cotacao)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Cotacao $cotacao)
    {
        //
    }
}
