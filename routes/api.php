<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\QuestionarioController;
use App\Models\Questionario;



Route::post('/login', LoginController::class);
Route::post('/questionarios', [QuestionarioController::class, 'store']);
Route::get('/questionarios', [QuestionarioController::class, 'index']);
Route::get('/documentos/{documento}', [DocumentoController::class, 'download']);

Route::middleware('auth:sanctum')->group(function () {
    // Logout do token atual
    Route::post('/logout', function (Request $request) {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logout realizado com sucesso']);
    });

    // Cadastro de usuário (restrito ao supervisor)
    Route::middleware('role:supervisor')->post('/register', RegisterController::class);
});