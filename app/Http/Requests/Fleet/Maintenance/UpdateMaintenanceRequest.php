<?php

declare(strict_types=1);

namespace App\Http\Requests\Fleet\Maintenance;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateMaintenanceRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_uuid' => 'sometimes|required|uuid|exists:companies,uuid',
            'vehicle_uuid' => 'sometimes|required|uuid|exists:vehicles,uuid',
            'maintenance_type' => 'sometimes|required|in:PREVENTIVA,CORRECTIVA,OTRO',
            'mileage' => 'sometimes|required|integer',
            'service_description' => 'sometimes|required|string',
            'mechanic_name' => 'nullable|string|max:150',
            'workshop_name' => 'nullable|string|max:150',
            'maintenance_date' => 'sometimes|required|date',
            'labor_cost' => 'nullable|numeric',
            'parts_cost' => 'nullable|numeric',
            'invoice_number' => 'nullable|string|max:50',
            'next_maintenance_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'status' => 'sometimes|required|in:Pendiente,Finalizado,Anulado',
            'parts' => 'nullable|array',
            'parts.*.part_name' => 'required|string|max:150',
            'parts.*.part_code' => 'nullable|string|max:50',
            'parts.*.quantity' => 'required|numeric',
            'parts.*.unit_cost' => 'required|numeric',
            'parts.*.supplier_uuid' => 'nullable|uuid|exists:third_parties,uuid',
            'parts.*.notes' => 'nullable|string',
            'address_type' => 'nullable|string|max:100',
            'steering_type' => 'nullable|string|max:30',
            'transmission_type' => 'nullable|string|max:30',
            'number_of_speeds' => 'nullable|string|max:30',
            'bearing_type' => 'nullable|string|max:30',
            'rear_suspension' => 'nullable|string|max:30',
            'number_of_tires' => 'nullable|string|max:20',
            'rim_size' => 'nullable|string|max:20',
            'rim_material' => 'nullable|string|max:20',
            'front_brake_type' => 'nullable|string|max:30',
            'rear_brake_type' => 'nullable|string|max:30',
            'number_of_windows' => 'nullable|string|max:20',
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
            'maintenance_type' => 'Tipo de mantenimiento',
            'mileage' => 'Kilometraje',
            'service_description' => 'Descripción del servicio',
            'mechanic_name' => 'Nombre del mecánico',
            'workshop_name' => 'Nombre del taller',
            'maintenance_date' => 'Fecha del mantenimiento',
            'labor_cost' => 'Costo de mano de obra',
            'parts_cost' => 'Costo de repuestos',
            'invoice_number' => 'Número de factura',
            'next_maintenance_date' => 'Fecha del próximo mantenimiento',
            'notes' => 'Notas adicionales',
            'status' => 'Estado',
        ];
    }
}
