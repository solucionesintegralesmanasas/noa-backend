<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Pdf\PdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InsertaFilas;
use Tests\TestCase;

/**
 * El nombre del archivo al descargar la ficha técnica del vehículo debe ser
 * legible ("Ficha Técnica Vehículo - PLACA.pdf") y viajar en el
 * Content-Disposition de la respuesta.
 */
class VehicleTechSheetDownloadNameTest extends TestCase
{
    use RefreshDatabase, InsertaFilas;

    public function test_el_nombre_del_archivo_es_legible_y_viaja_en_la_descarga(): void
    {
        $empresa = $this->insertar('companies', ['business_name' => 'Empresa ficha', 'document_number' => '901797720', 'is_active' => true]);
        $vehiculo = $this->insertar('vehicles', [
            'company_uuid' => $empresa,
            'vehicle_license_plate' => 'ABC123',
        ]);

        $resultado = app(PdfService::class)->generateVehicleTechnicalSheetPdf($vehiculo);

        $this->assertSame('Ficha Técnica Vehículo - ABC123.pdf', $resultado['file_name']);

        $respuesta = $resultado['pdf']->stream($resultado['file_name']);
        $disposition = (string) $respuesta->headers->get('Content-Disposition');

        // RFC 5987: el nombre UTF-8 viaja codificado; el respaldo ASCII sin tildes.
        $this->assertStringContainsString("filename*=utf-8''Ficha%20T%C3%A9cnica%20Veh%C3%ADculo%20-%20ABC123.pdf", $disposition);
        $this->assertStringContainsString('filename="Ficha Tecnica Vehiculo - ABC123.pdf"', $disposition);
    }
}
