<?php

declare(strict_types=1);

namespace App\Http\Requests\Fleet\VehicleDocument;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateVehicleDocumentRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vehicle_uuid' => 'sometimes|required|uuid|exists:vehicles,uuid',
            'document_type' => 'sometimes|required|in:SOAT,RCE,RCC,RTM',
            'policy_number' => 'sometimes|required|string|max:200',
            'issue_date' => 'sometimes|required|date',
            'effective_date' => 'nullable|date',
            'expiry_date' => 'sometimes|required|date',
            'issuing_entity' => 'sometimes|required|string|max:200',
            'tariff_code' => 'nullable|string|max:5',
            'taker' => 'nullable|string|max:200',
            'status' => 'sometimes|required|in:SI,NO,VIGENTE,INACTIVA,NO VIGENTE',
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
            'vehicle_uuid' => 'Vehículo',
            'document_type' => 'Tipo de documento',
            'policy_number' => 'Número de póliza',
            'issue_date' => 'Fecha de emisión',
            'effective_date' => 'Fecha de inicio de vigencia',
            'expiry_date' => 'Fecha de vencimiento',
            'issuing_entity' => 'Entidad emisora',
            'tariff_code' => 'Código de tarifa',
            'taker' => 'Tomador',
            'status' => 'Estado',
        ];
    }
}
