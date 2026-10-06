<?php

declare(strict_types=1);

namespace App\Http\Requests\Procedure\Radicacion;

use Closure;

/**
 * El `company_uuid` que envía el cliente debe ser la empresa del contexto de la petición
 * (`SetCompanyContext`); el SUPERADMIN opera en cualquiera.
 */
trait ValidaEmpresaDelContexto
{
    protected function reglaEmpresaDelContexto(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $actual = $this->attributes->get('current_company_uuid');
            if ($value === null || $actual === null || $value === $actual) {
                return;
            }
            if ($this->user()?->hasRole('SUPERADMIN')) {
                return;
            }
            $fail('La empresa no corresponde a la empresa activa.');
        };
    }
}
