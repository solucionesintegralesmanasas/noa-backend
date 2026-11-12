<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Billing\NumeroALetras;
use PHPUnit\Framework\TestCase;

class NumeroALetrasTest extends TestCase
{
    public function test_convierte_montos_enteros_a_letras(): void
    {
        $this->assertSame('CERO', NumeroALetras::convertir(0));
        $this->assertSame('UNO', NumeroALetras::convertir(1));
        $this->assertSame('VEINTIÚN', NumeroALetras::convertir(21));
        $this->assertSame('CIEN', NumeroALetras::convertir(100));
        $this->assertSame('CIENTO UNO', NumeroALetras::convertir(101));
        $this->assertSame('MIL', NumeroALetras::convertir(1000));
        $this->assertSame('UN MILLÓN', NumeroALetras::convertir(1_000_000));
        $this->assertSame(
            'UN MILLÓN CUATROCIENTOS VEINTIOCHO MIL',
            NumeroALetras::convertir(1_428_000)
        );
        $this->assertSame(
            'DOS MILLONES NOVECIENTOS VEINTICINCO MIL QUINIENTOS PESOS M/CTE',
            NumeroALetras::formatear(2_925_500)
        );
    }
}
