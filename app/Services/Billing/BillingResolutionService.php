<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\BillingResolution;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Model;

/** Gestión de resoluciones de facturación (rango interno por ahora). */
class BillingResolutionService extends BaseService
{
    protected function getModelInstance(): Model
    {
        return new BillingResolution;
    }

    /**
     * Reserva el siguiente consecutivo de la resolución con bloqueo de fila.
     *
     * @throws \RuntimeException Si la resolución no está vigente o agotó el rango.
     */
    public function tomarConsecutivo(string $uuid): int
    {
        return $this->transaction(function () use ($uuid) {
            /** @var BillingResolution|null $resolucion */
            $resolucion = BillingResolution::where('uuid', $uuid)->lockForUpdate()->first();

            if (! $resolucion) {
                throw new \RuntimeException('La resolución de facturación no existe.');
            }

            if (! $resolucion->estaVigente()) {
                throw new \RuntimeException('La resolución no está vigente o agotó su rango.');
            }

            $siguiente = $resolucion->last_consecutive + 1;
            $resolucion->withoutLogging(fn () => $resolucion->update(['last_consecutive' => $siguiente]));

            return $siguiente;
        });
    }
}
