<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrations\Experience;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Request para validar la creación de una Experiencia.
 */
class StoreExperienceRequest extends FormRequest
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
            'customer_name' => 'required|string|max:255',
            'value_before_tax' => 'nullable|numeric',
            'currency' => 'nullable|string|max:10',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'is_ongoing' => 'nullable|date',
            'remarks' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'customer_name.required' => 'El nombre del cliente es obligatorio.',
        ];
    }

    public function attributes(): array
    {
        return [
            'customer_name' => 'Nombre del Cliente',
            'value_before_tax' => 'Valor antes de Impuestos',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
