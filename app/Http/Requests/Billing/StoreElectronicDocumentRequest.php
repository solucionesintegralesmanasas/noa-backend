<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use App\Models\ElectronicDocument;
use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Registro de un documento electrónico genérico ApiDian (fase 3).
 *
 * El payload llega ya armado con el formato del proveedor; la empresa y el
 * proveedor se resuelven en el servicio/controlador, no en el cliente.
 */
class StoreElectronicDocumentRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'api_document_type' => ['required', 'string', Rule::in(ElectronicDocument::TIPOS)],
            'event_code' => [
                'nullable', 'string', 'size:3',
                Rule::requiredIf(fn () => $this->input('api_document_type') === ElectronicDocument::TIPO_EVENTO),
                'regex:/^(0[345]\d)$/',
            ],
            'document_number' => ['nullable', 'string', 'max:50'],
            'billing_provider_uuid' => ['nullable', 'uuid', 'exists:billing_providers,uuid'],
            'payload' => ['required', 'array'],
            'payload.send' => ['prohibited'], // la bandera la pone el proveedor de la empresa
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'api_document_type' => 'Tipo de documento',
            'event_code' => 'Código de evento RADIAN',
            'document_number' => 'Número del documento',
            'billing_provider_uuid' => 'Proveedor tecnológico',
            'payload' => 'Payload del documento',
        ];
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
