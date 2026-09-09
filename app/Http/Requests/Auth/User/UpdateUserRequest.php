<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth\User;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Validador para la actualización de un usuario.
 *
 * Utiliza el UUID para ignorar el registro actual en validaciones de unicidad.
 *
 * @author   Darwin Montes
 *
 * @version  V 1.0.1
 *
 * @since    V 1.0.1
 *
 * @created  2025-05-31
 */
class UpdateUserRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $uuid = $this->route('uuid');

        return [
            'name' => 'sometimes|required|string|max:255',
            'email' => "sometimes|required|string|email|max:255|unique:users,email,{$uuid},uuid",
            'user_name' => 'nullable|string|max:255',
            'password' => 'sometimes|nullable|string|min:8|max:255',
            'verification_code' => 'nullable|string|max:255',
            'verification_code_expires_at' => 'nullable|date',
            'failed_login_attempts' => 'sometimes|integer|min:0',
            'locked_until' => 'nullable|date',
            'company_uuid' => 'nullable|uuid',
            'third_party_uuid' => 'nullable|uuid',
            'google_id' => 'nullable|string|max:255',
            'google_email' => 'nullable|string|email|max:255',
            'google_drive_refresh_token' => 'nullable|string|max:255',
            'status' => 'sometimes|integer|in:0,1',
            'roles' => 'sometimes|array',
            'roles.*' => 'string|exists:roles,name',
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Este correo electrónico ya se encuentra en uso por otro usuario.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'email' => 'correo electrónico',
            'user_name' => 'nombre de usuario',
            'password' => 'contraseña',
            'company_uuid' => 'empresa',
        ];
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
