<?php

declare(strict_types=1);

namespace App\Http\Requests\Fleet\VehicleBranch;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateVehicleBranchRequest extends FormRequest
{
    use HandlesApiResponse;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vehicle_uuid' => 'sometimes|required|uuid|exists:vehicles,uuid',
            'branch_uuid' => 'sometimes|required|uuid|exists:branches,uuid',
            'entry_date' => 'sometimes|required|date',
            'exit_date' => 'sometimes|required|date|after_or_equal:entry_date',
            'exit_type' => 'sometimes|required|in:ENTRADA,SALIDA,PRESTAMO',
            'is_active' => 'sometimes|required|boolean',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'vehicle_uuid' => 'UUID del vehículo',
            'branch_uuid' => 'UUID de la sucursal',
            'entry_date' => 'Fecha de entrada',
            'exit_date' => 'Fecha de salida',
            'exit_type' => 'Tipo de salida/sucursal',
            'is_active' => 'Estado activo/inactivo',
        ];
    }
}
