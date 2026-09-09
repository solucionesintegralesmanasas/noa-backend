<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth\Permission;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Validador para la actualización de un permiso.
 *
 * Gestiona la unicidad compuesta ignorando el registro actual identificado por ID.
 *
 * @author   Darwin Montes
 *
 * @version  V 1.0.1
 *
 * @since    V 1.0.1
 *
 * @created  2025-05-31
 */
class UpdatePermissionRequest extends FormRequest
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
                Rule::unique('permissions')->where(function ($query) {
                    return $query->where('guard_name', $this->guard_name ?? $this->permission->guard_name);
                })->ignore($id, 'id'),
            ],
            'guard_name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'La combinación de nombre y guard ya está en uso por otro permiso.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre del permiso',
            'guard_name' => 'nombre del guard',
            'description' => 'descripción',
        ];
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
