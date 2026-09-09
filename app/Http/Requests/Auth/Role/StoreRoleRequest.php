<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth\Role;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Validador para la creación de un nuevo rol.
 *
 * Implementa unicidad compuesta (name, guard_name).
 *
 * @author   Darwin Montes
 *
 * @version  V 1.0.1
 *
 * @since    V 1.0.1
 *
 * @created  2025-05-31
 */
class StoreRoleRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles')->where(fn ($query) => $query->where('guard_name', $this->guard_name)),
            ],
            'guard_name' => 'required|string|max:255',
            'company_uuid' => 'nullable|uuid',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Ya existe un rol con este nombre para el guard especificado.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre del rol',
            'guard_name' => 'nombre del guard',
            'company_uuid' => 'UUID de la empresa',
        ];
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
