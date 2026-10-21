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

];
