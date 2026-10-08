<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Pdf\PdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InsertaFilas;
use Tests\Support\PresupuestoConsultas;
use Tests\TestCase;

/**
 * SPEC-004: la carga de datos del contrato laboral hace un número fijo de
 * consultas (sin N+1 al crecer los contratos) y con tope absoluto.
 */
#[\PHPUnit\Framework\Attributes\Group('perf')]
class ContratoLaboralConsultasTest extends TestCase
{
    use RefreshDatabase, PresupuestoConsultas, InsertaFilas;

    private string $empresa;

    private string $tercero;

    private string $contrato;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = $this->insertar('companies', ['business_name' => 'Contrato laboral perf', 'is_active' => true]);
        $this->tercero = $this->insertar('third_parties', ['company_uuid' => $this->empresa]);
        $this->contrato = $this->insertar('employment_contracts', [
            'company_uuid' => $this->empresa, 'third_party_uuid' => $this->tercero,
        ]);
    }

    private function medir(): array
    {
        return app(PdfService::class)->cargarContratoLaboral($this->contrato);
    }

    public function test_la_carga_del_contrato_no_crece_con_mas_contratos(): void
    {
        [$pocos, $muchos] = $this->assertConteoConstante(
            fn () => $this->medir(),
            function (): void {
                for ($i = 0; $i < 5; $i++) {
                    $t = $this->insertar('third_parties', ['company_uuid' => $this->empresa]);
                    $this->insertar('employment_contracts', ['company_uuid' => $this->empresa, 'third_party_uuid' => $t]);
                }
            },
            'cargarContratoLaboral'
        );

        $this->assertSame($pocos, $muchos);
    }

    public function test_la_carga_del_contrato_tiene_tope_de_consultas(): void
    {
        $datos = null;
        $n = $this->assertPresupuesto(5, function () use (&$datos): void {
            $datos = $this->medir();
        }, 'cargarContratoLaboral');

        $this->assertMedicionConDatos(count($datos['doc'] ?? []), 'cargarContratoLaboral');
        $this->assertNotEmpty($datos['doc']);
    }
}
