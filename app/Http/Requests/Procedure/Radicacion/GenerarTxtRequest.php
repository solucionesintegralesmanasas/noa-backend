<?php

declare(strict_types=1);

namespace App\Http\Requests\Procedure\Radicacion;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Petición de validación para generar el TXT de Registro Único Nacional de Tránsito.
 */
class GenerarTxtRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'origin' => 'required|in:ADMIN_FLOTA,PRESTACION',
        ];
    }

    public function messages(): array
    {
        return [
            'origin.required' => 'El origen del contrato es obligatorio.',
            'origin.in' => 'El origen del contrato no es válido.',
        ];
    }

    public function attributes(): array
    {
        return [
            'origin' => 'Origen del contrato',
        ];
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException($this->validationErrorResponse($validator->errors()->toArray()));
    }
}
