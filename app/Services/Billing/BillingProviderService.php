<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\BillingProvider;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Catálogo de proveedores tecnológicos.
 *
 * Al guardar un proveedor ApiDian con credenciales de login, NOA inicia sesión
 * (paso 00 de la colección) y guarda el token resultante: el formulario no pide
 * token, solo correo y contraseña. Si el login falla, responde 422 con el campo
 * `client_password` en lugar de guardar un proveedor que no podrá emitir.
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
        return parent::create($this->conTokenDeLogin($attributes));
    }

    public function update(string $uuid, array $attributes): bool
    {
        $actual = $this->findByUuid($uuid);

        return parent::update($uuid, $this->conTokenDeLogin($attributes, $actual));
    }

    /**
     * Si llega una contraseña (alta o cambio), valida el login y devuelve los
     * atributos con el token fresco. Sin contraseña no se toca el token guardado.
     */
    private function conTokenDeLogin(array $attributes, ?BillingProvider $actual = null): array
    {
        if (empty($attributes['client_password'])) {
            return $attributes;
        }

        $borrador = $actual ? clone $actual : new BillingProvider;
        $borrador->fill($attributes);

        if (! $borrador->usaApiDian()) {
            return $attributes;
        }

        try {
            $attributes['client_secret'] = (new ApiDianFacturacion($borrador))->obtenerTokenDeLogin();
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'client_password' => 'No se pudo iniciar sesión con ApiDian: verifica el correo y la contraseña del portal.',
            ]);
        }

        return $attributes;
    }
}
