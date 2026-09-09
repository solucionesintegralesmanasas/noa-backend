<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs\MeasurementUnit;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Solicitud de validación para la actualización de una Unidad de Medida existente.
 *
 * @author Darwin Montes
 *
 * @version 1.0.0
 */
class UpdateMeasurementUnitRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $uuid = $this->route('uuid');

        return [
            'code' => ['nullable', 'string', 'max:10', 'unique:measurement_units,code,'.$uuid.',uuid'],
            'name' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'El código de la unidad ya se encuentra registrado.',
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
