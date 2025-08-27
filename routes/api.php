<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Bluefeet;
use App\Http\Controllers\CarrosController;
use App\Http\Controllers\CatalogoPublicController;
use App\Http\Controllers\CotacaoController;
use App\Http\Controllers\CotacaoSiteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\MidiaController;
use App\Http\Controllers\ModeloController;
use App\Http\Controllers\QuestionarioController;
use App\Http\Controllers\RelatorioMidiaController;
use App\Http\Controllers\RotacaoController;
use App\Http\Controllers\TiposController;
use App\Http\Controllers\UserController;
use App\Models\Tipos;
use App\Services\BlueFleetService;

Route::middleware('throttle:30,1')->group(function () {
    Route::post('/cotacoes/public', [CotacaoSiteController::class, 'store']);
});

// Catálogo público (somente leitura)
Route::get('/public/midias',  [CatalogoPublicController::class, 'midias']);
Route::get('/public/modelos', [CatalogoPublicController::class, 'modelos']);
Route::get('/public/carros',  [CatalogoPublicController::class, 'carros']);
Route::get('/public/tipos', fn () =>
    Tipos::where('ativo', true)->orderBy('nome')->get(['id','nome'])
);

Route::get('/bluefleet-token', function (BlueFleetService $service) {
    return response()->json([
        'access_token' => $service->getAccessToken(),
    ]);
});

Route::get('/bluefleet-veiculos', [Bluefeet::class, 'buscarVeiculos']);
Route::get('/bluefleet-contratos', [Bluefeet::class, 'buscarContrato']);
Route::get('/bluefleet-contratoss', [Bluefeet::class, 'buscarContratoPorDocumento']);

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
    Route::get('/cotacoes/hoje-total', [CotacaoController::class, 'totalHoje']);

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

        Route::get('/dashboard/vendedores', [DashboardController::class, 'vendedoresResumo']);
        Route::get('/dashboard/usuario/{id}', [DashboardController::class, 'porUsuario']);
    });

    // ========================= MARKETING (somente diretoria/marketing) =========================
    Route::middleware('role:diretoria,marketing')->group(function () {
        // MIDIAS
        Route::get   ('/midias',            [MidiaController::class,   'index']);   // listar (admin)
        Route::post  ('/midias',            [MidiaController::class,   'store']);   // criar
        Route::put   ('/midias/{midia}',    [MidiaController::class,   'update']);  // editar
        Route::delete('/midias/{midia}',    [MidiaController::class,   'destroy']); // excluir

        // MODELOS
        Route::get   ('/modelos',           [ModeloController::class,  'index']);
        Route::post  ('/modelos',           [ModeloController::class,  'store']);
        Route::put   ('/modelos/{modelo}',  [ModeloController::class,  'update']);
        Route::delete('/modelos/{modelo}',  [ModeloController::class,  'destroy']);

        // CARROS (suporta upload de imagem OU imagem_url)
        Route::get   ('/carros',            [CarrosController::class,  'index']);
        Route::post  ('/carros',            [CarrosController::class,  'store']);   // multipart/form-data (imagem) OU JSON (imagem_url)
        Route::put   ('/carros/{carro}',    [CarrosController::class,  'update']);
        Route::delete('/carros/{carro}',    [CarrosController::class,  'destroy']);

        // Tipos
        Route::get   ('/tipos',           [TiposController::class, 'index']);
        Route::post  ('/tipos',           [TiposController::class, 'store']);
        Route::put   ('/tipos/{tipos}',    [TiposController::class, 'update']);
        Route::delete('/tipos/{tipos}',    [TiposController::class, 'destroy']);

        // Relatório de mídias
        Route::get('/marketing/relatorios/midias', [RelatorioMidiaController::class, 'distribuicao']);
        Route::get('/marketing/relatorios/midias/serie', [RelatorioMidiaController::class, 'serieAnual']);
    });
});
