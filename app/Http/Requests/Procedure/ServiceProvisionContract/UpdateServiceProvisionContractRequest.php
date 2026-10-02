<?php

declare(strict_types=1);

namespace App\Http\Requests\Procedure\ServiceProvisionContract;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Petición de validación para la actualización de un Contrato de Prestación.
 */
class UpdateServiceProvisionContractRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_uuid' => 'nullable|uuid|exists:companies,uuid',
            'procedure_uuid' => 'nullable|uuid|exists:procedures,uuid',
            'vehicle_uuid' => 'nullable|uuid|exists:vehicles,uuid',
            'third_party_uuid' => 'nullable|uuid|exists:third_parties,uuid',
            'contract_number' => 'nullable|string|max:50',
            'issue_date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'duration' => 'nullable|integer|min:1',
            'valuation_amount' => 'nullable|numeric|min:0',
            'coverage' => 'nullable|string|max:255',
            'object_description' => 'nullable|string',
            'status' => 'nullable|in:BORRADOR,PENDIENTE_FIRMA,FIRMADO,VENCIDO,CANCELADO',
            'document_hash' => 'nullable|string|max:128',
        ];
    }

    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'La fecha de fin debe ser posterior o igual a la fecha de inicio.',
        ];
    }

    public function attributes(): array
    {
        return [
            'company_uuid' => 'Empresa',
            'procedure_uuid' => 'Trámite',
            'vehicle_uuid' => 'Vehículo',
            'third_party_uuid' => 'Tercero/Contratante',
            'contract_number' => 'Número de contrato',
            'issue_date' => 'Fecha de emisión',
            'start_date' => 'Fecha de inicio',
            'end_date' => 'Fecha de fin',
            'duration' => 'Duración (días)',
            'valuation_amount' => 'Valor de tasación',
            'coverage' => 'Cobertura',
            'object_description' => 'Descripción del objeto',
            'status' => 'Estado',
            'document_hash' => 'Hash del documento',
        ];
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException($this->validationErrorResponse($validator->errors()->toArray()));
    }
}