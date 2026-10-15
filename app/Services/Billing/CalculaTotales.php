<?php

declare(strict_types=1);

namespace App\Services\Billing;

/**
 * Cálculos comerciales compartidos por facturas y notas.
 *
 * Transporte especial: el servicio es excluido de IVA (tarifa 0), por lo que
 * la línea puede convivir con ítems gravados de otras actividades.
 */
final class CalculaTotales
{
    /**
     * Calcula una línea: base = cantidad * precio - descuento.
     *
     * @param  array<string, mixed>  $item
     * @return array{base: float, impuesto: float, total: float}
     */
    public static function calcularLinea(array $item): array
    {
        $cantidad = (float) ($item['quantity'] ?? 1);
        $precio = (float) ($item['unit_price'] ?? 0);
        $descuento = (float) ($item['discount_amount'] ?? 0);
        $tarifa = (float) ($item['tax_rate'] ?? 0);

        $base = round($cantidad * $precio - $descuento, 2);
        $impuesto = round($base * $tarifa / 100, 2);

        return ['base' => $base, 'impuesto' => $impuesto, 'total' => round($base + $impuesto, 2)];
    }

    /**
     * Suma los totales de la cabecera a partir de las líneas calculadas.
     *
     * @param  array<int, array{base: float, impuesto: float, total: float, descuento: float, retencion: float}>  $lineas
     * @return array{subtotal: float, descuento: float, impuesto: float, retencion: float, total: float}
     */
    public static function sumarTotales(array $lineas): array
    {
        $subtotal = 0.0;
        $descuento = 0.0;
        $impuesto = 0.0;
        $retencion = 0.0;

        foreach ($lineas as $linea) {
            $subtotal += $linea['base'];
            $descuento += $linea['descuento'];
            $impuesto += $linea['impuesto'];
            $retencion += $linea['retencion'];
        }

        return [
            'subtotal' => round($subtotal, 2),
            'descuento' => round($descuento, 2),
            'impuesto' => round($impuesto, 2),
            'retencion' => round($retencion, 2),
            'total' => round($subtotal + $impuesto - $retencion, 2),
        ];
    }
}
