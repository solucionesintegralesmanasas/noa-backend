<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs\InspectionItem;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Solicitud de validación para la actualización de un Ítem de Inspección existente.
 *
 * @author Darwin Montes
 *
 * @version 1.0.0
 */
class UpdateInspectionItemRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['nullable', 'in:DOCUMENTOS,DOTACION,VIDRIOS_ESPEJOS,OTROS,EMERGENCIAS,EXTINTOR,HERRAMIENTAS,LUCES,FLUIDOS,NEUMATICOS,PRESION'],
            'item_name' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'category.in' => 'La categoría seleccionada no es válida.',
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
