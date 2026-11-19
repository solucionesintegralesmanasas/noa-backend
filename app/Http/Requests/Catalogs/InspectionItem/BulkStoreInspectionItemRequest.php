<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs\InspectionItem;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Solicitud de validación para la creación múltiple de ítems de inspección en una categoría.
 */
class BulkStoreInspectionItemRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['required', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.item_name' => ['required', 'string', 'max:150'],
            'items.*.description' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'category.required' => 'La categoría es obligatoria.',
            'category.max' => 'La categoría no debe superar los 100 caracteres.',
            'items.required' => 'Debes ingresar al menos un ítem de inspección.',
            'items.min' => 'Debes ingresar al menos un ítem de inspección.',
            'items.max' => 'Solo se permiten máximo 200 ítems por envío.',
            'items.*.item_name.required' => 'Todos los ítems deben tener un nombre.',
            'items.*.item_name.max' => 'El nombre del ítem no debe superar los 150 caracteres.',
        ];
    }

    public function attributes(): array
    {
        return [
            'category' => 'Categoría',
            'items' => 'Ítems de inspección',
            'items.*.item_name' => 'Nombre del ítem',
            'items.*.description' => 'Descripción',
        ];
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
