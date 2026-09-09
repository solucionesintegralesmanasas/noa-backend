<?php

Route::get('/test-middleware', function () {
    return [
        'check' => Auth::check(),
        'user' => Auth::user(),
        'company_uuid' => request()->attributes->get('company_uuid'),
        'user_id' => request()->attributes->get('user_id'),
    ];
})->middleware('setCompanyContext');

