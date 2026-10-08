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
            // SUPERADMIN opera en cualquiera, incluso sin empresa activa.
            if ($this->user()?->hasRole('SUPERADMIN')) {
                return;
            }
            $actual = $this->attributes->get('current_company_uuid');
            // Sin empresa activa no se puede validar pertenencia: falla cerrado.
            if ($actual === null) {
                $fail('No hay empresa activa en la petición.');

                return;
            }
            // Valor nulo significa "usar la empresa del contexto", ya validada arriba.
            if ($value === null || $value === $actual) {
                return;
            }
            $fail('La empresa no corresponde a la empresa activa.');
        };
    }
}
