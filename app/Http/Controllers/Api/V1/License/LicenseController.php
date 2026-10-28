<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\License;

use App\Http\Controllers\Controller;
use App\Services\LicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de gestión y verificación de licencias.
 *
 * Desacopla los endpoints de licencia de routes/api.php y delega la verificación
 * híbrida (online + fallback offline) y transiciones en LicenseService.
 */
class LicenseController extends Controller
{
    public function __construct(
        protected LicenseService $licenseService
    ) {}

    /**
     * Verificar una clave de licencia en modo híbrido (online con fallback offline).
     */
    public function verify(Request $request, ?string $key = null): JsonResponse
    {
        $clave = trim((string) ($key ?? $request->input('license_key', '')));

        if ($clave === '') {
            return response()->json([
                'success' => false,
                'error' => 'invalid',
                'message' => 'Código de licencia inválido o no encontrado',
            ], 403);
        }

        $resultado = $this->licenseService->verify($clave);

        if ($resultado['success']) {
            return response()->json($resultado, 200);
        }

        $codigoHttp = match ($resultado['status']) {
            'expired', 'revoked' => 403,
            default => 403,
        };

        return response()->json([
            'success' => false,
            'error' => $resultado['error'] ?? 'invalid',
            'message' => $resultado['message'] ?? 'Código de licencia inválido o no encontrado',
        ], $codigoHttp);
    }

    /**
     * Revocar una licencia activa (solo SUPERADMIN).
     */
    public function revoke(Request $request): JsonResponse
    {
        $key = trim((string) $request->input('license_key', ''));

        if ($key === '') {
            return response()->json([
                'success' => false,
                'message' => 'La licencia es obligatoria.',
            ], 422);
        }

        if ($this->licenseService->revokeKey($key)) {
            return response()->json([
                'success' => true,
                'message' => 'Licencia revocada',
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Licencia no encontrada',
        ], 404);
    }

    /**
     * Renovar fecha de vigencia de una licencia (solo SUPERADMIN).
     */
    public function renew(Request $request): JsonResponse
    {
        $key = trim((string) $request->input('license_key', ''));
        $newExpiry = $request->input('new_expiry_date');

        if ($key === '' || ! is_string($newExpiry) || strtotime($newExpiry) === false) {
            return response()->json([
                'success' => false,
                'message' => 'La licencia y la nueva fecha de vencimiento son obligatorias.',
            ], 422);
        }

        if ($this->licenseService->renewKey($key, $newExpiry)) {
            return response()->json([
                'success' => true,
                'new_expiry' => $newExpiry,
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'No se puede renovar',
        ], 400);
    }

    /**
     * Consultar ficha detallada de una licencia (SUPERADMIN o ADMIN_EMPRESA).
     */
    public function detail(string $key): JsonResponse
    {
        $license = $this->licenseService->detail($key);

        if (! $license) {
            return response()->json([
                'success' => false,
                'message' => 'Licencia no encontrada',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $license,
        ], 200);
    }
}
