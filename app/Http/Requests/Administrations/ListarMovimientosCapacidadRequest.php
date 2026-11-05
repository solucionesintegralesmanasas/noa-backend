<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrations;

use App\Http\Requests\Procedure\Radicacion\ValidaEmpresaDelContexto;
use App\Models\CapacityMovement;
use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Consulta del libro mayor de cupos de capacidad transportadora (historial y
 * conciliación). La empresa consultada debe ser la del contexto de la petición
 * (SUPERADMIN opera en cualquiera); no muta nada.
 */
class ListarMovimientosCapacidadRequest extends FormRequest
{
    use HandlesApiResponse, ValidaEmpresaDelContexto;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->input('company_uuid')) {
            $actual = $this->attributes->get('current_company_uuid');
            if ($actual) {
                $this->merge(['company_uuid' => $actual]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'company_uuid' => ['nullable', 'uuid', 'exists:companies,uuid', $this->reglaEmpresaDelContexto()],
            'movement_type' => ['nullable', 'string', 'in:'.implode(',', [
                CapacityMovement::DESCUENTO_NUEVO,
                CapacityMovement::DESCUENTO_INGRESO,
                CapacityMovement::LIBERACION_DESVINCULACION,
                CapacityMovement::LIBERACION_SALIDA,
                CapacityMovement::AJUSTE_MANUAL,
            ])],
            'vehicle_uuid' => 'nullable|uuid|exists:vehicles,uuid',
            'desde' => 'nullable|date',
            'hasta' => 'nullable|date|after_or_equal:desde',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'company_uuid.uuid' => 'La empresa no es válida.',
            'company_uuid.exists' => 'La empresa no existe.',
            'movement_type.in' => 'El tipo de movimiento no es válido.',
            'vehicle_uuid.exists' => 'El vehículo no existe.',
            'desde.date' => 'La fecha inicial no es válida.',
            'hasta.date' => 'La fecha final no es válida.',
            'hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
            'per_page.integer' => 'El número de filas por página no es válido.',
            'per_page.max' => 'El número de filas por página no puede superar 100.',
            'page.integer' => 'El número de página no es válido.',
        ];
    }

    public function attributes(): array
    {
        return [
            'company_uuid' => 'Empresa',
            'movement_type' => 'Tipo de movimiento',
            'vehicle_uuid' => 'Vehículo',
            'desde' => 'Fecha inicial',
            'hasta' => 'Fecha final',
            'per_page' => 'Filas por página',
            'page' => 'Página',
        ];
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException($this->validationErrorResponse($validator->errors()->toArray()));
    }
}
