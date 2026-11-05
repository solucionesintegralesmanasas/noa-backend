<?php

declare(strict_types=1);

namespace App\Http\Requests\Procedure\Radicacion;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Datos que se capturan antes de avanzar un paso: requisitos de renovación
 * (SIMIT, SOAT, RTM, tarjeta anterior), paz y salvo de la desvinculación y
 * referencia del pago de derechos. El pago es informativo: no bloquea el paso.
 */
class UpdatePasoRadicacionRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'simit_clear' => 'nullable|boolean',
            'soat_valid' => 'nullable|boolean',
            'rtm_valid' => 'nullable|boolean',
            'old_card_number' => 'nullable|string|max:20',
            'radicado_number' => 'nullable|string|max:50',
            'payment_status' => 'nullable|in:PENDIENTE,PAGADO,EXONERADO,NO_APLICA',
            'resolution_number' => 'nullable|string|max:50',
            'paz_y_salvo' => 'nullable|string|max:100',
            'subject' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'simit_clear.boolean' => 'El estado de SIMIT no es válido.',
            'soat_valid.boolean' => 'El estado del SOAT no es válido.',
            'rtm_valid.boolean' => 'El estado de la revisión técnico-mecánica no es válido.',
            'old_card_number.max' => 'El número de la tarjeta anterior no puede superar 20 caracteres.',
            'radicado_number.max' => 'El número de radicado no puede superar 50 caracteres.',
            'payment_status.in' => 'El estado del pago no es válido.',
            'resolution_number.max' => 'El número de resolución no puede superar 50 caracteres.',
            'paz_y_salvo.max' => 'La referencia del paz y salvo no puede superar 100 caracteres.',
            'subject.max' => 'El asunto no puede superar 255 caracteres.',
        ];
    }

    public function attributes(): array
    {
        return [
            'simit_clear' => 'SIMIT al día',
            'soat_valid' => 'SOAT vigente',
            'rtm_valid' => 'Revisión técnico-mecánica vigente',
            'old_card_number' => 'Número de la tarjeta anterior',
            'radicado_number' => 'Número de radicado',
            'payment_status' => 'Estado del pago de derechos',
            'resolution_number' => 'Número de resolución',
            'paz_y_salvo' => 'Paz y salvo',
            'subject' => 'Asunto',
        ];
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException($this->validationErrorResponse($validator->errors()->toArray()));
    }
}
