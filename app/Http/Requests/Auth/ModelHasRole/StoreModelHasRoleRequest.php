<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth\ModelHasRole;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Validador para la creación de una asignación rol-modelo.
 *
 * @author   Darwin Montes
 *
 * @version  V 1.0.1
 *
 * @since    V 1.0.1
 *
 * @created  2025-05-31
 */
class StoreModelHasRoleRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role_id' => 'required|integer|exists:roles,id',
            'model_type' => 'required|string|max:255',
            'model_id' => 'required|integer',
        ];
    }

    public function attributes(): array
    {
        return [
            'role_id' => 'ID del rol',
            'model_type' => 'tipo de modelo',
            'model_id' => 'ID del modelo',
        ];
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
