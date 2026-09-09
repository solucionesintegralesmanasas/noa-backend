<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserAuthenticated;
use Illuminate\Support\Facades\RateLimiter;

class HandlePostAuthentication
{
    /**
     * Maneja el evento de autenticación exitosa del usuario.
     */
    public function handle(UserAuthenticated $event): void
    {
        RateLimiter::clear(strtolower($event->email).'|'.$event->ip);

        // Registrar la actividad de inicio de sesión con Spatie
        activity()
            ->performedOn($event->user)
            ->causedBy($event->user)
            ->event('login')
            ->withProperties([
                'ip' => $event->ip,
                'user_agent' => request()->userAgent() ?? 'N/A',
            ])
            ->log('inició sesión');

        // Purga automática de tokens antiguos para mantener la BD ligera
        $event->user->tokens()->where('created_at', '<', now()->subDays(7))->delete();
    }
}
