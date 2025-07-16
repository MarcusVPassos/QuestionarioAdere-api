<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class RegisterController extends Controller
{
    public function __invoke(Request $request)
    {
        if ($request->user()->role !== 'supervisor') {
            return response()->json(['message' => 'Acesso negado.'], 403);
        }

        $data = $request->validate([
            'name' => 'required|string|unique:users',
            'password' => 'required|string|min:6',
            'role' => 'required|in:user,supervisor',
        ]);

        $user = User::create($data); // password já será hash por cast

        return response()->json([
            'message' => 'Usuário criado com sucesso',
            'user' => $user,
        ], 201);
    }
}
