<?php

declare(strict_types=1);

namespace App\Http\Requests\ContractExtraction\Fuec;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Solicitud de validación para la actualización de un FUEC existente.
 */
class UpdateFuecRequest extends FormRequest
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
            'issue_date' => 'nullable|date',
            'request_number' => 'nullable|string|max:25',
            'number_fuec' => 'nullable|string|max:4',
            'contract_number_display' => 'nullable|string|max:4',
            'company_uuid' => 'nullable|uuid|exists:companies,uuid',
            'contractor_uuid' => 'nullable|uuid|exists:contractors,uuid',
            'vehicle_uuid' => 'nullable|uuid|exists:vehicles,uuid',
            'effective_date' => 'nullable|date',
            'expiration_date' => 'nullable|date',
            'origin_route' => 'nullable|string|max:255',
            'destination_route' => 'nullable|string|max:255',
            'object_contract_uuid' => 'nullable|uuid|exists:objects_contracts,uuid',
            'main_conductor_uuid' => 'nullable|uuid|exists:third_parties,uuid',
            'secondary_conductor_uuid' => 'nullable|uuid|exists:third_parties,uuid',
            'tertiary_conductor_uuid' => 'nullable|uuid|exists:third_parties,uuid',
            'verification_code' => 'nullable|string|max:100',
            'status' => 'nullable|in:ACTIVO,CERRADO,ANULADO',
            'passengers' => 'nullable|array',
            'passengers.*.uuid' => 'nullable|uuid',
            'passengers.*.type_of_document_uuid' => 'required|uuid|exists:type_of_documents,uuid',
            'passengers.*.document_number' => 'required|string|max:50',
            'passengers.*.first_and_last_name' => 'required|string|max:255',
            'contractor' => 'nullable|array',
            'contractor.uuid' => 'nullable|uuid',
            'contractor.document_type_uuid' => 'required_with:contractor|uuid|exists:type_of_documents,uuid',
            'contractor.document_number' => 'required_with:contractor|string|max:20',
            'contractor.company_name' => 'required_with:contractor|string|max:255',
            'contractor.address' => 'required_with:contractor|string|max:255',
            'contractor.telephone' => 'required_with:contractor|string|max:20',
            'contractor.contract_number' => 'required_with:contractor|string|max:20',
            'contractor.contracting_party_city' => 'required_with:contractor|string|max:255',
            'contractor.vehicle_uuid' => 'required_with:contractor|uuid|exists:vehicles,uuid',
            'contractor.responsible_name' => 'required_with:contractor|string|max:255',
            'contractor.responsible_document' => 'required_with:contractor|string|max:20',
            'contractor.responsible_phone' => 'required_with:contractor|string|max:20',
            'contractor.responsible_address' => 'required_with:contractor|string|max:255',
            'contractor.status' => 'nullable|integer|in:0,1',
        ];
    }

    /**
     * Obtiene los atributos personalizados para los errores de validación.
     */
    public function attributes(): array
    {
        return [
            'issue_date' => 'fecha de expedición',
            'request_number' => 'número de solicitud',
            'number_fuec' => 'número de FUEC',
            'contract_number_display' => 'número de contrato',
            'company_uuid' => 'UUID de la empresa',
            'contractor_uuid' => 'UUID del contratista',
            'vehicle_uuid' => 'UUID del vehículo',
            'effective_date' => 'fecha de inicio',
            'expiration_date' => 'fecha de fin',
            'origin_route' => 'ruta de origen',
            'destination_route' => 'ruta de destino',
            'object_contract_uuid' => 'UUID del objeto de contrato',
            'main_conductor_uuid' => 'UUID del conductor principal',
            'secondary_conductor_uuid' => 'UUID del conductor secundario',
            'tertiary_conductor_uuid' => 'UUID del conductor terciario',
            'verification_code' => 'código de verificación',
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
