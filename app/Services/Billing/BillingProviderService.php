<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\BillingProvider;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Catálogo de proveedores tecnológicos (driver por empresa: factus o apidian).
 *
 * Factus: al guardar con credenciales NOA inicia sesión OAuth2 y guarda
 * access_token + refresh_token (el formulario pide client_id, client_secret,
 * usuario y contraseña). ApiDian: inicia sesión con email + contraseña
 * (POST /api/auth/login) y guarda el JWT en access_token con su expiración
 * (el refresh es automático ante 401); también admite el token maestro `whs_…`
 * directo en client_password. Si el login falla, responde 422 en vez de guardar
 * un proveedor que no podrá emitir.
 */
class BillingProviderService extends BaseService
{
    protected array $searchableFields = ['name', 'email'];

    protected function getModelInstance(): Model
    {
        return new BillingProvider;
    }

    public function create(array $attributes): Model
    {
        // Si no se envía el driver en el body, se fuerza a factus para mantener compatibilidad con versiones anteriores del cliente.
        if (! isset($attributes['driver'])) {
            $attributes['driver'] = BillingProvider::DRIVER_FACTUS;
        }

        return parent::create($this->conTokenDeLogin($attributes));
    }

    public function update(string $uuid, array $attributes): bool
    {
        // Si no se envía el driver en el body, se mantiene el existente.
        if (! isset($attributes['driver'])) {
            unset($attributes['driver']);
        }

        $actual = $this->findByUuid($uuid);

        return parent::update($uuid, $this->conTokenDeLogin($attributes, $actual));
    }

    /**
     * Si llega una contraseña (alta o cambio), valida el login según el driver
     * y devuelve los atributos con los tokens frescos. Sin contraseña no se toca
     * el token guardado.
     */
    private function conTokenDeLogin(array $attributes, ?BillingProvider $actual = null): array
    {
        if (empty($attributes['client_password'])) {
            return $attributes;
        }

        $borrador = $actual ? clone $actual : new BillingProvider;
        $borrador->fill($attributes);

        if ($borrador->driver === BillingProvider::DRIVER_FACTUS) {
            try {
                $tokens = (new FactusFacturacion($borrador))->obtenerTokensDeLogin();

                $attributes['access_token'] = $tokens['access_token'];
                $attributes['refresh_token'] = $tokens['refresh_token'];
                $attributes['access_token_expires_at'] = now()->addSeconds(max(60, $tokens['expires_in']));
            } catch (\RuntimeException $e) {
                throw ValidationException::withMessages([
                    'client_password' => 'No se pudo iniciar sesión con Factus: verifica el client_id, el client_secret, el correo y la contraseña.',
                ]);
            }
        } elseif ($borrador->driver === BillingProvider::DRIVER_APIDIAN) {
            // Token maestro `whs_…`: se usa directo, sin login.
            if (! str_starts_with((string) $borrador->client_password, 'whs_')) {
                try {
                    $resultado = (new ApidianFacturacion($borrador))->obtenerTokensDeLogin();

                    $attributes['access_token'] = $resultado['access_token'];
                    $attributes['access_token_expires_at'] = now()->addSeconds($resultado['expires_in']);
                } catch (\RuntimeException $e) {
                    throw ValidationException::withMessages([
                        'client_password' => 'No se pudo iniciar sesión con ApiDian: verifica el correo y la contraseña.',
                    ]);
                }
            }
        }

        return $attributes;
    }
}
