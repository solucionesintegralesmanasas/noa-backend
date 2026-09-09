<?php

declare(strict_types=1);

namespace App\Http\Requests\Fleet\MaintenancePart;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateMaintenancePartRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'part_name' => 'sometimes|required|string|max:150',
            'part_code' => 'nullable|string|max:50',
            'quantity' => 'sometimes|required|numeric',
            'unit_cost' => 'sometimes|required|numeric',
            'supplier_uuid' => 'nullable|uuid|exists:third_parties,uuid',
            'notes' => 'nullable|string',
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
            'part_name' => 'Nombre del repuesto',
            'part_code' => 'Código del repuesto',
            'quantity' => 'Cantidad',
            'unit_cost' => 'Costo unitario',
            'supplier_uuid' => 'Proveedor',
            'notes' => 'Notas adicionales',
        ];
    }
}
