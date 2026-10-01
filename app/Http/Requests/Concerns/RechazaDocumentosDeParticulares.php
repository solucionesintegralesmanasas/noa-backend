<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Models\OperationCard;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Validation\Validator;

/**
 * Un vehículo particular no tiene pólizas RCC/RCE ni tarjeta de operación (solo SOAT y RTM).
 * Se rechaza al REGISTRAR; los registros históricos existentes se pueden seguir editando
 * mientras no cambien de vehículo ni de tipo.
 */
trait RechazaDocumentosDeParticulares
{
    public const MENSAJE_POLIZA_PARTICULAR = 'Un vehículo particular no tiene pólizas RCC/RCE: solo registra SOAT y tecnomecánica.';

    public const MENSAJE_TARJETA_PARTICULAR = 'Un vehículo particular no tiene tarjeta de operación.';

    private function vehiculoPorUuid(?string $uuid): ?Vehicle
    {
        return $uuid ? Vehicle::withoutGlobalScopes()->where('uuid', $uuid)->first() : null;
    }

    /** Valida un documento (alta o cambio de vehículo/tipo) contra el tipo de servicio del vehículo. */
    protected function validarDocumentoContraVehiculo(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $actual = $this->route('uuid')
            ? VehicleDocument::withoutGlobalScopes()->where('uuid', $this->route('uuid'))->first()
            : null;

        $vehicleUuid = $this->input('vehicle_uuid', $actual?->vehicle_uuid);
        $tipo = $this->input('document_type', $actual?->document_type);

        // En edición, solo se valida si cambia el vehículo o el tipo: no se bloquea el historial.
        if ($actual && $vehicleUuid === $actual->vehicle_uuid && strtoupper((string) $tipo) === strtoupper((string) $actual->document_type)) {
            return;
        }

        $vehiculo = $this->vehiculoPorUuid($vehicleUuid);
        if ($vehiculo && ! $vehiculo->admiteTipoDocumento($tipo)) {
            $validator->errors()->add('document_type', self::MENSAJE_POLIZA_PARTICULAR);
        }
    }

    /** Valida una tarjeta de operación (alta o cambio de vehículo) contra el tipo de servicio. */
    protected function validarTarjetaContraVehiculo(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $actual = $this->route('uuid')
            ? OperationCard::withoutGlobalScopes()->where('uuid', $this->route('uuid'))->first()
            : null;

        $vehicleUuid = $this->input('vehicle_uuid', $actual?->vehicle_uuid);

        if ($actual && $vehicleUuid === $actual->vehicle_uuid) {
            return;
        }

        $vehiculo = $this->vehiculoPorUuid($vehicleUuid);
        if ($vehiculo && ! $vehiculo->requiereTarjetaOperacion()) {
            $validator->errors()->add('vehicle_uuid', self::MENSAJE_TARJETA_PARTICULAR);
        }
    }
}
