<?php

declare(strict_types=1);

namespace App\Http\Requests\Fleet\Vehicle;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateVehicleRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'third_party_uuid' => 'sometimes|required|uuid|exists:third_parties,uuid',
            'vehicle_license_plate' => 'sometimes|required|string|max:20',
            'transit_license_number' => 'sometimes|required|string|max:20',
            'type_of_service' => 'sometimes|required|in:PUBLICO,PARTICULAR',
            'vehicle_class_uuid' => 'sometimes|required|uuid|exists:vehicle_class,uuid',
            'brand_uuid' => 'sometimes|required|uuid|exists:brands,uuid',
            'line' => 'sometimes|required|string|max:50',
            'model' => 'sometimes|required|string|max:10',
            'color' => 'sometimes|required|string|max:30',
            'serial_number' => 'sometimes|nullable|string|max:50',
            'engine_number' => 'sometimes|required|string|max:50',
            'chassis_number' => 'sometimes|required|string|max:50',
            'vin_number' => 'sometimes|nullable|string|max:50',
            'engine_displacement' => 'sometimes|required|string|max:20',
            'body_type' => 'sometimes|required|string|max:30',
            'fuel_type' => 'sometimes|required|in:GASOLINA,DIESEL,GAS,GNV,ELECTRICIDAD,ELECTRICO,HIBRIDO,OTRO',
            'registration_date' => 'sometimes|required|date',
            'transit_authority' => 'sometimes|required|string|max:50',
            'doors' => 'sometimes|required|integer',
            'load_capacity' => 'sometimes|required|integer',
            'gross_vehicle_weight' => 'sometimes|required|integer',
            'passenger_capacity' => 'sometimes|required|integer',
            'seated_passenger_capacity' => 'sometimes|required|integer',
            'number_of_axles' => 'sometimes|required|integer',
            'branch_uuid' => 'nullable|uuid|exists:branches,uuid',
            'exact_payment' => 'sometimes|required|boolean',
            'internal_number' => 'nullable|string|max:20',
            'steering_type' => 'sometimes|nullable|string|max:30',
            'transmission_type' => 'sometimes|nullable|string|max:30',
            'number_of_speeds' => 'sometimes|nullable|string|max:30',
            'bearing_type' => 'sometimes|nullable|string|max:30',
            'rear_suspension' => 'sometimes|nullable|string|max:30',
            'number_of_tires' => 'sometimes|nullable|integer',
            'rim_size' => 'sometimes|nullable|string|max:30',
            'rim_material' => 'sometimes|nullable|string|max:20',
            'front_brake_type' => 'sometimes|nullable|string|max:30',
            'rear_brake_type' => 'sometimes|nullable|string|max:30',
            'number_of_windows' => 'sometimes|nullable|integer',
            'is_active' => 'sometimes|required|boolean',
            'owner' => 'nullable|array',
            'owner.third_party_uuid' => 'nullable|uuid|exists:third_parties,uuid',
            'owner.document_type_uuid' => 'required_with:owner|uuid|exists:type_of_documents,uuid',
            'owner.owner_name' => 'required_with:owner|string|max:255',
            'owner.document_number' => 'required_with:owner|string|max:20',
            'owner.verification_digit' => 'nullable|string|max:1',
        ];
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }

    public function attributes(): array
    {
        return [
            'vehicle_license_plate' => 'Placa del vehículo',
            'transit_license_number' => 'Número de la licencia de tránsito',
            'type_of_service' => 'Tipo de servicio',
            'vehicle_class_uuid' => 'Clase de vehículo',
            'brand_uuid' => 'Marca',
            'line' => 'Línea',
            'model' => 'Modelo',
            'color' => 'Color',
            'serial_number' => 'Número de serie',
            'engine_number' => 'Número de motor',
            'chassis_number' => 'Número de chasis',
            'vin_number' => 'Número de VIN',
            'engine_displacement' => 'Cilindraje',
            'body_type' => 'Tipo de carrocería',
            'fuel_type' => 'Tipo de combustible',
            'registration_date' => 'Fecha de registro',
            'transit_authority' => 'Autoridad de tránsito',
            'doors' => 'Número de puertas',
            'load_capacity' => 'Capacidad de carga',
            'gross_vehicle_weight' => 'Peso bruto vehicular',
            'passenger_capacity' => 'Capacidad de pasajeros',
            'seated_passenger_capacity' => 'Capacidad de pasajeros sentados',
            'number_of_axles' => 'Número de ejes',
            'branch_uuid' => 'Sucursal',
            'exact_payment' => 'Pago exacto',
            'internal_number' => 'Número interno',
            'steering_type' => 'Tipo de dirección',
            'transmission_type' => 'Tipo de transmisión',
            'number_of_speeds' => 'Número de velocidades',
            'bearing_type' => 'Tipo de rodamiento',
            'rear_suspension' => 'Tipo de suspensión trasera',
            'number_of_tires' => 'Número de llantas',
            'rim_size' => 'Tamaño del rin',
            'rim_material' => 'Material del rin',
            'front_brake_type' => 'Tipo de freno delantero',
            'rear_brake_type' => 'Tipo de freno trasero',
            'number_of_windows' => 'Número de ventanas',
            'is_active' => 'Estado activo',
            'owner' => 'Propietario',
            'owner.third_party_uuid' => 'UUID del Tercero (Propietario)',
            'owner.document_type_uuid' => 'Tipo de documento (Propietario)',
            'owner.owner_name' => 'Nombre del propietario',
            'owner.document_number' => 'Número de documento (Propietario)',
            'owner.verification_digit' => 'Dígito de verificación (Propietario)',
        ];
    }
}
