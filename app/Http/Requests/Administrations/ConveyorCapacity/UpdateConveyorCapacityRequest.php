<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrations\ConveyorCapacity;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Request para validar la actualización de Capacidad de Transporte.
 */
class UpdateConveyorCapacityRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enabling_resolution_uuid' => 'nullable|uuid|exists:enabling_resolutions,uuid',
            'vehicle_type' => 'nullable|string|max:20',
            'authorized_capacity' => 'nullable|integer',
            'current_capacity' => 'nullable|integer',
            'minimum_own_capacity' => 'nullable|integer',
            'status' => 'nullable|boolean',
        ];
    }

    public function attributes(): array
    {
        return [
            'vehicle_type' => 'Tipo de Vehículo',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
