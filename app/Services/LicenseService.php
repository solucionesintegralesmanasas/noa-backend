<?php

namespace App\Services;

use App\Models\License;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class LicenseService
{
    protected $secretKey = 'LS2026-HYBRID-SUPER-SECRET-KEY-Alpha-Numeric-2026';
    protected $cacheTtl = 3600; // 1 hora en caché

    /**
     * Generar un nuevo código de licencia híbrido.
     * Retorna: ['license_key' => 'CODE', 'signature' => 'SIG', 'payload' => [...]]
     */
    public function generateKey(array $companyData): array
    {
        // 1. Construir payload (datos codificados en la licencia)
        $expiry = $companyData['expiry_date'] instanceof Carbon ? $companyData['expiry_date'] : Carbon::createFromFormat('Y-m-d', $companyData['expiry_date']);
        $payload = [
            'company_uuid' => $companyData['company_uuid'],
            'expires_at' => $expiry->copy()->endOfDay()->format('Y-m-d H:i:s'),
            'permissions' => $companyData['permissions'] ?? [],
            'iat' => time(),
            'type' => 'hybrid_license_v1',
        ];

        // 2. Crear firma digital HMAC-SHA256 sobre los datos (sin la firma incluida)
        $dataWithoutSig = Arr::except($payload, []);
        $signature = hash_hmac('sha256', json_encode($dataWithoutSig), $this->secretKey);

        // 3. Combinar payload + firma y codificar a base64url
        $fullPayload = array_merge($payload, ['sig' => $signature]);
        $licenseKey = rtrim(base64_encode(json_encode($fullPayload)), '=');

        // 4. Guardar en base de datos (si el modelo existe y hay company_uuid válido)
        if (!empty($companyData['company_uuid'])) {
            try {
                License::updateOrCreate(
                    ['license_key' => $licenseKey],
                    [
                        'company_uuid' => $companyData['company_uuid'],
                        'signature' => $signature,
                        'expiry_date' => $companyData['expiry_date'] instanceof Carbon ? $companyData['expiry_date'] : Carbon::createFromFormat('Y-m-d', $companyData['expiry_date']),
                        'status' => 'active',
                        'online_verification_enabled' => true,
                    ]
                );
            } catch (\Exception $e) {
                // Si la tabla aún no existe o hay error, continuamos igual
                // El código seguirá funcionando modo offline/local
            }
        }

        return [
            'license_key' => $licenseKey,
            'signature' => $signature,
            'payload' => $payload,
            'expires_at' => $companyData['expiry_date'],
        ];
    }

    /**
     * Verificar un código de licencia (modo híbrido: online + offline).
     * Retorna array con status o null si es inválido.
     */
    public function verifyKey(string $licenseKey): ?array
    {
        $licenseKey = trim($licenseKey);

        // 0. Códigos cortos legibles (ej: NSA-LIC-2026-ABC123): se validan directo en BD.
        try {
            $short = License::where('license_key', $licenseKey)->first();
            if ($short) {
                if ($short->status !== 'active') {
                    return ['status' => $short->status, 'company_uuid' => $short->company_uuid];
                }
                if ($short->expiry_date < now()->toDateString()) {
                    $short->expire();
                    return ['status' => 'expired', 'company_uuid' => $short->company_uuid];
                }
                return [
                    'status' => 'valid',
                    'company_uuid' => $short->company_uuid,
                    'expires_at' => $short->expiry_date->format('Y-m-d H:i:s'),
                    'permissions' => [],
                ];
            }
        } catch (\Throwable $e) {
            // Si la tabla aún no existe, se sigue al modo híbrido largo.
        }

        try {
            // 1. Decodificar base64 (añadir padding si es necesario)
            $decoded = json_decode(base64_decode($licenseKey . '=='), true);
            if (json_last_error() !== JSON_ERROR_NONE || !$decoded) return null;

            // 2. Verificar firma digital
            $dataWithoutSig = Arr::except($decoded, ['sig']);
            $expectedSig = hash_hmac('sha256', json_encode($dataWithoutSig), $this->secretKey);

            if (!isset($decoded['sig']) || $decoded['sig'] !== $expectedSig) return null; // Firma inválida

            // 3. Verificar campos obligatorios
            $requiredFields = ['company_uuid', 'expires_at', 'sig'];
            foreach ($requiredFields as $field) {
                if (!array_key_exists($field, $decoded)) return null;
            }

            // 4. Verificar expiración
            $expiry = Carbon::createFromFormat('Y-m-d H:i:s', $decoded['expires_at']);
            if ($expiry->isPast()) {
                // Actualizar status en BD si existe
                $license = License::where('license_key', $licenseKey)->first();
                if ($license) $license->expire();
                return ['status' => 'expired', 'company_uuid' => $decoded['company_uuid']];
            }

            // 5. Retornar datos válidos
            return [
                'status' => 'valid',
                'company_uuid' => $decoded['company_uuid'],
                'expires_at' => $decoded['expires_at'],
                'permissions' => $decoded['permissions'] ?? [],
            ];

        } catch (Exception $e) {
            // Cualquier error = licencia inválida
            return null;
        }
    }

    /**
     * Verificación solo OFFLINE usando base de datos.
     * Útil cuando no hay conexión a internet pero sí BD local.
     */
    public function verifyOffline(string $licenseKey): ?array
    {
        $license = License::where('license_key', $licenseKey)->first();

        if (!$license) return null;

        // Verificar estatus en BD
        if ($license->status !== 'active') return ['status' => $license->status];

        // Verificar expiración en BD
        if ($license->expiry_date < now()->toDateString()) {
            $license->expire();
            return ['status' => 'expired'];
        }

        // Incrementar contador offline
        $license->incrementOffline();

        return [
            'status' => 'valid',
            'company_uuid' => $license->company_uuid,
            'expires_at' => $license->expiry_date->toDateTimeString(),
            'offline_mode' => true,
            'offline_uses' => $license->offline_verifications,
        ];
    }

    /**
     * Guardar licencia en almacenamiento local (storage/licenses/) para modo offline puro.
     */
    public function saveLocalLicenseData(string $licenseKey, array $data): void
    {
        $path = storage_path("licenses/license_{$licenseKey}.json");
        @mkdir(dirname($path), 0755, true); // Crear directorio si no existe
        file_put_contents($path, json_encode($data));
    }

    /**
     * Leer licencia guardada localmente.
     */
    public function readLocalLicenseData(string $licenseKey): ?array
    {
        $path = storage_path("licenses/license_{$licenseKey}.json");

        if (file_exists($path)) {
            return json_decode(file_get_contents($path), true);
        }

        return null;
    }

    /**
     * Registrar verificación online en BD y caché.
     */
    public function recordOnlineVerification(string $licenseKey, string $companyUuid): void
    {
        License::where('license_key', $licenseKey)
            ->increment('online_verifications');

        // También actualizar caché para lecturas rápidas
        Cache::put("license_{$licenseKey}_online", now()->toDateTimeString(), $this->cacheTtl);
    }

    /**
     * Verificar si la verificación online está habilitada para esta licencia.
     */
    public function isOnlineVerificationEnabled(string $licenseKey): bool
    {
        $license = License::where('license_key', $licenseKey)->first();
        return $license?->online_verification_enabled ?? true; // Default: enabled
    }

    /**
     * Renovar licencia (desde panel admin).
     */
    public function renewKey(string $licenseKey, string $newExpiryDate): bool
    {
        $license = License::where('license_key', $licenseKey)->first();
        if (!$license || $license->status !== 'active') return false;

        try {
            $license->update(['expiry_date' => Carbon::createFromFormat('Y-m-d', $newExpiryDate)]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Revocar/uspender licencia.
     */
    public function revokeKey(string $licenseKey): bool
    {
        $license = License::where('license_key', $licenseKey)->first();
        if (!$license) return false;

        $license->status = 'revoked';
        $license->save();
        return true;
    }
}