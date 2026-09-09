<?php

declare(strict_types=1);

namespace App\Http\Requests\Fleet\VehicleBranch;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreVehicleBranchRequest extends FormRequest
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
            'company_uuid' => 'required|uuid|exists:companies,uuid',
            'vehicle_uuid' => 'required|uuid|exists:vehicles,uuid',
            'branch_uuid' => 'required|uuid|exists:branches,uuid',
            'entry_date' => 'required|date',
            'exit_date' => 'required|date|after_or_equal:entry_date',
            'exit_type' => 'required|in:ENTRADA,SALIDA,PRESTAMO',
            'is_active' => 'nullable|boolean',
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
            'company_uuid' => 'UUID de la empresa',
            'vehicle_uuid' => 'UUID del vehículo',
            'branch_uuid' => 'UUID de la sucursal',
            'entry_date' => 'Fecha de entrada',
            'exit_date' => 'Fecha de salida',
            'exit_type' => 'Tipo de salida/sucursal',
            'is_active' => 'Estado activo/inactivo',
        ];
    }
}
