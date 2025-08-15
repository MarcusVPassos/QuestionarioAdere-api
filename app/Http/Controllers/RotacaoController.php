<?php

namespace App\Http\Controllers;

use App\Services\RotacaoVendedoresService;
use Illuminate\Http\Request;

class RotacaoController extends Controller
{
    public function status()
    {
        [, , $ultimoNome, $proximoNome] = RotacaoVendedoresService::calcular();

        return response()->json([
            'ultimoVendedor'  => $ultimoNome,
            'proximoVendedor' => $proximoNome,
        ]);
    }
}
