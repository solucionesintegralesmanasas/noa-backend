<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\ContractExtraction\FuecService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Regresión: al registrar una póliza RCC/RCE nueva, la anterior queda INACTIVA
 * (los reemplazos no se borran) y no debe invalidar el FUEC. Solo cuenta la
 * póliza vigente de cada tipo.
 *
 * Se prueba la validación del servicio de forma aislada para no sembrar todo el
 * flujo de creación del FUEC (inspección, aportes, convenio, cobros, SOAT, RTM…).
 */
class FuecPolizasVigentesTest extends TestCase
{
    use RefreshDatabase;

    private const FECHA_EMISION = '2026-10-15';

    /** Inserta una fila rellenando las columnas obligatorias sin valor por defecto. */
    private function insertar(string $tabla, array $datos): string
    {
        $uuid = $datos['uuid'] ?? (string) Str::uuid();
        $datos['uuid'] = $uuid;

        $columnas = DB::select(
            'select column_name n, data_type t, column_type ct from information_schema.columns
             where table_schema = database() and table_name = ? and is_nullable = "NO"
             and column_default is null and extra not like "%auto_increment%"',
            [$tabla]
        );
        foreach ($columnas as $c) {
            if (array_key_exists($c->n, $datos)) {
                continue;
            }
            $datos[$c->n] = match (true) {
                in_array($c->t, ['char', 'varchar'], true) => $c->n === 'email' ? Str::random(6).'@x.test' : (str_ends_with($c->n, 'uuid') ? (string) Str::uuid() : 'x'),
                in_array($c->t, ['text', 'longtext', 'mediumtext'], true) => 'x',
                $c->t === 'enum' => explode("','", trim(substr($c->ct, 5, -1), "'"))[0],
                $c->t === 'date' => '2026-09-30',
                in_array($c->t, ['datetime', 'timestamp'], true) => '2026-09-30 10:00:00',
                $c->t === 'json' => '[]',
                default => 0,
            };
        }

        Schema::disableForeignKeyConstraints();
        DB::table($tabla)->insert($datos + ['created_at' => now(), 'updated_at' => now()]);
        Schema::enableForeignKeyConstraints();

        return $uuid;
    }

    private function vehiculo(): string
    {
        return $this->insertar('vehicles', [
            'vehicle_license_plate' => 'PPQ489',
            'type_of_service' => 'PUBLICO',
            'is_active' => true,
        ]);
    }

    private function poliza(string $vehiculo, string $tipo, string $vence, string $estado = 'VIGENTE'): string
    {
        return $this->insertar('vehicle_documents', [
            'vehicle_uuid' => $vehiculo,
            'document_type' => $tipo,
            'policy_number' => 'POL-'.Str::random(5),
            'issue_date' => '2026-01-01',
            'expiry_date' => $vence,
            'issuing_entity' => 'Aseguradora',
            'status' => $estado,
        ]);
    }

    /** Ejecuta la validación privada del servicio sin sembrar todo el flujo del FUEC. */
    private function validar(string $vehiculo, string $fecha = self::FECHA_EMISION): void
    {
        $servicio = app(FuecService::class);
        $metodo = new ReflectionMethod($servicio, 'validarPolizasRccRce');
        $metodo->setAccessible(true);
        $metodo->invoke($servicio, $vehiculo, Carbon::parse($fecha));
    }

    // ------------------------------------------------------------------ regresión del caso reportado

    public function test_una_poliza_reemplazada_y_vencida_no_invalida_el_fuec(): void
    {
        $vehiculo = $this->vehiculo();
        $this->poliza($vehiculo, 'RCE', '2026-09-21', 'INACTIVA'); // reemplazada, vencida
        $this->poliza($vehiculo, 'RCE', '2027-09-21', 'VIGENTE');  // nueva
        $this->poliza($vehiculo, 'RCC', '2026-09-21', 'INACTIVA'); // reemplazada, vencida
        $this->poliza($vehiculo, 'RCC', '2027-09-21', 'VIGENTE');  // nueva

        $this->validar($vehiculo); // no debe lanzar

        $this->assertTrue(true);
    }

    public function test_se_toma_la_poliza_vigente_mas_reciente_por_tipo(): void
    {
        $vehiculo = $this->vehiculo();
        // Dos filas aún marcadas VIGENTE (dato sucio): manda la de mayor vencimiento.
        $this->poliza($vehiculo, 'RCE', '2026-01-01', 'VIGENTE');
        $this->poliza($vehiculo, 'RCE', '2027-06-30', 'VIGENTE');
        $this->poliza($vehiculo, 'RCC', '2027-06-30', 'VIGENTE');

        $this->validar($vehiculo); // no debe lanzar

        $this->assertTrue(true);
    }

    public function test_una_poliza_que_vence_justo_el_dia_de_emision_es_valida(): void
    {
        $vehiculo = $this->vehiculo();
        $this->poliza($vehiculo, 'RCE', self::FECHA_EMISION, 'VIGENTE');
        $this->poliza($vehiculo, 'RCC', self::FECHA_EMISION, 'VIGENTE');

        $this->validar($vehiculo);

        $this->assertTrue(true);
    }

    // ------------------------------------------------------------------ casos negativos

    public function test_una_poliza_individual_vencida_bloquea_el_fuec(): void
    {
        $vehiculo = $this->vehiculo();
        $this->poliza($vehiculo, 'RCE', '2026-09-21', 'VIGENTE');
        $this->poliza($vehiculo, 'RCC', '2027-06-30', 'VIGENTE');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("La póliza individual 'RCE' está vencida (Fecha de vencimiento: 21/09/2026).");

        $this->validar($vehiculo);
    }

    public function test_una_poliza_no_vigente_por_estado_no_cuenta_aunque_la_fecha_sea_futura(): void
    {
        $vehiculo = $this->vehiculo();
        $this->poliza($vehiculo, 'RCE', '2027-06-30', 'NO VIGENTE');
        $this->poliza($vehiculo, 'RCC', '2027-06-30', 'VIGENTE');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('El vehículo no cuenta con una póliza de Responsabilidad Civil Extracontractual (RCE) registrada.');

        $this->validar($vehiculo);
    }

    public function test_un_estado_legado_no_cuenta_como_vigente(): void
    {
        $vehiculo = $this->vehiculo();
        $this->poliza($vehiculo, 'RCE', '2027-06-30', 'NO');
        $this->poliza($vehiculo, 'RCC', '2027-06-30', 'VIGENTE');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('El vehículo no cuenta con una póliza de Responsabilidad Civil Extracontractual (RCE) registrada.');

        $this->validar($vehiculo);
    }

    public function test_sin_rcc_no_se_puede_crear_el_fuec(): void
    {
        $vehiculo = $this->vehiculo();
        $this->poliza($vehiculo, 'RCE', '2027-06-30', 'VIGENTE');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('El vehículo no cuenta con una póliza de Responsabilidad Civil Contractual (RCC) registrada.');

        $this->validar($vehiculo);
    }

    public function test_sin_rce_no_se_puede_crear_el_fuec(): void
    {
        $vehiculo = $this->vehiculo();
        $this->poliza($vehiculo, 'RCC', '2027-06-30', 'VIGENTE');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('El vehículo no cuenta con una póliza de Responsabilidad Civil Extracontractual (RCE) registrada.');

        $this->validar($vehiculo);
    }

    public function test_un_vehiculo_sin_polizas_bloquea_el_fuec(): void
    {
        $vehiculo = $this->vehiculo();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('RCC');

        $this->validar($vehiculo);
    }

    public function test_una_poliza_vencida_solo_bloquea_cuando_es_la_vigente_del_tipo(): void
    {
        $vehiculo = $this->vehiculo();
        $this->poliza($vehiculo, 'RCE', '2026-01-01', 'INACTIVA'); // vieja, vencida: se ignora
        $this->poliza($vehiculo, 'RCE', '2027-06-30', 'VIGENTE');
        $this->poliza($vehiculo, 'RCC', '2027-06-30', 'VIGENTE');

        $this->validar($vehiculo);

        $this->assertTrue(true);
    }
}
