<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClienteController;

Route::get('/', function () {
    return "Laravel funcionando!";
});

Route::get('/teste', function () {
    return "Rota funcionando!";
});

