<?php

declare(strict_types=1);

namespace App\Http\Requests\ContractExtraction\FuecPassenger;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Solicitud de validación para la actualización de un Pasajero de FUEC existente.
 */
class UpdateFuecPassengerRequest extends FormRequest
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
            'fuec_uuid' => 'nullable|uuid|exists:fuec,uuid',
            'type_of_document_uuid' => 'nullable|uuid|exists:type_of_documents,uuid',
            'document_number' => 'nullable|string|max:20',
            'first_and_last_name' => 'nullable|string|max:255',
        ];
    }

    /**
     * Obtiene los atributos personalizados para los errores de validación.
     */
    public function attributes(): array
    {
        return [
            'fuec_uuid' => 'UUID del FUEC',
            'type_of_document_uuid' => 'UUID del tipo de documento',
            'document_number' => 'número de documento',
            'first_and_last_name' => 'nombre y apellido',
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
