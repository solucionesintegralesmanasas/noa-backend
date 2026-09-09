<?php

declare(strict_types=1);

namespace App\Http\Requests\Fleet\InspectionResult;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreInspectionResultRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'inspection_uuid' => 'required|uuid|exists:vehicle_inspections,uuid',
            'item_uuid' => 'required|uuid|exists:inspection_items,uuid',
            'is_selected' => 'sometimes|required|boolean',
            'status' => 'sometimes|required|in:APROBADO,NO_APROBADO,NO_APLICA',
            'observations' => 'nullable|string',
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
            'inspection_uuid' => 'Inspección',
            'item_uuid' => 'Ítem de inspección',
            'is_selected' => 'Seleccionado',
            'status' => 'Estado',
            'observations' => 'Observaciones',
        ];
    }
}
