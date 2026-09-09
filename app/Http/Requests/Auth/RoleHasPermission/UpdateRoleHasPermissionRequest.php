<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth\RoleHasPermission;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Validador para la actualización de una asociación rol-permiso.
 *
 * Nota: En una tabla pivote pura, la actualización suele implicar el cambio de IDs.
 *
 * @author   Darwin Montes
 *
 * @version  V 1.0.1
 *
 * @since    V 1.0.1
 *
 * @created  2025-05-31
 */
class UpdateRoleHasPermissionRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'permission_id' => 'sometimes|required|integer|exists:permissions,id',
            'role_id' => 'sometimes|required|integer|exists:roles,id',
        ];
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
