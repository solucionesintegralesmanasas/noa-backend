<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\DriverLicense;
use App\Models\ThirdParty;
use Tests\TestCase;

/**
 * El PDF del FUEC mostraba la licencia vencida/vieja en vez de la nueva
 * (caso real: JUAN CARLOS OVIEDO ALMANZA con VENCIDA 2026-10-02 y ACTIVA
 * 2029-06-01; la vista tomaba `driver_licenses->first()`, sin orden).
 * `licenciaActual()` fija el contrato: ACTIVA más reciente, si no la más
 * reciente, si no null. Modelos en memoria, sin BD.
 */
class LicenciaActualConductorTest extends TestCase
{
    private function conductor(array ...$licencias): ThirdParty
    {
        $tercero = new ThirdParty();
        $tercero->setRelation(
            'driverLicenses',
            collect(array_map(fn (array $l) => new DriverLicense($l), $licencias))
        );

        return $tercero;
    }

    public function test_elige_la_activa_nueva_ante_vencida_vieja(): void
    {
        $tercero = $this->conductor(
            ['number' => '92533549', 'status' => 'VENCIDA', 'expiration_date' => '2026-10-02'],
            ['number' => '92533549', 'status' => 'ACTIVA', 'expiration_date' => '2029-06-01'],
        );

        $this->assertSame('2029-06-01', $tercero->licenciaActual()->expiration_date->format('Y-m-d'));
    }

    public function test_entre_dos_activas_elige_la_mas_reciente(): void
    {
        $tercero = $this->conductor(
            ['number' => 'A', 'status' => 'ACTIVA', 'expiration_date' => '2024-05-01'],
            ['number' => 'B', 'status' => 'ACTIVA', 'expiration_date' => '2028-05-01'],
        );

        $this->assertSame('B', $tercero->licenciaActual()->number);
    }

    public function test_tolera_estado_en_minusculas_o_con_espacios(): void
    {
        $tercero = $this->conductor(
            ['number' => 'A', 'status' => 'VENCIDA', 'expiration_date' => '2023-01-01'],
            ['number' => 'B', 'status' => 'activa ', 'expiration_date' => '2028-01-01'],
        );

        $this->assertSame('B', $tercero->licenciaActual()->number);
    }

    public function test_sin_activas_muestra_la_mas_reciente(): void
    {
        $tercero = $this->conductor(
            ['number' => 'A', 'status' => 'VENCIDA', 'expiration_date' => '2022-01-01'],
            ['number' => 'B', 'status' => 'VENCIDA', 'expiration_date' => '2025-01-01'],
        );

        $this->assertSame('B', $tercero->licenciaActual()->number);
    }

    public function test_sin_licencias_devuelve_null(): void
    {
        $this->assertNull($this->conductor()->licenciaActual());
    }
}
