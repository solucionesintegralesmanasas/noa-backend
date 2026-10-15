<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Driver de facturación electrónica
    |--------------------------------------------------------------------------
    |
    | nulo: genera y guarda el UBL 2.1 sin transmitir (fases 1 y 2).
    | La fase del proveedor tecnológico agrega su driver aquí y cambia
    | FACTURACION_DRIVER en el .env, sin tocar el resto del módulo.
    |
    */
    'driver' => env('FACTURACION_DRIVER', 'nulo'),

];
