<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Driver de facturación electrónica
    |--------------------------------------------------------------------------
    |
    | El driver se resuelve POR EMPRESA desde billing_providers.driver
    | ('apidian' con credenciales de cada empresa; 'nulo' en su defecto).
    | Los valores aquí solo configuran el transporte HTTP hacia el proveedor.
    |
    */
    'timeout' => env('APIDIAN_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | ApiDian Credentials
    |--------------------------------------------------------------------------
    |
    | Credenciales globales para autenticación con el API de ApiDian.
    |
    */
    'apidian' => [
        'base_url' => env('APIDIAN_BASE_URL', 'https://apidian.systemsfact.com.co'),
        // Token de API de FACTW para regenerar el catálogo (billing:sync-apidian-catalogs).
        'token' => env('APIDIAN_TOKEN'),
    ],
];
