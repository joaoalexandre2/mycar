<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return 'Laravel funcionando!';
});

Route::get('/teste', function () {
    return 'Rota funcionando!';
});
