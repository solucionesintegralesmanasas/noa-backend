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
        // Este listener nunca debe romper el login: cualquier fallo de
        // logging o purga se reporta y se ignora.
        try {
            RateLimiter::clear(strtolower($event->email).'|'.$event->ip);
        } catch (\Throwable $e) {
            report($e);
        }

        try {
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
        } catch (\Throwable $e) {
            report($e);
        }

        try {
            // Purga automática de tokens antiguos para mantener la BD ligera
            $event->user->tokens()->where('created_at', '<', now()->subDays(7))->delete();
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
