<?php

declare(strict_types=1);

namespace App\Http\Requests\ServiceDeliveryControlSheet;

use App\Models\Fuec;
use App\Models\Project;
use App\Models\ThirdParty;
use App\Models\Vehicle;
use Closure;

/**
 * Reglas compartidas por las planillas de control (PCP) para que el proyecto,
 * el vehículo, el tercero y el FUEC pertenezcan a la empresa (y a la sede)
 * activa de la petición.
 *
 * Se consulta el modelo con sus global scopes en vez de `exists`: así el
 * aislamiento multiempresa de `BelongsToCompany` y el de sede del rol SUCURSAL
 * se aplican igual que en los listados.
 */
trait ValidaPertenenciaPcp
{
    protected function reglaProyectoDelContexto(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || $value === '' || ! $this->exigirContexto($fail)) {
                return;
            }

            if (! Project::query()->where('uuid', $value)->exists()) {
                $fail('El proyecto no pertenece a la empresa activa.');
            }
        };
    }

    protected function reglaVehiculoDelContexto(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || $value === '' || ! $this->exigirContexto($fail)) {
                return;
            }

            if (! Vehicle::query()->where('uuid', $value)->exists()) {
                $fail('El vehículo no pertenece a la empresa o sede activa.');
            }
        };
    }

    protected function reglaTerceroDelContexto(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || $value === '' || ! $this->exigirContexto($fail)) {
                return;
            }

            if (! ThirdParty::query()->where('uuid', $value)->exists()) {
                $fail('El tercero no pertenece a la empresa o sede activa.');
            }
        };
    }

    protected function reglaFuecDelContexto(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || $value === '' || ! $this->exigirContexto($fail)) {
                return;
            }

            // El modelo Fuec no usa el scope multiempresa: la empresa se filtra
            // de forma explícita cuando la petición tiene una activa.
            $query = Fuec::query()->where('uuid', $value);
            $empresa = $this->attributes->get('current_company_uuid');
            if ($empresa) {
                $query->where('company_uuid', $empresa);
            }

            if (! $query->exists()) {
                $fail('El FUEC no pertenece a la empresa activa.');
            }
        };
    }

    /**
     * Sin empresa activa solo un SUPERADMIN puede operar (en cualquier empresa);
     * para el resto se falla cerrado.
     */
    private function exigirContexto(Closure $fail): bool
    {
        if ($this->attributes->get('current_company_uuid')) {
            return true;
        }

        if ($this->user()?->hasRole('SUPERADMIN')) {
            return true;
        }

        $fail('No hay empresa activa en la petición.');

        return false;
    }
}
