<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrations\Contact;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Request para validar la creación de un Contacto.
 */
class StoreContactRequest extends FormRequest
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
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'representative_type' => 'required|in:COMERCIAL,REPRESENTANTE_LEGAL,TECNICO,CARTERA,COMPRAS,CONTACTO_FACTURACION,CUMPLIMIENTO,FINANCIERO,HSE,JURIDICO,REPRESENTANTE_LEGAL_SUPLENTE',
            'country' => 'nullable|string|max:100',
            'municipality_uuid' => 'nullable|uuid|exists:cities,uuid',
            'email' => 'nullable|string|email|max:150',
            'phone_number' => 'nullable|string|max:50',
            'remarks' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'company_uuid.exists' => 'La empresa vinculada no existe en nuestros registros.',
            'representative_type.in' => 'El tipo de representante seleccionado no es válido.',
            'email.email' => 'El formato del correo electrónico es inválido.',
        ];
    }

    public function attributes(): array
    {
        return [
            'first_name' => 'Nombres',
            'last_name' => 'Apellidos',
            'representative_type' => 'Tipo de Representante',
            'company_uuid' => 'UUID de Empresa',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
