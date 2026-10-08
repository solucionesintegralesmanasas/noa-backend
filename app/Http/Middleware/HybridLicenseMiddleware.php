<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\LicenseService;

class HybridLicenseMiddleware
{
    /**
     * LEGADO: este middleware no se aplica globalmente (en Laravel 12 el Kernel
     * clásico no se carga; ver bootstrap/app.php). La verificación de licencia
     * vive en la ruta explícita `GET /api/v1/license/verify/{key}` con
     * `LicenseService`. No reactivarlo en global sin alinear ambas ramas
     * (online/offline) con el servicio: hoy la lógica está duplicada entre este
     * archivo y el closure de la ruta.
     */
    public function handle(Request $request, Closure $next)
    {
        // 1. Obtener código de licencia de múltiples fuentes
        $licenseCode = $this->getLicenseCode($request);

        if (!$licenseCode) {
            // En modo desarrollo/local: permitir con restricciones
            if (app()->environment('local')) {
                // Añadir header informativo pero dejar pasar
                $response = $next($request);
                $response->headers->set('X-License-Status', 'development-no-license');
                return $response;
            }

            // En producción sin licencia: bloquear
            return response()->json([
                'success' => false,
                'error' => 'no_license',
                'message' => 'No se detectó código de licencia. Contacte a su administrador.'
            ], 403);
        }

        // 2. Verificar licencia usando el servicio (JWT/HMAC verification)
        $licenseService = new LicenseService();
        $verification = $licenseService->verifyKey($licenseCode);

        if ($verification && $verification['status'] === 'valid') {
            // 3. Si es válida, registrar verificación online y continuar
            $licenseService->recordOnlineVerification($licenseCode, $verification['company_uuid']);

            // Agregar información de la licencia a la request para usar después
            $request->merge([
                'license_company_uuid' => $verification['company_uuid'],
                'license_status' => 'valid',
                'license_mode' => 'online'
            ]);

            $response = $next($request);
            $response->headers->set('X-License-Valid', 'true');
            $response->headers->set('X-License-Company-Uuid', $verification['company_uuid']);
            $response->headers->set('X-License-Mode', 'online');
            return $response;

        } elseif ($verification && $verification['status'] === 'expired') {
            // Licencia vencida
            return response()->json([
                'success' => false,
                'error' => 'expired',
                'message' => 'Su licencia ha vencido. Es necesario renovar.'
            ], 403);

        } else {
            // Verificar modo offline (usando datos guardados en BD o local)
            $offlineResult = $licenseService->verifyOffline($licenseCode);

            if ($offlineResult && $offlineResult['status'] === 'valid') {
                // Modo offline activado
                $request->merge([
                    'license_company_uuid' => $offlineResult['company_uuid'],
                    'license_status' => 'valid',
                    'license_mode' => 'offline'
                ]);

                $response = $next($request);
                $response->headers->set('X-License-Valid', 'true');
                $response->headers->set('X-License-Mode', 'offline');
                $response->headers->set('X-License-Offline-Uses', (string)$offlineResult['offline_uses']);
                return $response;
            }

            // Si ambos fallan (ni online ni offline)
            return response()->json([
                'success' => false,
                'error' => 'invalid_license',
                'message' => 'La licencia no es válida. Por favor verifique el código e intente nuevamente.'
            ], 403);
        }
    }

    /**
     * Obtener código de licencia de varias fuentes (por orden de prioridad)
     */
    protected function getLicenseCode(Request $request): ?string
    {
        // Prioridad 1: Header X-License-Key (recomendado para axios interceptor)
        if ($request->header('X-License-Key')) {
            return $request->header('X-License-Key');
        }

        // Prioridad 2: Parámetro de query ?license_key=...
        if ($request->query('license_key')) {
            return $request->query('license_key');
        }

        // Prioridad 3: Variable de entorno config('app.license_key')
        $envKey = config('app.license_key', '');
        if ($envKey) return $envKey;

        // Prioridad 4: Almacenamiento local (storage/licenses/...) - consultado después
        // Por ahora omitimos esta opción en el middleware puro

        return null;
    }
}