<?php

declare(strict_types=1);

namespace App\Services\Pdf;

/**
 * Opciones tipadas para ServiceControlSheetRows::armarDias().
 *
 * Sustituye el array asociativo de 6 claves: cada resolución queda nombrada,
 * tipada y con valor por defecto explícito en vez de viajar suelta.
 */
final class ServiceControlSheetRowsOptions
{
    /**
     * @param \Closure $proyecto callable($hoja): ?string — nombre del proyecto de la fila
     * @param \Closure $rutasDe callable($hoja): iterable — recorridos de la hoja, ya ordenados
     * @param \Closure $firmaHoja callable($hoja): ?string — base64 del funcionario, respaldo
     * @param \Closure $firmasRuta callable($hoja, $route): array{funcionario: ?string, conductor: ?string}
     * @param bool $numeroEntero diario usa (int); mensual/filtrado conservan "05"
     * @param bool $conRutasDetalle diario devuelve además el detalle por ruta para el pie
     * @param \Closure|null $firmaCoordinador callable($hoja): ?string — base64 del coordinador,
     *                                                     respaldo de la firma de la fila
     * @param \Closure|null $firmaConductor callable($hoja): ?string — base64 del conductor de la
     *                                                   hoja, respaldo de la columna CONDUCTOR ASIGNADO
     */
    public function __construct(
        public readonly \Closure $proyecto,
        public readonly \Closure $rutasDe,
        public readonly \Closure $firmaHoja,
        public readonly \Closure $firmasRuta,
        public readonly bool $numeroEntero = false,
        public readonly bool $conRutasDetalle = false,
        public readonly ?\Closure $firmaCoordinador = null,
        public readonly ?\Closure $firmaConductor = null,
    ) {}
}
