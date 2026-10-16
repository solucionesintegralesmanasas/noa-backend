<?php

namespace Tests\Unit;

use App\Models\ThirdParty;
use PHPUnit\Framework\TestCase;

class TerceroNombresEnMayusculasTest extends TestCase
{
    public function test_nombres_y_razon_social_se_guardan_en_mayusculas(): void
    {
        $t = new ThirdParty;
        $t->first_name = '  libardo   enrique ';
        $t->last_name = 'Peña Núñez';
        $t->company_name = 'Transportes sin barreras s.a.s';
        $t->trade_name = 'Turisval';

        $this->assertSame('LIBARDO ENRIQUE', $t->first_name);
        $this->assertSame('PEÑA NÚÑEZ', $t->last_name);
        $this->assertSame('TRANSPORTES SIN BARRERAS S.A.S', $t->company_name);
        $this->assertSame('TURISVAL', $t->trade_name);
    }

    public function test_no_altera_otros_campos_ni_nulos(): void
    {
        $t = new ThirdParty;
        $t->email = 'Correo@Ejemplo.com';
        $t->first_name = null;

        $this->assertSame('Correo@Ejemplo.com', $t->email);
        $this->assertNull($t->first_name);
    }
}
