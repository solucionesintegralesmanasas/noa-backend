<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Frescura del mapa en vivo
    |--------------------------------------------------------------------------
    |
    | Minutos sin recibir puntos tras los que un conductor deja de aparecer
    | en /tracking/active-drivers. En 720 (12 h) el mapa muestra la última
    | posición conocida del conductor aunque el vehículo lleve rato parado
    | (los APK viejos no envían nada cuando no se mueven); pasado ese tiempo
    | la sesión se considera abandonada y sale del mapa.
    |
    */
    'stale_minutes' => (int) env('TRACKING_STALE_MINUTES', 720),

    /*
    |--------------------------------------------------------------------------
    | Ventana de reactivación de sesiones pausadas
    |--------------------------------------------------------------------------
    |
    | Horas hacia atrás en las que una sesión pausada se puede reactivar al
    | recibir un punto (reinicio de app, caída de red o startSession fallido).
    | Pasado ese tiempo se abre una sesión nueva.
    |
    */
    'session_paused_hours' => (int) env('TRACKING_SESSION_PAUSED_HOURS', 12),
];
