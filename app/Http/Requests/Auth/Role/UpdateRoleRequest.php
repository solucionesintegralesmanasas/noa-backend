<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth\Role;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Validador para la actualización de un rol.
 *
 * @author   Darwin Montes
 *
 * @version  V 1.0.1
 *
 * @since    V 1.0.1
 *
 * @created  2025-05-31
 */
class UpdateRoleRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('roles')->where(function ($query) use ($id) {
                    $guard = $this->input('guard_name') ?? \App\Models\Role::find($id)?->guard_name ?? 'api';
                    return $query->where('guard_name', $guard);
                })->ignore($id, 'id'),
            ],
            'guard_name' => 'sometimes|required|string|max:255',
            'company_uuid' => 'nullable|uuid',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'La combinación de nombre y guard ya está en uso por otro rol.',
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
