<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Rutas temporales para previsualizar prototipos de estilo PDF (rama feat/pdf-style-prototypes).
Route::prefix('dev/pdf-prototypes')->group(function () {
    Route::get('/', [\App\Http\Controllers\Dev\PdfPrototypeController::class, 'index']);
    Route::get('/{variant}', [\App\Http\Controllers\Dev\PdfPrototypeController::class, 'show']);
});
