<?php

use Illuminate\Support\Facades\Route;

// Raiz do domínio da API: resposta mínima e estável, sem versão, ambiente
// nem dado interno. A verificação de saúde fica no /up (bootstrap/app.php).
Route::get('/', fn () => response()->json([
    'app' => 'MyCar API',
    'status' => 'ok',
]));
