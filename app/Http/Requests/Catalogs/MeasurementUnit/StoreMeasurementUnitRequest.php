<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs\MeasurementUnit;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Solicitud de validación para la creación de una nueva Unidad de Medida.
 *
 * @author Darwin Montes
 *
 * @version 1.0.0
 */
class StoreMeasurementUnitRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:10', 'unique:measurement_units,code'],
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'El código de la unidad es obligatorio.',
            'code.unique' => 'El código de la unidad ya se encuentra registrado.',
            'name.required' => 'El nombre de la unidad es obligatorio.',
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'Código Unidad',
            'name' => 'Nombre Unidad',
            'is_active' => 'Vigente',
        ];
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
