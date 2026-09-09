<?php

declare(strict_types=1);

namespace App\Http\Requests\Procedure\FleetServiceContract;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Petición de validación para la creación de un nuevo ContratoGestionFlota.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class StoreFleetServiceContractRequest extends FormRequest
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
            'procedure_uuid' => 'required|uuid|exists:procedures,uuid',
            'item' => 'required|integer',
            'type_of_action' => 'required|in:C,M,E',
            'issue_date' => 'required|date',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'duration' => 'required|integer',
            'contract_type' => 'required|in:1,2',
            'contract_number' => 'required|string|max:50',
            'signature_validation' => 'nullable|string|max:5',
            'valuation_amount' => 'required|numeric',
        ];
    }

    /**
     * Obtiene los mensajes de error personalizados para las reglas definidas.
     */
    public function messages(): array
    {
        return [
            'procedure_uuid.required' => 'El trámite es obligatorio.',
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
