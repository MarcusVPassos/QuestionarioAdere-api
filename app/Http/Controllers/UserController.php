<?php

namespace App\Http\Controllers;

use App\Events\RoleUsuarioAtualizada;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $incluir = (bool) $request->boolean('incluir_inativos', false);

        return User::query()
            ->select('id','name','role','ativo','created_at')
            ->visiveis($incluir)
            ->orderBy('name')
            ->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:users,name',
            'password' => ['required','string','min:6'],
            'role' => 'required|in:user,supervisor,diretoria,recepcao,marketing',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'password' => $validated['password'], // cast hashed
            'role' => $validated['role'],
            'ativo' => true,
        ]);

        return response()->json($user, 201);
    }

    public function atualizarRole(Request $request, $id)
    {
        $request->validate([
            'role' => 'required|in:user,supervisor,diretoria,recepcao,marketing',
        ]);

        $user = User::findOrFail($id);
        $user->role = $request->role;
        $user->save();

        event(new RoleUsuarioAtualizada($user));

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

    public function inativar(Request $request, $id)
    {
        $authId = $request->user()?->id ?? null; // se usar Route Model Binding, troque o $request
        $user = User::findOrFail($id);

        if ($authId === $user->id) {
            return response()->json(['error' => 'Você não pode se inativar'], 403);
        }

        $user->ativo = false;
        event(new \App\Events\UsuarioInativado($user->id));
        $user->save();

        // Revoga tokens ativos do usuário inativado
        $user->tokens()->delete();

        return response()->json(['message' => 'Usuário inativado com sucesso']);
    }

    public function reativar($id)
    {
        $user = User::findOrFail($id);
        $user->ativo = true;
        $user->save();

        return response()->json(['message' => 'Usuário reativado com sucesso']);
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

    public function vendedores(Request $request)
    {
        // apenas ativos, role=user
        return User::query()
            ->select('id','name')
            ->where('role', 'user')
            ->ativos()
            ->orderBy('name')
            ->get();
    }
}
