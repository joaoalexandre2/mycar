<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\VeiculoController;
use App\Http\Controllers\OrdemServicoController;

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

// Ordem de Serviços

Route::post('/ordens-servico', [OrdemServicoController::class, 'store']);

Route::get('/ordens-servico/{id}', [OrdemServicoController::class, 'show']);

Route::get('/ordens-servico', [OrdemServicoController::class, 'index']);

Route::put('/ordens-servico/{id}', [OrdemServicoController::class, 'update']);

Route::delete('/ordens-servico/{id}', [OrdemServicoController::class, 'destroy']);

