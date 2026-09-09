<?php

declare(strict_types=1);

namespace App\Http\Requests\Fleet\VehicleDocument;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreVehicleDocumentRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_uuid' => 'required|uuid|exists:companies,uuid',
            'vehicle_uuid' => 'required|uuid|exists:vehicles,uuid',
            'document_type' => 'required|in:SOAT,RCE,RCC,RTM',
            'policy_number' => 'required|string|max:200',
            'issue_date' => 'required|date',
            'effective_date' => 'nullable|date',
            'expiry_date' => 'required|date',
            'issuing_entity' => 'required|string|max:200',
            'tariff_code' => 'nullable|string|max:5',
            'taker' => 'nullable|string|max:200',
            'status' => 'required|in:SI,NO,VIGENTE,INACTIVA,NO VIGENTE',
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
            'company_uuid' => 'Empresa',
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
