<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrations\Experience;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Request para validar la actualización de una Experiencia.
 */
class UpdateExperienceRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_uuid' => 'nullable|uuid|exists:companies,uuid',
            'customer_name' => 'nullable|string|max:255',
            'value_before_tax' => 'nullable|numeric',
            'currency' => 'nullable|string|max:10',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'is_ongoing' => 'nullable|date',
            'remarks' => 'nullable|string',
        ];
    }

    public function attributes(): array
    {
        return [
            'customer_name' => 'Nombre del Cliente',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
