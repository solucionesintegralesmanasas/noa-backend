<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs\InspectionItem;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Solicitud de validación para crear o actualizar una categoría de ítems de inspección.
 */
class StoreInspectionCategoryRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la categoría es obligatorio.',
            'name.max' => 'El nombre de la categoría no debe superar los 100 caracteres.',
            'code.max' => 'El código de la categoría no debe superar los 100 caracteres.',
            'description.max' => 'La descripción no debe superar los 255 caracteres.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Nombre de categoría',
            'code' => 'Código',
            'description' => 'Descripción',
        ];
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
