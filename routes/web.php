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

Route::get('migrar', function () {
    try {
        Artisan::call('migrate', [
            '--force' => true,
        ]);

        return '<pre>' . Artisan::output() . '</pre>';
    } catch (\Throwable $e) {
        return response('<pre>Error al ejecutar migraciones: ' . $e->getMessage() . '</pre>', 500);
    }
});
