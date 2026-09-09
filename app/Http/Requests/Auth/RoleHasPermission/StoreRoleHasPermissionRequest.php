<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth\RoleHasPermission;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Validador para la creación de una asociación rol-permiso.
 *
 * @author   Darwin Montes
 *
 * @version  V 1.0.1
 *
 * @since    V 1.0.1
 *
 * @created  2025-05-31
 */
class StoreRoleHasPermissionRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'permission_id' => 'required|integer|exists:permissions,id',
            'role_id' => 'required|integer|exists:roles,id',
        ];
    }

    public function messages(): array
    {
        return [
            'permission_id.exists' => 'El permiso seleccionado no es válido.',
            'role_id.exists' => 'El rol seleccionado no es válido.',
        ];
    }

    public function attributes(): array
    {
        return [
            'permission_id' => 'ID del permiso',
            'role_id' => 'ID del rol',
        ];
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
