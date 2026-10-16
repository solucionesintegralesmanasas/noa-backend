<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Utils\OwnCompany;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * La comparación de nombres de empresa propia (correo de vencimientos y notificaciones)
 * debe tolerar mayúsculas, acentos, signos, espacios y la forma de escribir la sigla S.A.S.
 */
class OwnCompanyNormalizacionTest extends TestCase
{
    /** @return array<string, array{0: ?string, 1: string}> */
    public static function nombres(): array
    {
        return [
            'con puntos' => ['TRANSPORTES ESPECIALES SIN BARRERA S.A.S', 'TRANSPORTES ESPECIALES SIN BARRERA SAS'],
            'con punto final' => ['Transportes Especiales Sin Barrera S.A.S.', 'TRANSPORTES ESPECIALES SIN BARRERA SAS'],
            'sin puntos' => ['Transportes Especiales Sin Barrera SAS', 'TRANSPORTES ESPECIALES SIN BARRERA SAS'],
            'letras separadas' => ['transportes especiales sin barrera s a s', 'TRANSPORTES ESPECIALES SIN BARRERA SAS'],
            'puntos y espacios' => ['transportes especiales sin barrera s. a. s.', 'TRANSPORTES ESPECIALES SIN BARRERA SAS'],
            'acentos y espacios dobles' => ['  Transportes  Especiales Sin Bárrera S.A.S ', 'TRANSPORTES ESPECIALES SIN BARRERA SAS'],
            'otra sigla' => ['Coop. A.B.C. Ltda', 'COOP ABC LTDA'],
            'una letra suelta seguida de una palabra no se une' => ['BARRERA A SAS', 'BARRERA A SAS'],
            'una sola letra al final no se une' => ['Empresa Uno A', 'EMPRESA UNO A'],
            'nulo' => [null, ''],
            'vacío' => ['   ', ''],
        ];
    }

    #[DataProvider('nombres')]
    public function test_normaliza_el_nombre(?string $entrada, string $esperado): void
    {
        $this->assertSame($esperado, OwnCompany::normalizarNombre($entrada));
    }

    public function test_las_variantes_de_la_sigla_son_equivalentes(): void
    {
        $variantes = ['S.A.S', 'S.A.S.', 'SAS', 'S A S', 's. a. s.'];
        $normalizadas = array_unique(array_map(
            fn (string $v) => OwnCompany::normalizarNombre("TRANSPORTES ESPECIALES SIN BARRERA {$v}"),
            $variantes
        ));

        $this->assertCount(1, $normalizadas);
    }

    public function test_nombres_distintos_siguen_siendo_distintos(): void
    {
        $this->assertNotSame(
            OwnCompany::normalizarNombre('TRANSPORTES ESPECIALES SIN BARRERA S.A.S'),
            OwnCompany::normalizarNombre('TRANSPORTES ESPECIALES CON BARRERA S.A.S'),
        );
    }
}
