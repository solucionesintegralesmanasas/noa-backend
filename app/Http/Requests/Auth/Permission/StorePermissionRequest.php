<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth\Permission;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Validador para la creación de un nuevo permiso.
 *
 * Implementa una regla de unicidad compuesta sobre 'name' y 'guard_name'
 * conforme a las restricciones definidas en el esquema SQL.
 *
 * @author   Darwin Montes
 *
 * @version  V 1.0.1
 *
 * @since    V 1.0.1
 *
 * @created  2025-05-31
 */
class StorePermissionRequest extends FormRequest
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
                Rule::unique('permissions')->where(fn ($query) => $query->where('guard_name', $this->guard_name)),
            ],
            'guard_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del permiso es obligatorio.',
            'name.unique' => 'Ya existe un permiso con este nombre para el guard especificado.',
            'guard_name.required' => 'El nombre del guard es obligatorio.',
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
