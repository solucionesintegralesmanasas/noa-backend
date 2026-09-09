<?php

declare(strict_types=1);

namespace App\Http\Requests\Fleet\BusinessCollaborationAgreement;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreBusinessCollaborationAgreementRequest extends FormRequest
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
            'resolution_number' => 'nullable|string|max:50',
            'agreement_internal_id' => 'nullable|string|max:50',
            'contracting_entity_nit' => 'required|string|max:20',
            'contracting_entity_name' => 'required|string|max:255',
            'effective_date' => 'required|date',
            'expiry_date' => 'required|date',
            'rep_name' => 'required|string|max:150',
            'rep_document_id' => 'required|string|max:20',
            'transport_modality' => 'required|in:CARGA,ESPECIAL,PASAJEROS,MIXTO',
            'max_fleet_capacity' => 'nullable|integer',
            'status' => 'sometimes|required|boolean',
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
            'resolution_number' => 'Número de resolución',
            'agreement_internal_id' => 'ID interno del acuerdo',
            'contracting_entity_nit' => 'NIT de la entidad contratante',
            'contracting_entity_name' => 'Nombre de la entidad contratante',
            'effective_date' => 'Fecha de inicio de vigencia',
            'expiry_date' => 'Fecha de expiración',
            'rep_name' => 'Nombre del representante',
            'rep_document_id' => 'Documento del representante',
            'transport_modality' => 'Modalidad de transporte',
            'max_fleet_capacity' => 'Capacidad máxima de la flota',
            'status' => 'Estado',
        ];
    }
}
