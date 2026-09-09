<?php
Route::get('/api/test-middleware', function () {
    return [
        'check' => Auth::check(),
        'third_party' => request()->attributes->get('current_third_party_uuid')
    ];
})->middleware('auth:sanctum');
