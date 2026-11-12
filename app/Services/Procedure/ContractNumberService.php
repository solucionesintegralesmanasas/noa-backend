<?php

declare(strict_types=1);

namespace App\Services\Procedure;

use Illuminate\Support\Facades\DB;

/**
 * Consecutivo de contratos compartido por los contratos de administración de
 * flota y de prestación de servicios de una misma empresa.
 */
class ContractNumberService
{
    public const LONGITUD = 4;

    /**
     * Número que se asignaría en la próxima creación. No lo consume: dos
     * usuarios pueden ver el mismo número antes de guardar; el definitivo se
     * reserva al crear el contrato.
     */
    public function siguiente(string $companyUuid): string
    {
        $actual = DB::table('contract_sequences')->where('company_uuid', $companyUuid)->value('last_number');

        $base = max($actual === null ? 0 : (int) $actual, $this->maximoExistente($companyUuid));

        return $this->formatear($base + 1);
    }

    /** Reserva atómicamente el siguiente número de la empresa. */
    public function reservar(string $companyUuid): string
    {
        return DB::transaction(function () use ($companyUuid) {
            $actual = DB::table('contract_sequences')
                ->where('company_uuid', $companyUuid)
                ->lockForUpdate()
                ->value('last_number');

            if ($actual === null) {
                // Si la empresa aún no tiene fila, se crea sembrada con el mayor
                // número ya usado por sus contratos para no repetir datos históricos.
                $base = $this->maximoExistente($companyUuid);

                DB::table('contract_sequences')->insertOrIgnore([
                    'company_uuid' => $companyUuid,
                    'last_number' => $base,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $actual = (int) DB::table('contract_sequences')
                    ->where('company_uuid', $companyUuid)
                    ->lockForUpdate()
                    ->value('last_number');
            } else {
                $actual = max((int) $actual, $this->maximoExistente($companyUuid));
            }

            $siguiente = $actual + 1;

            DB::table('contract_sequences')
                ->where('company_uuid', $companyUuid)
                ->update(['last_number' => $siguiente, 'updated_at' => now()]);

            return $this->formatear($siguiente);
        });
    }

    private function formatear(int|string|float $numero): string
    {
        $cadena = is_float($numero) ? sprintf('%.0f', $numero) : (string) $numero;

        return str_pad($cadena, self::LONGITUD, '0', STR_PAD_LEFT);
    }

    /** Mayor número puramente numérico ya usado por la empresa en ambos contratos. */
    private function maximoExistente(string $companyUuid): int
    {
        $maximo = 0;
        foreach (['fleet_service_contracts', 'service_provision_contracts'] as $tabla) {
            $valor = DB::table($tabla)
                ->where('company_uuid', $companyUuid)
                ->whereRaw("contract_number REGEXP '^[0-9]+$'")
                ->selectRaw('MAX(CAST(contract_number AS UNSIGNED)) AS numero')
                ->value('numero');

            $maximo = max($maximo, (int) $valor);
        }

        return $maximo;
    }
}
