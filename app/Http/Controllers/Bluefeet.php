<?php

namespace App\Http\Controllers;

use App\Services\BlueFleetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Bluefeet extends Controller
{
    public function buscarVeiculos()
    {
        $token = Cache::get('bluefleet_access_token');

        if (!$token) {
            return response()->json(['erro' => 'Token não disponível'], 401);
        }

        $resposta = Http::withToken($token)
            ->withHeaders([
                'accept' => 'text/plain',
            ])
            ->get('https://api.bluefleet.com.br/vehicle');

        if ($resposta->unauthorized()) {
            return response()->json(['erro' => 'Token inválido'], 401);
        }

        return response()->json($resposta->json());
    }
}
