<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs\Brand;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Solicitud de validación para la creación de una nueva Marca.
 *
 * @author Darwin Montes
 *
 * @version 1.0.0
 */
class StoreBrandRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'description.required' => 'La descripción o nombre de la marca es obligatoria.',
        ];
    }

    public function attributes(): array
    {
        return [
            'description' => 'Descripción de la Marca',
        ];
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
