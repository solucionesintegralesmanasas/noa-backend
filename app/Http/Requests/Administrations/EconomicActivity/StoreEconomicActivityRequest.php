<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrations\EconomicActivity;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Request para validar la creación de una Actividad Económica.
 */
class StoreEconomicActivityRequest extends FormRequest
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
            'activity_code' => 'required|string|max:20',
            'activity_description' => 'nullable|string|max:255',
            'is_main_activity' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'activity_code.required' => 'El código de la actividad económica es obligatorio.',
        ];
    }

    public function attributes(): array
    {
        return [
            'activity_code' => 'Código de Actividad',
            'activity_description' => 'Descripción de Actividad',
            'is_main_activity' => 'Actividad Principal',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
