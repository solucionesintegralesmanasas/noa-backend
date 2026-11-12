<?php

declare(strict_types=1);

namespace App\Http\Requests\Procedure\FleetServiceContract;

use App\Models\FleetServiceContract;
use App\Traits\HandlesApiResponse;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;

/**
 * Petición de validación para la actualización de un ContratoGestionFlota existente.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class UpdateFleetServiceContractRequest extends FormRequest
{
    use HandlesApiResponse;

    /**
     * Determina si el usuario está autorizado para realizar esta petición.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Obtiene las reglas de validación que se aplicarán a la petición.
     */
    public function rules(): array
    {
        return [
            'procedure_uuid' => 'nullable|uuid|exists:procedures,uuid',
            'item' => 'nullable|integer',
            'type_of_action' => 'nullable|in:C,M,E',
            'issue_date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'end_date' => [
                'nullable',
                'date',
                'after_or_equal:start_date',
                'after_or_equal:today',
                // Regla RUNT del contrato de vinculación: vigencia máxima de 24 meses.
                function (string $attribute, mixed $value, Closure $fail): void {
                    $inicio = $this->input('start_date');
                    if (! $inicio) {
                        $inicio = FleetServiceContract::where('uuid', $this->route('uuid'))->value('start_date');
                    }
                    if ($inicio && Carbon::parse((string) $value)->gt(Carbon::parse((string) $inicio)->addMonthsNoOverflow(24))) {
                        $fail('El contrato de vinculación no puede superar 24 meses (2 años).');
                    }
                },
            ],
            'duration' => 'nullable|integer',
            'contract_type' => 'nullable|in:1,2',
            'contract_number' => 'nullable|string|max:50',
            'signature_validation' => 'nullable|string|max:5',
            'valuation_amount' => 'nullable|numeric',
        ];
    }

    /**
     * Obtiene los mensajes de error personalizados para las reglas definidas.
     */
    public function messages(): array
    {
        return [
            'procedure_uuid.exists' => 'El trámite seleccionado no existe.',
            'type_of_action.in' => 'El tipo de acción debe ser C, M o E.',
            'contract_type.in' => 'El tipo de contrato debe ser 1 o 2.',
            'end_date.after_or_equal' => 'La fecha de fin debe ser posterior o igual a la fecha de inicio.',
        ];
    }

    /**
     * Obtiene los atributos personalizados para los errores de validación.
     */
    public function attributes(): array
    {
        return [
            'procedure_uuid' => 'Trámite asociado',
            'item' => 'Item del contrato',
            'type_of_action' => 'Tipo de acción',
            'issue_date' => 'Fecha de expedición',
            'start_date' => 'Fecha de inicio',
            'end_date' => 'Fecha de fin',
            'duration' => 'Duración del contrato',
            'contract_type' => 'Tipo de contrato',
            'contract_number' => 'Número de contrato',
            'signature_validation' => 'Validación de firma',
            'valuation_amount' => 'Valor de tasación',
        ];
    }

    /**
     * Maneja un intento de validación fallido.
     */
    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
