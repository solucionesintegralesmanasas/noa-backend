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
    public static function formatear(float $monto, string $moneda = 'COP'): string
    {
        if (is_nan($monto) || is_infinite($monto)) {
            throw new \InvalidArgumentException('El monto debe ser un número finito.');
        }

        $redondeado = (int) round($monto);
        if ($redondeado === 1) {
            return 'UN PESO M/CTE';
        }
        if ($redondeado === -1) {
            return 'MENOS UN PESO M/CTE';
        }

        return self::convertir($redondeado) . ' PESOS M/CTE';
    }

    public static function convertir(int $numero): string
    {
        if ($numero === 0) {
            return 'CERO';
        }

        if ($numero === PHP_INT_MIN) {
            return 'MENOS ' . self::convertir(PHP_INT_MAX);
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

            $textoMiles = preg_replace('/\bUNO$/', 'UN', self::convertir($miles));
            $texto = $miles === 1 ? 'MIL' : $textoMiles . ' MIL';

            return $texto . ($resto ? ' ' . self::convertir($resto) : '');
        }

        if ($numero < 1_000_000_000) {
            $millones = intdiv($numero, 1_000_000);
            $resto = $numero % 1_000_000;

            $textoMillones = preg_replace('/\bUNO$/', 'UN', self::convertir($millones));
            $texto = $millones === 1 ? 'UN MILLÓN' : $textoMillones . ' MILLONES';

            return $texto . ($resto ? ' ' . self::convertir($resto) : '');
        }

        if ($numero < 1_000_000_000_000) {
            $milesMillones = intdiv($numero, 1_000_000_000);
            $resto = $numero % 1_000_000_000;

            $textoMiles = preg_replace('/\bUNO$/', 'UN', self::convertir($milesMillones));
            if ($resto === 0) {
                return ($milesMillones === 1 ? 'MIL' : $textoMiles . ' MIL') . ' MILLONES';
            }

            $texto = $milesMillones === 1 ? 'MIL' : $textoMiles . ' MIL';

            return $texto . ' ' . self::convertir($resto);
        }

        if ($numero < 1_000_000_000_000_000_000) {
            $billones = intdiv($numero, 1_000_000_000_000);
            $resto = $numero % 1_000_000_000_000;

            $textoBillones = preg_replace('/\bUNO$/', 'UN', self::convertir($billones));
            $texto = $billones === 1 ? 'UN BILLÓN' : $textoBillones . ' BILLONES';

            return $texto . ($resto ? ' ' . self::convertir($resto) : '');
        }

        return (string) $numero;
    }
}
