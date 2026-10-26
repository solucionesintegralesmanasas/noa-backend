<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Frescura del mapa en vivo
    |--------------------------------------------------------------------------
    |
    | Minutos sin recibir puntos tras los que un conductor deja de aparecer
    | en /tracking/active-drivers. Mientras la flota no esté toda en el APK
    | con heartbeat conviene un umbral amplio (10): los APK viejos no envían
    | nada cuando el vehículo está parado. Con la flota actualizada se puede
    | bajar a 3-5.
    |
    */
    'stale_minutes' => (int) env('TRACKING_STALE_MINUTES', 10),

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
