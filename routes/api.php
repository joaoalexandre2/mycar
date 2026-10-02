<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\VeiculoController;
use App\Http\Controllers\OrdemServicoController;
use App\Http\Controllers\ManutencaoController;
use App\Http\Controllers\FipeController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\PasswordResetController;

// Autenticação
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

// Cadastro de uma nova oficina + usuário administrador dela.
Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:5,1');
Route::post('/email/reenviar', [RegisterController::class, 'reenviar'])->middleware('throttle:5,1');

// Link clicado a partir do e-mail de confirmação (assinado, sem precisar de token).
Route::get('/email/verificar/{id}/{hash}', [EmailVerificationController::class, 'confirmar'])
    ->middleware('signed')
    ->name('verificacao.confirmar');

// Esqueci minha senha
Route::post('/password/esqueci', [PasswordResetController::class, 'enviar'])->middleware('throttle:5,1');
Route::post('/password/redefinir', [PasswordResetController::class, 'redefinir'])->middleware('throttle:5,1');

Route::middleware('auth.token')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Clientes
    Route::post('/clientes', [ClienteController::class, 'store']);
    Route::get('/clientes', [ClienteController::class, 'index']);
    Route::get('/clientes/{id}', [ClienteController::class, 'show']);
    Route::put('/clientes/{id}', [ClienteController::class, 'update']);
    Route::delete('/clientes/{id}', [ClienteController::class, 'destroy']);

    // Veiculos
    Route::post('/veiculos', [VeiculoController::class, 'store']);
    Route::get('/veiculos', [VeiculoController::class, 'index']);
    Route::get('/veiculos/{id}', [VeiculoController::class, 'show']);
    Route::put('/veiculos/{id}', [VeiculoController::class, 'update']);
    Route::delete('/veiculos/{id}', [VeiculoController::class, 'destroy']);
    Route::post('/veiculos/{id}/fipe', [VeiculoController::class, 'consultarFipe']);
    Route::get('/veiculos/{id}/historico', [VeiculoController::class, 'historico']);

    // Tabela FIPE
    Route::get('/fipe/marcas', [FipeController::class, 'marcas']);
    Route::get('/fipe/marcas/{marca}/modelos', [FipeController::class, 'modelos']);
    Route::get('/fipe/marcas/{marca}/modelos/{modelo}/anos', [FipeController::class, 'anos']);
    Route::get('/fipe/marcas/{marca}/modelos/{modelo}/anos/{ano}', [FipeController::class, 'valor']);

    // Ordem de Serviços
    Route::post('/ordens-servico', [OrdemServicoController::class, 'store']);
    Route::get('/ordens-servico/{id}', [OrdemServicoController::class, 'show']);
    Route::get('/ordens-servico', [OrdemServicoController::class, 'index']);
    Route::put('/ordens-servico/{id}', [OrdemServicoController::class, 'update']);
    Route::delete('/ordens-servico/{id}', [OrdemServicoController::class, 'destroy']);

    // Manutenções
    Route::get('/manutencoes', [ManutencaoController::class, 'index']);
    Route::post('/manutencoes', [ManutencaoController::class, 'store']);
    Route::get('/manutencoes/{id}', [ManutencaoController::class, 'show']);
    Route::put('/manutencoes/{id}', [ManutencaoController::class, 'update']);
    Route::delete('/manutencoes/{id}', [ManutencaoController::class, 'destroy']);
});
