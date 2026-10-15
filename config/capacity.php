<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Motor de cupos de capacidad transportadora
    |--------------------------------------------------------------------------
    |
    | `enforce` controla si el motor de cupos (CapacityMovementService) aplica
    | de verdad los cambios sobre `conveyor_capacity` o si solo los registra en
    | modo auditoría. En auditoría se deja la fila del movimiento con
    | `applied = false` y no se modifica el cupo, útil para desplegar en
    | empresas que aún no tienen configurada su capacidad transportadora.
    |
    | `enforce_on_new_operation_card` controla si el CRUD directo de tarjetas de
    | operación rechaza (422) una tarjeta NUEVA/CAMBIO_DE_EMPRESA cuando no existe
    | un movimiento de descuento previo. Por defecto va apagado (auditoría): se
    | registra el faltante en el log sin romper el asistente de alta actual.
    |
    */

    'enforce' => (bool) env('CAPACITY_ENFORCE', true),

    'enforce_on_new_operation_card' => (bool) env('CAPACITY_ENFORCE_NEW_CARD', false),

];
