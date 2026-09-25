<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\ServiceDeliveryControlSheet;
use App\Models\ServiceDeliveryControlSheetRoute;
use App\Services\ServiceDeliveryControlSheet\ServiceDeliveryControlSheetService;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

/**
 * SPEC-002 §5.2 y §7.2 — Reglas de negocio de la planilla cerrada.
 *
 * Se invocan por reflexión porque son collaborated privados del servicio: así el
 * test fija la regla sin necesitar base de datos ni montar el contenedor HTTP.
 */
class PlanillaCerradaTest extends TestCase
{
    private function servicio(): ServiceDeliveryControlSheetService
    {
        return $this->app->make(ServiceDeliveryControlSheetService::class);
    }

    private function invocar(string $metodo, array $args): mixed
    {
        $ref = new ReflectionMethod(ServiceDeliveryControlSheetService::class, $metodo);
        $ref->setAccessible(true);

        return $ref->invoke($this->servicio(), ...$args);
    }

    private function planilla(bool $isActive, bool $conRutas = false): ServiceDeliveryControlSheet
    {
        $p = new ServiceDeliveryControlSheet;
        $p->uuid = 'p-test';
        $p->is_active = $isActive;
        $p->setRelation('routes', $conRutas
            ? collect([(function () {
                $r = new ServiceDeliveryControlSheetRoute;
                $r->uuid = 'ruta-1';
                $r->order_index = 0;

                return $r;
            })()])
            : collect());

        return $p;
    }

    #[Test]
    public function una_planilla_abierta_se_puede_editar(): void
    {
        $this->assertNull($this->invocar('asegurarEditable', [$this->planilla(true), 'update']));
    }

    #[Test]
    #[DataProvider('operacionesProhibidas')]
    public function una_planilla_cerrada_rechaza_la_edicion(string $campo): void
    {
        $this->expectException(ValidationException::class);

        $this->invocar('asegurarEditable', [$this->planilla(false), $campo]);
    }

    /** @return array<string, array{0: string}> */
    public static function operacionesProhibidas(): array
    {
        return [
            'actualizar' => ['update'],
            'eliminar' => ['delete'],
            'volver a cerrar' => ['close'],
        ];
    }

    #[Test]
    public function el_rechazo_apunta_al_campo_que_se_uso(): void
    {
        try {
            $this->invocar('asegurarEditable', [$this->planilla(false), 'delete']);
            $this->fail('Debía rechazar la operación.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('delete', $e->errors());
            $this->assertStringContainsString('cerrada', $e->errors()['delete'][0]);
        }
    }

    #[Test]
    public function declarar_disponibilidad_exige_motivo(): void
    {
        $this->expectException(ValidationException::class);

        $this->invocar('validarDisponibilidad', [
            $this->planilla(true, false), 'disponibilidad', '   ',
        ]);
    }

    #[Test]
    public function declarar_disponibilidad_con_recorridos_es_contradictorio(): void
    {
        $this->expectException(ValidationException::class);

        $this->invocar('validarDisponibilidad', [
            $this->planilla(true, true), 'disponibilidad', 'Mantenimiento',
        ]);
    }

    #[Test]
    public function declarar_disponibilidad_con_motivo_y_sin_recorridos_es_valido(): void
    {
        $this->assertNull($this->invocar('validarDisponibilidad', [
            $this->planilla(true, false), 'disponibilidad', 'Mantenimiento',
        ]));
    }

    #[Test]
    public function una_operacion_normal_no_exige_motivo(): void
    {
        $this->assertNull($this->invocar('validarDisponibilidad', [
            $this->planilla(true, true), 'operacion', null,
        ]));
    }
}
