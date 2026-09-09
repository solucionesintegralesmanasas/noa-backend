<?php

declare(strict_types=1);

namespace App\Http\Requests\ContractExtraction\Contractor;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Solicitud de validación para la actualización de un Contratista existente.
 */
class UpdateContractorRequest extends FormRequest
{
    use HandlesApiResponse;

    /**
     * Determina si el usuario está autorizado para realizar esta solicitud.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Obtiene las reglas de validación que se aplican a la solicitud.
     */
    public function rules(): array
    {
        return [
            'company_uuid' => 'nullable|uuid|exists:companies,uuid',
            'document_type_uuid' => 'nullable|uuid|exists:type_of_documents,uuid',
            'document_number' => 'nullable|string|max:20',
            'company_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'telephone' => 'nullable|string|max:20',
            'contract_number' => 'nullable|string|max:20',
            'contracting_party_city' => 'nullable|string|max:255',
            'vehicle_uuid' => 'nullable|uuid|exists:vehicles,uuid',
            'responsible_name' => 'nullable|string|max:255',
            'responsible_document' => 'nullable|string|max:20',
            'responsible_phone' => 'nullable|string|max:20',
            'responsible_address' => 'nullable|string|max:255',
            'status' => 'nullable|boolean',
        ];
    }

    /**
     * Obtiene los atributos personalizados para los errores de validación.
     */
    public function attributes(): array
    {
        return [
            'company_uuid' => 'UUID de la empresa',
            'document_type_uuid' => 'UUID del tipo de documento',
            'document_number' => 'número de documento',
            'company_name' => 'nombre de la empresa',
            'address' => 'dirección',
            'telephone' => 'teléfono',
            'contract_number' => 'número de contrato',
            'contracting_party_city' => 'ciudad de contratación',
            'vehicle_uuid' => 'UUID del vehículo',
            'responsible_name' => 'nombre del responsable',
            'responsible_document' => 'documento del responsable',
            'responsible_phone' => 'teléfono del responsable',
            'responsible_address' => 'dirección del responsable',
            'status' => 'estado',
        ];
    }

    /**
     * Maneja un intento de validación fallido.
     */
    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
