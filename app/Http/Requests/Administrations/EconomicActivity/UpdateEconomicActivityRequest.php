<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrations\EconomicActivity;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Request para validar la actualización de una Actividad Económica.
 */
class UpdateEconomicActivityRequest extends FormRequest
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
            'activity_code' => 'nullable|string|max:20',
            'activity_description' => 'nullable|string|max:255',
            'is_main_activity' => 'nullable|boolean',
        ];
    }

    public function attributes(): array
    {
        return [
            'activity_code' => 'Código de Actividad',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
