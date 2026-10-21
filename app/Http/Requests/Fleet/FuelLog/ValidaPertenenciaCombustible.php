<?php

declare(strict_types=1);

namespace App\Http\Requests\Fleet\FuelLog;

use App\Models\ThirdParty;
use App\Models\Vehicle;
use Closure;

/**
 * Reglas compartidas por los requests de combustible para que el vehículo y el
 * conductor pertenezcan a la empresa (y a la sede) activa de la petición.
 *
 * Se usa el modelo con sus global scopes en vez de `exists`: así el aislamiento
 * multiempresa de `BelongsToCompany` y el de sede del rol SUCURSAL se aplican
 * igual que en los listados.
 */
trait ValidaPertenenciaCombustible
{
    protected function reglaVehiculoDelContexto(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! $this->hayContextoDeEmpresa()) {
                $fail('No hay empresa activa en la petición.');

                return;
            }

            if (! Vehicle::query()->where('uuid', $value)->exists()) {
                $fail('El vehículo no pertenece a la empresa o sede activa.');
            }
        };
    }

    protected function reglaConductorDelContexto(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            if (! $this->hayContextoDeEmpresa()) {
                $fail('No hay empresa activa en la petición.');

                return;
            }

            if (! ThirdParty::query()->where('uuid', $value)->exists()) {
                $fail('El conductor no pertenece a la empresa o sede activa.');
            }
        };
    }

    /**
     * Sin empresa activa solo un SUPERADMIN puede operar (en cualquier empresa);
     * para el resto se falla cerrado.
     */
    private function hayContextoDeEmpresa(): bool
    {
        return (bool) $this->attributes->get('current_company_uuid')
            || (bool) $this->user()?->hasRole('SUPERADMIN');
    }
}
