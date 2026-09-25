<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\ServiceDeliveryControlSheet;
use App\Models\ServiceDeliveryControlSheetRoute;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SPEC-002 §5.2 — Modalidad del día y motivo de disponibilidad.
 *
 * Son reglas de dominio puras: no tocan la base de datos, así que el test corre
 * en cualquier entorno (SQLite, MySQL o el de las pruebas de integración).
 */
class DisponibilidadDiaTest extends TestCase
{
    private function planilla(string $dayKind, bool $conRutas, ?string $motivo = null): ServiceDeliveryControlSheet
    {
        $p = new ServiceDeliveryControlSheet;
        $p->uuid = 'p-test';
        $p->day_kind = $dayKind;
        $p->availability_reason = $motivo;

        $p->setRelation('routes', $conRutas
            ? collect([$this->ruta('ruta-1')])
            : collect());

        return $p;
    }

    private function ruta(string $uuid): ServiceDeliveryControlSheetRoute
    {
        $r = new ServiceDeliveryControlSheetRoute;
        $r->uuid = $uuid;
        $r->order_index = 0;

        return $r;
    }

    #[Test]
    public function una_planilla_declarada_en_disponibilidad_es_disponibilidad(): void
    {
        $this->assertTrue($this->planilla('disponibilidad', false, 'Vehículo en mantenimiento')->esDisponibilidad());
    }

    #[Test]
    public function una_operacion_con_recorridos_no_es_disponibilidad(): void
    {
        $this->assertFalse($this->planilla('operacion', true)->esDisponibilidad());
    }

    #[Test]
    #[DataProvider('casosSinMotivoUtil')]
    public function sin_motivo_registrado_no_se_inventa_uno(?string $motivo): void
    {
        $p = $this->planilla('disponibilidad', false, $motivo);

        $this->assertNull($p->motivoDisponibilidad());
        // Nunca inventa: cae al texto genérico en vez de inventar un motivo.
        $this->assertSame('VEHÍCULO EN DISPONIBILIDAD', $p->textoDisponibilidad());
    }

    /** @return array<string, array{0: ?string}> */
    public static function casosSinMotivoUtil(): array
    {
        return [
            'null' => [null],
            'cadena vacía' => [''],
            'solo espacios' => ['   '],
        ];
    }

    #[Test]
    public function el_motivo_registrado_aparece_en_el_texto(): void
    {
        $p = $this->planilla('disponibilidad', false, '  Mantenimiento preventivo  ');

        $this->assertSame('Mantenimiento preventivo', $p->motivoDisponibilidad());
        $this->assertSame(
            'VEHÍCULO EN DISPONIBILIDAD — Mantenimiento preventivo',
            $p->textoDisponibilidad()
        );
    }

    /**
     * El histórico anterior a la migración no tiene day_kind. No debe romperse,
     * pero tampoco puede exigirse un motivo que nunca se capturó.
     */
    #[Test]
    public function el_historico_sin_declaracion_conserva_la_inferencia_anterior(): void
    {
        $sinRutas = $this->planilla('operacion', false);
        $this->assertTrue($sinRutas->esDisponibilidad());
        $this->assertNull($sinRutas->motivoDisponibilidad());

        $this->assertFalse($this->planilla('operacion', true)->esDisponibilidad());
    }

    #[Test]
    public function la_declaracion_manda_sobre_el_contenido_de_rutas(): void
    {
        // Estado inconsistente que la validación de negocio ya impide persistir,
        // pero el modelo debe seguir priorizando la declaración explícita.
        $p = $this->planilla('disponibilidad', true, 'Motivo');

        $this->assertTrue($p->esDisponibilidad());
    }
}
