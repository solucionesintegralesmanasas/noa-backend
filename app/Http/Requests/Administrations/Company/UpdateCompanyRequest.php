<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrations\Company;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Request para validar la actualización de una Empresa.
 */
class UpdateCompanyRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'person_type' => 'nullable|in:PERSONA NATURAL,PERSONA JURIDICA',
            'type_of_company' => 'nullable|in:PUBLICO,PRIVADO',
            'economic_sector' => 'nullable|string|max:255',
            'legal_structure' => 'nullable|in:SOCIEDAD POR ACCIONES SIMPLIFICADA - SAS,SOCIEDAD DE RESPONSABILIDAD LIMITADA - LTDA,SOCIEDAD POR ACCIONES - SPA,SOCIEDAD ANONIMA - SA,UNIÓN TEMPORAL - UT,ENTIDAD SIN ÁNIMO DE LUCRO - ESAL',
            'document_type_uuid' => 'nullable|uuid|exists:type_of_documents,uuid',
            'document_number' => 'nullable|string|max:20',
            'verification_digit' => 'nullable|string|max:1',
            'business_name' => 'nullable|string|max:200',
            'trade_name' => 'nullable|string|max:100',
            'commercial_registration' => 'nullable|string|max:50',
            'municipality_uuid' => 'nullable|uuid|exists:cities,uuid',
            'address' => 'nullable|string|max:200',
            'postal_code' => 'nullable|string|max:10',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|string|email|max:100',
            'tax_regime_uuid' => 'nullable|uuid|exists:tax_regimes,uuid',
            'currency_code' => 'nullable|string|max:3',
            'approximate_number_of_employees' => 'nullable|string|max:10',
            'web_page' => 'nullable|string|max:255',
            'country_code' => 'nullable|string|max:2',
            'legal_representative_name' => 'nullable|string|max:255',
            'legal_representative_last_name' => 'nullable|string|max:255',
            'legal_representative_document_type' => 'nullable|string|max:20',
            'legal_representative_document_number' => 'nullable|string|max:20',
            'legal_representative_nationality' => 'nullable|string|max:20',
            'legal_representative_document_issue_date' => 'nullable|date',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'email.email' => 'El correo electrónico debe ser una dirección válida.',
            'document_type_uuid.exists' => 'El tipo de documento seleccionado no existe.',
            'municipality_uuid.exists' => 'El municipio seleccionado no existe.',
            'tax_regime_uuid.exists' => 'El régimen fiscal seleccionado no existe.',
        ];
    }

    public function attributes(): array
    {
        return [
            'person_type' => 'Tipo de persona',
            'type_of_company' => 'Naturaleza de la compañía',
            'email' => 'Correo electrónico',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
