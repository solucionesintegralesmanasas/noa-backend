<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request para enviar un mensaje al asistente.
 *
 * La sesión se identifica mediante el parámetro de ruta {uuid}.
 */
class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => 'required|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'El contenido del mensaje es obligatorio.',
            'content.max' => 'El mensaje no puede superar los 1000 caracteres.',
        ];
    }
}
