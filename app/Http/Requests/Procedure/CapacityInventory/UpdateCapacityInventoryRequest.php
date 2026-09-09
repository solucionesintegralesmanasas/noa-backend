<?php

declare(strict_types=1);

namespace App\Http\Requests\Procedure\CapacityInventory;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Petición de validación para la actualización de un InventarioCapacidad existente.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class UpdateCapacityInventoryRequest extends FormRequest
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
            'company_uuid' => 'nullable|uuid|exists:companies,uuid',
            'conveyor_capacity_uuid' => 'nullable|uuid',
            'procedure_uuid' => 'nullable|uuid|exists:procedures,uuid',
            'used_conveyor_capacity' => 'nullable|integer',
            'used_operating_cards' => 'nullable|integer',
        ];
    }

    /**
     * Obtiene los mensajes de error personalizados para las reglas definidas.
     */
    public function messages(): array
    {
        return [
            'company_uuid.exists' => 'La empresa seleccionada no existe.',
            'procedure_uuid.exists' => 'El trámite seleccionado no existe.',
        ];
    }

    /**
     * Obtiene los atributos personalizados para los errores de validación.
     */
    public function attributes(): array
    {
        return [
            'company_uuid' => 'Empresa',
            'conveyor_capacity_uuid' => 'UUID de capacidad',
            'procedure_uuid' => 'Trámite',
            'used_conveyor_capacity' => 'Capacidad utilizada',
            'used_operating_cards' => 'Tarjetas de operación utilizadas',
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
