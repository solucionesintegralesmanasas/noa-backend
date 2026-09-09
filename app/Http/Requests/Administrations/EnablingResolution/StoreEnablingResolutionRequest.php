<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrations\EnablingResolution;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Request para validar la creación de una Resolución de Habilitación.
 */
class StoreEnablingResolutionRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_uuid' => 'required|uuid|exists:companies,uuid',
            'resolution_number' => 'required|string|max:20',
            'number_fuec' => 'required|string|max:20',
            'territorial_code' => 'required|string|max:20',
            'resolution_date' => 'required|date',
            'status' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'resolution_number.required' => 'El número de resolución es obligatorio.',
            'number_fuec.required' => 'El número FUEC es obligatorio.',
        ];
    }

    public function attributes(): array
    {
        return [
            'resolution_number' => 'Número de Resolución',
            'number_fuec' => 'Número FUEC',
            'resolution_date' => 'Fecha de Resolución',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
