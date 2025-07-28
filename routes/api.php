<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\QuestionarioController;
use App\Http\Controllers\UserController;

// Rota pública para login
Route::post('/login', LoginController::class);

// Rotas protegidas por Sanctum
Route::middleware('auth:sanctum')->group(function () {

    // Logout do token atual
    Route::post('/logout', function (Request $request) {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logout realizado com sucesso']);
    });

    // Questionários (acessível para user comum)
    Route::post('/questionarios', [QuestionarioController::class, 'store']);
    Route::get('/questionarios', [QuestionarioController::class, 'index']);
    Route::get('/questionarios/correcao', [QuestionarioController::class, 'emCorrecao']);
    Route::post('/questionarios/{id}/update', [QuestionarioController::class, 'update']);
    Route::get('/documentos/{documento}', [DocumentoController::class, 'download']);
    Route::get('/questionarios/anos-meses', [QuestionarioController::class, 'anosEMesesDisponiveis']);


    // Rotas exclusivas para supervisores
    Route::middleware('role:supervisor,diretoria')->group(function () {
        Route::patch('/questionarios/{id}/status', [QuestionarioController::class, 'atualizarStatus']);

        // Gerenciamento de usuários
        Route::post('/register', RegisterController::class);
        Route::get('/usuarios', [UserController::class, 'index']);
        Route::post('/usuarios', [UserController::class, 'store']);
        Route::put('/usuarios/{id}/role', [UserController::class, 'atualizarRole']);
        Route::put('/usuarios/{id}/senha', [UserController::class, 'atualizarSenha']);
        Route::delete('/usuarios/{id}', [UserController::class, 'destroy']);
    });

    // Rotas exclusivas para diretoria
    Route::middleware('role:diretoria')->group(function () {
        
    });
});
