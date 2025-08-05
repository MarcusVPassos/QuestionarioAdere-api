<?php

use App\Events\NovoQuestionarioCriado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\CotacaoGoogleController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\QuestionarioController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

// Rota pública para login
Route::post('/login', LoginController::class);

        // Route::get('/teste-pusher', function () {
        //     event(new NovoQuestionarioCriado(['msg' => 'funcionando do Laravel']));
        //     return response()->json(['status' => 'evento disparado']);
        // });

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


        Route::get('/cotacoes-google', [CotacaoGoogleController::class, 'index']);
        Route::get('/cotacoes-google/por-dia', [CotacaoGoogleController::class, 'porDia']); 
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/dashboard/disponiveis', [DashboardController::class, 'disponiveis']);

        // ✅ ROTA PARA FORÇAR ATUALIZAÇÃO DAS COTAÇÕES (manualmente)
        Route::get('/cotacoes-google/forcar', function (Request $request) {
            $mes = $request->query('mes');
            $ano = $request->query('ano');

            if (!$mes || !$ano) {
                return response()->json(['error' => 'Parâmetros mes e ano são obrigatórios'], 400);
            }

            $url = 'https://script.google.com/macros/s/AKfycbwlXklGk2skVhaG-Qw7masPcapFrNkgpmn8ycvDzNuWLTpWQB1346MkSvh9tYquaBqing/exec';
            $params = [
                'action' => 'resumoCotacoesPorDia',
                'mes' => $mes,
                'ano' => $ano,
            ];

            $response = Http::get($url, $params);

            if (!$response->successful()) {
                return response()->json(['error' => 'Erro ao acessar planilha'], 500);
            }

            // Atualiza o cache
            $cacheKey = "cotacoes_google_mes_{$ano}_{$mes}";
            Cache::put($cacheKey, $response->json(), now()->addMinutes(15)); // ou proximaRenovacao()

            return response()->json($response->json());
        });

        Route::get('/cotacoes-google/forcar-resumo', [CotacaoGoogleController::class, 'forcarAtualizacaoResumo']);
        Route::get('/dashboard/completo', [DashboardController::class, 'completo']);
    });
});
