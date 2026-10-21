<?php

/**
 * Parámetros del Control de Combustible y Kilometraje.
 *
 * Consumo estimado por ralentí:
 *   litros_motor   = cilindraje (cc) / 1000
 *   litros/hora    = litros_motor × factor (L/h por litro de cilindraje)
 *   litros_consumo = litros/hora × horas_ralenti
 *   galones        = litros_consumo / litros_por_galon
 *
 * Factores de referencia (L/h por litro de cilindraje):
 *   - Gasolina: 0,60 (Natural Resources Canada, calculadora oficial de ralentí).
 *   - Diésel:   0,45 (rango medio de las referencias de ralentí para camionetas).
 *   - Otros:    valores conservadores; ajustables aquí sin tocar código.
 */
return [
    // Litros por galón estadounidense.
    'liters_per_gallon' => 3.785411784,

    'idle_factor_l_per_hour_per_liter' => [
        'GASOLINA' => 0.60,
        'DIESEL' => 0.45,
        'GAS' => 0.60,
        'HIBRIDO' => 0.40,
        'ELECTRICIDAD' => 0.0,
        'OTRO' => 0.50,
    ],

    /*
     * Aviso de combustible por kilometraje.
     *
     * Se genera una notificación cuando los kilómetros recorridos desde el
     * último tanqueo (según el preoperacional/PCP) alcanzan el porcentaje
     * indicado del alcance estimado, o cuando restan estos kilómetros,
     * lo que ocurra primero. El aviso se reinicia con cada tanqueo.
     */
    'notification_warning_percent' => (float) env('FUEL_NOTIFICATION_WARNING_PERCENT', 85),
    'notification_reserve_km' => (float) env('FUEL_NOTIFICATION_RESERVE_KM', 50),
];
