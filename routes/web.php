<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('limpiar-cache', function () {
    Artisan::call('optimize:clear');
    

    return 'Cache limpiada';
});
