<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\City;
use App\Models\Department;
use App\Models\DriverLicense;
use App\Models\EmploymentContract;
use App\Models\SocialSecurityContribution;
use App\Models\ThirdParty;
use Barryvdh\DomPDF\Facade\Pdf;
use Tests\TestCase;

/**
 * La ficha del conductor conserva el diseño institucional original (SGC/PESV)
 * y se alimenta del modelo real: antes era un HTML suelto con datos de ejemplo
 * quemados (cédula, empresa y licencia ficticias).
 */
class FichaTecnicaConductorPdfLayoutSmokeTest extends TestCase
{
    public function test_la_ficha_del_conductor_conserva_el_diseno_original_y_datos_reales(): void
    {
        $html = view('pdf.third-parties.technical-sheet', $this->datosFicha())->render();

        $this->assertStringContainsString('size: letter portrait', $html);
        $this->assertStringContainsString('FICHA TÉCNICA DEL CONDUCTOR', $html);
        $this->assertStringContainsString('FT-TAL-003', $html);
        $this->assertStringContainsString('class="sheet-preview"', $html);
        $this->assertStringContainsString('class="header-box"', $html);
        $this->assertStringContainsString('class="section-banner"', $html);

        // Datos del conductor de prueba.
        $this->assertStringContainsString('LUIS RAFAEL OVIEDO ROMERO', $html);
        $this->assertStringContainsString('1020304050', $html);
        $this->assertStringContainsString('NUEVA EPS S.A.', $html);

        // Modalidad fija del servicio y sin columna de organismo de tránsito.
        $this->assertStringContainsString('SERVICIO ESPECIAL · NACIONAL', $html);
        $this->assertStringNotContainsString('Organismo de Tránsito', $html);

        // Ya no arrastra los datos ficticios del diseño anterior.
        $this->assertStringNotContainsString('1.067.892.341', $html);
        $this->assertStringNotContainsString('CARLOS EDUARDO MARTÍNEZ', $html);
    }

    public function test_la_direccion_sin_calle_no_empieza_con_coma(): void
    {
        $datos = $this->datosFicha();
        $datos['driver']->address = null;

        $html = view('pdf.third-parties.technical-sheet', $datos)->render();

        $this->assertStringContainsString('MONTERÍA, CÓRDOBA', $html);
        $this->assertStringNotContainsString(', MONTERÍA, CÓRDOBA', $html);
    }

    public function test_la_ficha_del_conductor_renderiza_sin_errores(): void
    {
        $pdf = Pdf::loadView('pdf.third-parties.technical-sheet', $this->datosFicha())
            ->setPaper('letter', 'portrait')
            // Mismo DPI que PdfService::generateFromView (producción): los
            // tamaños en px de la vista se convierten a pt con esta escala.
            ->setOption(['dpi' => 120])
            ->setWarnings(true);

        $salida = $pdf->output();

        $this->assertNotSame('', $salida);
        preg_match_all('~/Type\s*/Page([^s]|$)~', $salida, $paginas);
        $this->assertSame(1, count($paginas[0]), 'La ficha debe caber en una hoja vertical');
    }

    /** @return array<string, mixed> */
    private function datosFicha(): array
    {
        $driver = new ThirdParty([
            'document_number' => '1020304050',
            'first_name' => 'LUIS RAFAEL',
            'last_name' => 'OVIEDO ROMERO',
            'phone' => '3001234567',
            'email' => 'conductor@example.test',
            'address' => 'CALLE 1 # 2-3',
            'is_driver' => true,
            'is_employee' => true,
            'is_active' => true,
        ]);

        $driver->setRelation('documentType', (object) ['prefix' => 'CC']);
        $driver->setRelation('branch', (object) ['name' => 'SEDE PRINCIPAL']);
        $driver->setRelation('company', (object) [
            'business_name' => 'TRANSPORTES DE PRUEBA S.A.S.',
            'document_number' => '901555001',
            'verification_digit' => '4',
        ]);

        $departamento = new Department(['name' => 'CÓRDOBA']);
        $municipio = new City(['name' => 'MONTERÍA']);
        $municipio->setRelation('department', $departamento);
        $driver->setRelation('municipality', $municipio);

        $driver->setRelation('driverLicenses', collect([
            new DriverLicense([
                'number' => '13001004928172',
                'category' => 'C2 / C3',
                'issue_date' => '2021-03-12',
                'expiration_date' => now()->addYear()->toDateString(),
                'status' => 'ACTIVA',
            ]),
        ]));

        $driver->setRelation('employmentContracts', collect([
            new EmploymentContract([
                'contract_type' => 'TÉRMINO INDEFINIDO',
                'position' => 'CONDUCTOR SERVICIO ESPECIAL',
                'start_date' => '2023-02-01',
                'status' => 'ACTIVO',
            ]),
        ]));

        $driver->setRelation('socialSecurityContributions', collect([
            new SocialSecurityContribution([
                'billing_period' => now()->startOfMonth()->toDateString(),
                'eps_name' => 'NUEVA EPS S.A.',
                'risk_labor_name' => 'SEGUROS BOLÍVAR S.A.',
                'pension_name' => 'COLPENSIONES',
                'compensation_fund_name' => 'COMFACOR',
                'health_paid' => true,
                'risk_labor_paid' => true,
                'pension_paid' => true,
                'compensation_fund_paid' => true,
            ]),
        ]));

        return [
            'driver' => $driver,
            'data' => ['logo' => null, 'photo' => null, 'qrcode' => null],
            'letterhead' => null,
            'logo_fondo' => null,
            'ocultar_marca' => true,
        ];
    }
}
