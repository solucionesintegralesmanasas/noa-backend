<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrations\EnablingResolution;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Request para validar la actualización de una Resolución de Habilitación.
 */
class UpdateEnablingResolutionRequest extends FormRequest
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
            'resolution_number' => 'nullable|string|max:20',
            'number_fuec' => 'nullable|string|max:20',
            'territorial_code' => 'nullable|string|max:20',
            'resolution_date' => 'nullable|date',
            'status' => 'nullable|boolean',
        ];
    }

    public function attributes(): array
    {
        return [
            'resolution_number' => 'Número de Resolución',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
