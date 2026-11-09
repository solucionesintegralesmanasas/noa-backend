<?php

declare(strict_types=1);

namespace App\Support\Billing;

/**
 * Convierte un valor monetario entero a letras en español.
 *
 * No se usa NumberFormatter porque la extensión intl no está instalada.
 */
class NumeroALetras
{
    private const UNIDADES = [
        '', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE',
        'DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS', 'DIECISIETE',
        'DIECIOCHO', 'DIECINUEVE', 'VEINTE', 'VEINTIÚN', 'VEINTIDÓS', 'VEINTITRÉS',
        'VEINTICUATRO', 'VEINTICINCO', 'VEINTISÉIS', 'VEINTISIETE', 'VEINTIOCHO', 'VEINTINUEVE',
    ];

    private const DECENAS = [
        3 => 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA',
    ];

    private const CENTENAS = [
        1 => 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS',
        'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS',
    ];

    /**
     * Devuelve el monto en letras con el sufijo de pesos colombianos.
     */
    public static function formatear(float $monto): string
    {
        // shortcut: no se expresan centavos porque las facturas son en pesos enteros.
        return self::convertir((int) round($monto)) . ' PESOS M/CTE';
    }

    public static function convertir(int $numero): string
    {
        if ($numero === 0) {
            return 'CERO';
        }

        if ($numero < 0) {
            return 'MENOS ' . self::convertir(-$numero);
        }

        if ($numero < 30) {
            return self::UNIDADES[$numero];
        }

        if ($numero < 100) {
            $resto = $numero % 10;

            return self::DECENAS[intdiv($numero, 10)] . ($resto ? ' Y ' . self::UNIDADES[$resto] : '');
        }

        if ($numero < 1000) {
            if ($numero === 100) {
                return 'CIEN';
            }

            $resto = $numero % 100;

            return self::CENTENAS[intdiv($numero, 100)] . ($resto ? ' ' . self::convertir($resto) : '');
        }

        if ($numero < 1_000_000) {
            $miles = intdiv($numero, 1000);
            $resto = $numero % 1000;

            $texto = $miles === 1 ? 'MIL' : self::convertir($miles) . ' MIL';

            return $texto . ($resto ? ' ' . self::convertir($resto) : '');
        }

        if ($numero < 1_000_000_000) {
            $millones = intdiv($numero, 1_000_000);
            $resto = $numero % 1_000_000;

            $texto = $millones === 1 ? 'UN MILLÓN' : self::convertir($millones) . ' MILLONES';

            return $texto . ($resto ? ' ' . self::convertir($resto) : '');
        }

        if ($numero < 1_000_000_000_000) {
            $milesMillones = intdiv($numero, 1_000_000_000);
            $resto = $numero % 1_000_000_000;

            $texto = $milesMillones === 1 ? 'MIL MILLONES' : self::convertir($milesMillones) . ' MIL MILLONES';

            return $texto . ($resto ? ' ' . self::convertir($resto) : '');
        }

        return (string) $numero;
    }
}
