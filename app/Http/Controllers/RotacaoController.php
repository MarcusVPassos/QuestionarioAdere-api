<?php

namespace App\Http\Controllers;

use App\Models\Cotacao;
use App\Models\Rotacao;
use App\Models\User;
use App\Services\RotacaoVendedoresService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RotacaoController extends Controller
{
public function status()
{
    $rotacao = Rotacao::find(1); // persistido!

    return [
        'ultimoVendedor'  => $rotacao?->ultimo_id ? User::find($rotacao->ultimo_id)?->name : null,
        'proximoVendedor' => $rotacao?->proximo_id ? User::find($rotacao->proximo_id)?->name : null,
    ];
}
}
