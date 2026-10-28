<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Models\Signature;
use Illuminate\Support\Collection;

/**
 * Resolución de la firma que corresponde a cada casilla de la planilla.
 *
 * La firma se elige por `signer_role` y `status = vigente`, nunca por la
 * posición en que se guardó: `SignatureService` marca la anterior como
 * `reemplazada` pero la conserva, así que tomar la primera por `id` podía
 * imprimir una firma reemplazada o intercambiar conductor y funcionario.
 *
 * Respaldo posicional SOLO para firmas anteriores a `signer_role`: si el
 * grupo no declara ningún rol se conserva el comportamiento histórico
 * (funcionario = 0, conductor = 1). Si el grupo sí declara roles pero falta
 * el pedido, se devuelve null en vez de arriesgar una firma equivocada.
 */
final class ServiceControlSheetSignatures
{
    /**
     * @param  iterable<int, Signature>  $grupo
     * @param  int|null  $posicionRespaldo  índice histórico cuando no hay roles
     */
    public static function porRol(iterable $grupo, string $rol, ?int $posicionRespaldo = null): ?Signature
    {
        $firmas = $grupo instanceof Collection ? $grupo : collect($grupo);
        if ($firmas->isEmpty()) {
            return null;
        }

        $conRol = $firmas->filter(fn (Signature $firma) => ! empty($firma->signer_role));

        if ($conRol->isEmpty()) {
            return $posicionRespaldo === null
                ? null
                : $firmas->values()->get($posicionRespaldo);
        }

        return $conRol
            ->filter(fn (Signature $firma) => $firma->signer_role === $rol
                && ($firma->status ?? Signature::STATUS_VIGENTE) === Signature::STATUS_VIGENTE)
            ->sortByDesc('id')
            ->first();
    }
}
