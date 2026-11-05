<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\BillingProvider;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Catálogo de proveedores tecnológicos (driver único: Factus).
 *
 * El `driver` se fija siempre a `factus` (el cliente no lo elige). Al guardar
 * con credenciales, NOA inicia sesión OAuth2 y guarda access_token + refresh_token:
 * el formulario no pide tokens, solo client_id, client_secret, usuario y contraseña.
 * Si el login falla, responde 422 con el campo `client_password` en lugar de
 * guardar un proveedor que no podrá emitir.
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
        $attributes['driver'] = BillingProvider::DRIVER_FACTUS;

        return parent::create($this->conTokenDeLogin($attributes));
    }

    public function update(string $uuid, array $attributes): bool
    {
        $attributes['driver'] = BillingProvider::DRIVER_FACTUS;

        $actual = $this->findByUuid($uuid);

        return parent::update($uuid, $this->conTokenDeLogin($attributes, $actual));
    }

    /**
     * Si llega una contraseña (alta o cambio), valida el login OAuth2 de Factus
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

        if (! $borrador->usaFactus()) {
            return $attributes;
        }

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

        return $attributes;
    }
}
