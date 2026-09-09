<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request para la creación de una nueva sesión de chat.
 */
class CreateSessionRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para hacer esta solicitud.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación.
     */
    public function rules(): array
    {
        return [
            'title' => 'nullable|string|max:255',
            'module' => 'nullable|in:transporte,facturacion,inventario,general',
        ];
    }

    /**
     * Mensajes de error personalizados.
     */
    public function messages(): array
    {
        return [
            'module.in' => 'El módulo seleccionado no es válido.',
        ];
    }
}
