<?php

declare(strict_types=1);

namespace App\Http\Requests\ContractExtraction\Contractor;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Solicitud de validación para la creación de un nuevo Contratista.
 */
class StoreContractorRequest extends FormRequest
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
            'company_uuid' => 'required|uuid|exists:companies,uuid',
            'document_type_uuid' => 'required|uuid|exists:type_of_documents,uuid',
            'document_number' => 'required|string|max:20',
            'company_name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'telephone' => 'required|string|max:20',
            'contract_number' => 'required|string|max:20',
            'contracting_party_city' => 'required|string|max:255',
            'vehicle_uuid' => 'required|uuid|exists:vehicles,uuid',
            'responsible_name' => 'required|string|max:255',
            'responsible_document' => 'required|string|max:20',
            'responsible_phone' => 'required|string|max:20',
            'responsible_address' => 'required|string|max:255',
            'status' => 'nullable|boolean',
        ];
    }

    /**
     * Obtiene los mensajes de error personalizados para las reglas de validación definidas.
     */
    public function messages(): array
    {
        return [
            'company_uuid.required' => 'El UUID de la empresa es obligatorio.',
            'company_uuid.exists' => 'La empresa seleccionada no es válida.',
            'document_type_uuid.required' => 'El tipo de documento es obligatorio.',
            'document_type_uuid.exists' => 'El tipo de documento seleccionado no es válido.',
            'document_number.required' => 'El número de documento es obligatorio.',
            'company_name.required' => 'El nombre de la empresa es obligatorio.',
            'address.required' => 'La dirección es obligatoria.',
            'telephone.required' => 'El teléfono es obligatorio.',
            'contract_number.required' => 'El número de contrato es obligatorio.',
            'contracting_party_city.required' => 'La ciudad de contratación es obligatoria.',
            'vehicle_uuid.required' => 'El vehículo es obligatorio.',
            'vehicle_uuid.exists' => 'El vehículo seleccionado no es válido.',
            'responsible_name.required' => 'El nombre del responsable es obligatorio.',
            'responsible_document.required' => 'El documento del responsable es obligatorio.',
            'responsible_phone.required' => 'El teléfono del responsable es obligatorio.',
            'responsible_address.required' => 'La dirección del responsable es obligatoria.',
        ];
    }

    /**
     * Obtiene los atributos personalizados para los errores de validación.
     */
    public function attributes(): array
    {
        return [
            'uuid' => 'UUID del contratista',
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
