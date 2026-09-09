<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs\InspectionItem;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Solicitud de validación para la creación de un nuevo Ítem de Inspección.
 *
 * @author Darwin Montes
 *
 * @version 1.0.0
 */
class StoreInspectionItemRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['required', 'in:DOCUMENTOS,DOTACION,VIDRIOS_ESPEJOS,OTROS,EMERGENCIAS,EXTINTOR,HERRAMIENTAS,LUCES,FLUIDOS,NEUMATICOS,PRESION'],
            'item_name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'category.required' => 'La categoría del ítem es obligatoria.',
            'category.in' => 'La categoría seleccionada no es válida.',
            'item_name.required' => 'El nombre del ítem es obligatorio.',
        ];
    }

    public function attributes(): array
    {
        return [
            'category' => 'Categoría',
            'item_name' => 'Nombre del Ítem',
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
