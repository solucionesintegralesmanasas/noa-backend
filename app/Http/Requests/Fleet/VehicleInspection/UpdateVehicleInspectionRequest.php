<?php

declare(strict_types=1);

namespace App\Http\Requests\Fleet\VehicleInspection;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateVehicleInspectionRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'inspection' => 'sometimes|required|array',
            'inspection.vehicle_uuid' => 'sometimes|required|uuid|exists:vehicles,uuid',
            'inspection.inspection_date' => 'sometimes|required|date',
            'inspection.inspector_name' => 'nullable|string|max:150',
            'inspection.mileage' => 'sometimes|required|integer|min:0',
            'inspection.driver_uuid' => 'nullable|uuid|exists:third_parties,uuid',
            'inspection.notes' => 'nullable|string',
            'inspection.results' => 'nullable|array',
            'inspection.results.*.item_uuid' => 'required|uuid|exists:inspection_items,uuid',
            'inspection.results.*.is_selected' => 'sometimes|required|integer|in:0,1',
            'inspection.results.*.status' => 'sometimes|required|string|in:APROBADO,NO_APROBADO',
            'inspection.results.*.observations' => 'nullable|string',
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
            'inspection' => 'Inspección',
            'inspection.vehicle_uuid' => 'Vehículo',
            'inspection.inspection_date' => 'Fecha de inspección',
            'inspection.inspector_name' => 'Nombre del inspector',
            'inspection.mileage' => 'Kilometraje',
            'inspection.driver_uuid' => 'Conductor',
            'inspection.notes' => 'Notas adicionales',
        ];
    }
}
