<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs\TypeOfDocument;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Solicitud de validación para la creación de un nuevo Tipo de Documento.
 *
 * @author Darwin Montes
 *
 * @version 1.0.0
 */
class StoreTypeOfDocumentRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'prefix' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del tipo de documento es obligatorio.',
            'prefix.required' => 'El prefijo es obligatorio.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Nombre del Tipo de Documento',
            'prefix' => 'Prefijo',
            'status' => 'Estado',
        ];
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
