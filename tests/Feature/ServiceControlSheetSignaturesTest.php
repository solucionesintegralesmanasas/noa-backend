<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Signature;
use App\Services\Pdf\ServiceControlSheetSignatures;
use Tests\TestCase;

/**
 * La planilla elige cada firma por rol y vigencia, no por la posición en que
 * se guardó: una firma reemplazada no debe imprimirse y el conductor no debe
 * ocupar la casilla del funcionario aunque haya firmado primero.
 */
class ServiceControlSheetSignaturesTest extends TestCase
{
    private function firma(int $id, ?string $rol, string $estado = Signature::STATUS_VIGENTE): Signature
    {
        $firma = new Signature;
        $firma->id = $id;
        $firma->signer_role = $rol;
        $firma->status = $estado;

        return $firma;
    }

    public function test_elige_por_rol_aunque_el_conductor_haya_firmado_primero(): void
    {
        $grupo = [
            $this->firma(1, Signature::ROL_CONDUCTOR),
            $this->firma(2, Signature::ROL_FUNCIONARIO),
        ];

        $this->assertSame(2, ServiceControlSheetSignatures::porRol($grupo, Signature::ROL_FUNCIONARIO, 0)?->id);
        $this->assertSame(1, ServiceControlSheetSignatures::porRol($grupo, Signature::ROL_CONDUCTOR, 1)?->id);
    }

    public function test_ignora_la_firma_reemplazada_aunque_tenga_id_mayor(): void
    {
        $grupo = [
            $this->firma(1, Signature::ROL_FUNCIONARIO, Signature::STATUS_VIGENTE),
            $this->firma(2, Signature::ROL_FUNCIONARIO, Signature::STATUS_REEMPLAZADA),
        ];

        $this->assertSame(1, ServiceControlSheetSignatures::porRol($grupo, Signature::ROL_FUNCIONARIO)?->id);
    }

    public function test_sin_roles_conserva_el_respaldo_posicional(): void
    {
        $grupo = [
            $this->firma(10, null),
            $this->firma(11, null),
        ];

        $this->assertSame(10, ServiceControlSheetSignatures::porRol($grupo, Signature::ROL_FUNCIONARIO, 0)?->id);
        $this->assertSame(11, ServiceControlSheetSignatures::porRol($grupo, Signature::ROL_CONDUCTOR, 1)?->id);
    }

    public function test_con_roles_pero_sin_el_rol_pedido_no_inventa_una_firma(): void
    {
        $grupo = [
            $this->firma(1, Signature::ROL_CONDUCTOR),
        ];

        $this->assertNull(ServiceControlSheetSignatures::porRol($grupo, Signature::ROL_FUNCIONARIO, 0));
    }

    public function test_grupo_vacio_devuelve_null(): void
    {
        $this->assertNull(ServiceControlSheetSignatures::porRol([], Signature::ROL_FUNCIONARIO, 0));
    }
}
