<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Bluefeet;
use App\Http\Controllers\CotacaoController;
use App\Http\Controllers\CotacaoSiteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\QuestionarioController;
use App\Http\Controllers\RotacaoController;
use App\Http\Controllers\UserController;
use App\Services\BlueFleetService;

Route::middleware('throttle:30,1')->group(function () {
    Route::post('/cotacoes/public', [CotacaoSiteController::class, 'store']);
});

Route::get('/bluefleet-token', function (BlueFleetService $service) {
    return response()->json([
        'access_token' => $service->getAccessToken(),
    ]);
});

Route::get('/bluefleet-veiculos', [Bluefeet::class, 'buscarVeiculos']);

// Rota pública para login
Route::post('/login', LoginController::class);

        // Route::get('/teste-pusher', function () {
        //     event(new NovoQuestionarioCriado(['msg' => 'funcionando do Laravel']));
        //     return response()->json(['status' => 'evento disparado']);
        // });

        
        Route::post('/questionarios/preview-comissao', [QuestionarioController::class, 'previewComissao'])->middleware('auth:sanctum');

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
    Route::patch('/questionarios/{id}/status', [QuestionarioController::class, 'atualizarStatus']);
    Route::get('/documentos/{documento}', [DocumentoController::class, 'download']);
    Route::get('/questionarios/anos-meses', [QuestionarioController::class, 'anosEMesesDisponiveis']);

    // --- COTAÇÕES ---
    // Recepção + gestão + vendedor (index filtra por role no controller)
    Route::get('/cotacoes', [CotacaoController::class, 'index']);
    Route::get('/cotacoes/buscar', [CotacaoController::class, 'buscar']);
    Route::get('/rotacao/status', [RotacaoController::class, 'status']);

    // Só recepção/supervisor/diretoria
    Route::middleware('role:recepcao,supervisor,diretoria')->group(function () {
        Route::post('/cotacoes/{cotacao}/atribuir', [CotacaoController::class, 'atribuir']);
        Route::get('/vendedores', [UserController::class, 'vendedores']); 
    });

    // Vendedor dono OU gestão
    Route::patch('/cotacoes/{cotacao}/status', [CotacaoController::class, 'atualizarStatus']);
    Route::post('/cotacoes/{cotacao}/converter', [CotacaoController::class, 'converter']);
    Route::patch('/questionarios/{id}/vincular-cotacao', [QuestionarioController::class, 'vincularCotacao']);



    // Rotas exclusivas para supervisores
    Route::middleware('role:supervisor,diretoria')->group(function () {
        Route::patch('/questionarios/{id}/status', [QuestionarioController::class, 'atualizarStatus']);

        // Gerenciamento de usuários
        Route::post('/register', RegisterController::class);
        Route::get('/usuarios', [UserController::class, 'index']);
        Route::post('/usuarios', [UserController::class, 'store']);
        Route::put('/usuarios/{id}/role', [UserController::class, 'atualizarRole']);
        Route::put('/usuarios/{id}/senha', [UserController::class, 'atualizarSenha']);
        Route::patch('/usuarios/{id}/inativar', [UserController::class, 'inativar']);
        Route::patch('/usuarios/{id}/reativar', [UserController::class, 'reativar']);
    });

    // Rotas exclusivas para diretoria
    Route::middleware('role:diretoria')->group(function () {
        Route::patch('/usuarios/{id}/inativar', [UserController::class, 'inativar'])->middleware('auth:sanctum');

        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/dashboard/disponiveis', [DashboardController::class, 'disponiveis']);

        Route::get('/dashboard/completo', [DashboardController::class, 'completo']);

        Route::get('/dashboard/usuario/{id}', [DashboardController::class, 'porUsuario']);
    });
});
