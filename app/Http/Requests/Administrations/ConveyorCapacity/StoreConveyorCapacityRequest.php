<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrations\ConveyorCapacity;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Request para validar la creación de Capacidad de Transporte.
 */
class StoreConveyorCapacityRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $data = $this->all();

        // Si se envía un arreglo de registros
        if (isset($data[0]) && is_array($data[0])) {
            return [
                '*' => 'array',
                '*.enabling_resolution_uuid' => 'required|uuid|exists:enabling_resolutions,uuid',
                '*.vehicle_type' => 'required|string|max:20',
                '*.authorized_capacity' => 'required|integer',
                '*.current_capacity' => 'required|integer',
                '*.minimum_own_capacity' => 'required|integer',
                '*.status' => 'nullable|boolean',
            ];
        }

        // Si se envía un solo registro
        return [
            'enabling_resolution_uuid' => 'required|uuid|exists:enabling_resolutions,uuid',
            'vehicle_type' => 'required|string|max:20',
            'authorized_capacity' => 'required|integer',
            'current_capacity' => 'required|integer',
            'minimum_own_capacity' => 'required|integer',
            'status' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'vehicle_type.required' => 'El tipo de vehículo es obligatorio.',
            'authorized_capacity.integer' => 'La capacidad autorizada debe ser un número entero.',
        ];
    }

    public function attributes(): array
    {
        return [
            'vehicle_type' => 'Tipo de Vehículo',
            'authorized_capacity' => 'Capacidad Autorizada',
            'current_capacity' => 'Capacidad Actual',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
