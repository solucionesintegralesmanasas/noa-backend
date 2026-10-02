<?php

declare(strict_types=1);

namespace App\Http\Requests\Procedure\Radicacion;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Petición de validación para generar un enlace de firma de contrato.
 */
class StoreEnlaceFirmaRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_uuid' => 'required|uuid',
            'contract_origin' => 'required|in:ADMIN_FLOTA,PRESTACION',
            'contract_uuid' => 'required|uuid',
            'signer_role' => 'required|in:PROPIETARIO,REP_LEGAL,CLIENTE,TESTIGO',
            'signer_name' => 'required|string',
            'signer_document' => 'required|string',
            'signer_email' => 'nullable|email',
            'signer_phone' => 'nullable|string',
            'enviar_correo' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'company_uuid.required' => 'La empresa es obligatoria.',
            'contract_origin.required' => 'El origen del contrato es obligatorio.',
            'contract_origin.in' => 'El origen del contrato no es válido.',
            'contract_uuid.required' => 'El contrato es obligatorio.',
            'signer_role.required' => 'El rol del firmante es obligatorio.',
            'signer_role.in' => 'El rol del firmante no es válido.',
            'signer_name.required' => 'El nombre del firmante es obligatorio.',
            'signer_document.required' => 'El documento del firmante es obligatorio.',
            'signer_email.email' => 'El correo del firmante debe ser una dirección válida.',
        ];
    }

    public function attributes(): array
    {
        return [
            'company_uuid' => 'Empresa',
            'contract_origin' => 'Origen del contrato',
            'contract_uuid' => 'Contrato',
            'signer_role' => 'Rol del firmante',
            'signer_name' => 'Nombre del firmante',
            'signer_document' => 'Documento del firmante',
            'signer_email' => 'Correo del firmante',
            'signer_phone' => 'Teléfono del firmante',
            'enviar_correo' => 'Enviar correo',
        ];
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException($this->validationErrorResponse($validator->errors()->toArray()));
    }
}
