<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class EvidenceStatusPdfTest extends TestCase
{
    public function test_faltantes_operativos_se_distinguen_de_la_certificacion_tardia(): void
    {
        $html = view('pdf.partials.evidence-status', [
            'evidencia_incompleta' => true,
            'firmas_pendientes' => [[
                'fecha' => '20/09/2026',
                'pendientes' => ['Recorrido 1: falta firma del conductor'],
            ]],
            'certificaciones_pendientes' => ['21/09/2026'],
            'dias_con_excepcion' => [],
        ])->render();

        $this->assertStringContainsString('EVIDENCIA OPERATIVA INCOMPLETA.', $html);
        $this->assertStringContainsString('Recorrido 1: falta firma del conductor', $html);
        $this->assertStringContainsString('PENDIENTE DE CERTIFICACIÓN ADMINISTRATIVA.', $html);
        $this->assertStringContainsString('21/09/2026', $html);
    }

    public function test_excepcion_aparece_como_estado_distinto_y_no_como_incompleta(): void
    {
        $html = view('pdf.partials.evidence-status', [
            'evidencia_incompleta' => false,
            'firmas_pendientes' => [],
            'certificaciones_pendientes' => [],
            'dias_con_excepcion' => [[
                'fecha' => '22/09/2026',
                'motivo' => 'Vía cerrada por derrumbe',
            ]],
        ])->render();

        $this->assertStringContainsString('CERRADA CON EXCEPCIÓN.', $html);
        $this->assertStringContainsString('Vía cerrada por derrumbe', $html);
        $this->assertStringNotContainsString('EVIDENCIA OPERATIVA INCOMPLETA.', $html);
    }

    public function test_no_muestra_bandas_cuando_la_evidencia_esta_completa(): void
    {
        $html = view('pdf.partials.evidence-status', [
            'evidencia_incompleta' => false,
            'firmas_pendientes' => [],
            'certificaciones_pendientes' => [],
            'dias_con_excepcion' => [],
        ])->render();

        $this->assertStringNotContainsString('EVIDENCIA OPERATIVA INCOMPLETA.', $html);
        $this->assertStringNotContainsString('PENDIENTE DE CERTIFICACIÓN ADMINISTRATIVA.', $html);
        $this->assertStringNotContainsString('CERRADA CON EXCEPCIÓN.', $html);
    }
}
