<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\License;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InsertaFilas;
use Tests\TestCase;

/**
 * La tabla `licenses` ya tiene PK `id`: revocar, renovar y expirar persisten
 * con UPDATE (antes `save()` intentaba INSERT por falta de clave).
 * El secreto HMAC vive en config/`LICENSE_HMAC_SECRET` y falla cerrado.
 */
class LicenseServiceTest extends TestCase
{
    use RefreshDatabase, InsertaFilas;

    private function licencia(array $datos = []): License
    {
        // `licenses` no tiene columna `uuid`: inserción explícita en vez de `InsertaFilas`.
        $fila = $datos + [
            'company_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'license_key' => 'LIC-'.strtoupper(\Illuminate\Support\Str::random(8)),
            'signature' => hash('sha256', \Illuminate\Support\Str::random(16)),
            'expiry_date' => '2027-06-30',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        \Illuminate\Support\Facades\DB::table('licenses')->insert($fila);

        return License::where('license_key', $fila['license_key'])->firstOrFail();
    }

    public function test_revocar_persiste_el_estado(): void
    {
        $licencia = $this->licencia();
        $this->assertNotNull($licencia->getKey(), 'licenses necesita PK para que save() actualice');

        $this->assertTrue(app(LicenseService::class)->revokeKey($licencia->license_key));
        $this->assertSame('revoked', $licencia->fresh()->status);
        $this->assertSame(1, License::where('license_key', $licencia->license_key)->count());
    }

    public function test_renovar_actualiza_el_vencimiento(): void
    {
        $licencia = $this->licencia();

        $this->assertTrue(app(LicenseService::class)->renewKey($licencia->license_key, '2028-01-15'));
        $this->assertSame('2028-01-15', $licencia->fresh()->expiry_date->format('Y-m-d'));
    }

    public function test_verificar_clave_corta_valida_y_vencida(): void
    {
        $valida = $this->licencia();
        $this->assertSame('valid', app(LicenseService::class)->verifyKey($valida->license_key)['status']);

        $vencida = $this->licencia(['expiry_date' => '2020-01-01']);
        $this->assertSame('expired', app(LicenseService::class)->verifyKey($vencida->license_key)['status']);
        $this->assertSame('expired', $vencida->fresh()->status);
    }

    public function test_generar_exige_secreto(): void
    {
        config(['app.license_hmac_secret' => null]);

        $this->expectException(\RuntimeException::class);
        app(LicenseService::class)->generateKey([
            'company_uuid' => (string) \Illuminate\Support\Str::uuid(), 'expiry_date' => '2027-06-30',
        ]);
    }

    public function test_generar_y_verificar_con_secreto(): void
    {
        config(['app.license_hmac_secret' => 'secreto-de-prueba']);

        $clave = app(LicenseService::class)->generateKey([
            'company_uuid' => (string) \Illuminate\Support\Str::uuid(), 'expiry_date' => '2027-06-30',
        ])['license_key'];

        $this->assertSame('valid', app(LicenseService::class)->verifyKey($clave)['status']);
    }
}
