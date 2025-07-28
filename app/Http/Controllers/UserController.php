<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class UserController extends Controller
{
    public function index()
    {
        return User::select('id', 'name', 'role', 'created_at')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:users,name',
            'password' => ['required', 'string', 'min:6'],
            'role' => 'required|in:user,supervisor,diretoria',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
        ]);

        return response()->json($user, 201);
    }

    public function atualizarRole(Request $request, $id)
    {
        $request->validate([
            'role' => 'required|in:user,supervisor,diretoria',
        ]);

        $user = User::findOrFail($id);
        $user->role = $request->role;
        $user->save();

        return response()->json(['message' => 'Poder atualizado']);
    }

    public function atualizarSenha(Request $request, $id)
    {
        $request->validate([
            'password' => ['required', 'string', 'min:6'],
        ]);

        $user = User::findOrFail($id);
        $user->password = Hash::make($request->password); // reforça o hash mesmo com cast
        $user->save();

        return response()->json(['message' => 'Senha atualizada com sucesso']);
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        if (request()->user()?->id === $user->id) {
            return response()->json(['error' => 'Você não pode excluir a si mesmo'], 403);
        }

        $user->delete();
        return response()->json(['message' => 'Usuário excluído']);
    }
}
