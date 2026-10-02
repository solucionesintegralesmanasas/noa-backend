<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

/**
 * Presupuestos de consultas (SPEC-004): fijan números, no milisegundos.
 *
 * Patrón: contar con el query log el mismo flujo con poco y con mucho volumen
 * y exigir el mismo número, más un tope absoluto. Una prueba que falla por una
 * máquina lenta es una mala prueba; estas solo observan consultas.
 */
trait PresupuestoConsultas
{
    /** Número de consultas que ejecuta el bloque. */
    protected function contarConsultas(callable $bloque): int
    {
        $estabaActivo = DB::logging();
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $bloque();
        } finally {
            if (! $estabaActivo) {
                DB::disableQueryLog();
            }
        }

        return count(DB::getQueryLog());
    }

    /**
     * El bloque no supera el máximo declarado. Devuelve el conteo medido.
     */
    protected function assertPresupuesto(int $maximo, callable $bloque, string $etiqueta): int
    {
        $n = $this->contarConsultas($bloque);
        $this->assertLessThanOrEqual($maximo, $n, "$etiqueta hizo $n consultas (presupuesto: $maximo)");

        return $n;
    }

    /**
     * Cordura antes de fiarse de un tope: si el flujo midió sobre un conjunto
     * vacío (p. ej. al sembrado le falta `company_uuid` y el listado no devuelve
     * filas), el conteo es falso y el tope no protege nada.
     */
    protected function assertMedicionConDatos(int $filas, string $etiqueta): void
    {
        $this->assertGreaterThan(0, $filas, "$etiqueta midió sobre un conjunto vacío (revisar el sembrado)");
    }

    /**
     * El bloque hace las mismas consultas antes y después de ampliar los datos
     * (detecta N+1 aunque el total absoluto parezca pequeño).
     *
     * @return array{int, int} [conteo con poco volumen, conteo con más volumen]
     */
    protected function assertConteoConstante(callable $medir, callable $ampliar, string $etiqueta): array
    {
        // Calentamiento sin medir: las consultas de arranque (roles, permisos,
        // caché de metadatos) se hacen una sola vez y no son crecimiento.
        $medir();
        $pocos = $this->contarConsultas($medir);
        $ampliar();
        $muchos = $this->contarConsultas($medir);
        $this->assertSame($pocos, $muchos, "$etiqueta: $pocos consultas con poco volumen; $muchos con más volumen");

        return [$pocos, $muchos];
    }
}
