<?php

declare(strict_types=1);

namespace App\Http\Requests\Procedure\Radicacion;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Consulta informativa del cupo disponible para radicar un expediente.
 *
 * La empresa consultada debe ser la del contexto de la petición (SUPERADMIN
 * opera en cualquiera). No muta nada: el descuento ocurre al cerrar el paso
 * CAPACIDAD_TRANSPORTADORA.
 */
class CuposDisponiblesRequest extends FormRequest
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
            'vehicle_uuid' => 'nullable|uuid|exists:vehicles,uuid',
            'link_type' => 'nullable|in:NUEVO_VEHICULO,CAMBIO_DE_EMPRESA,RENOVACION,DESVINCULACION_MUTUO,DESVINCULACION_UNILATERAL',
        ];
    }

    public function messages(): array
    {
        return [
            'company_uuid.uuid' => 'La empresa no es válida.',
            'company_uuid.exists' => 'La empresa no existe.',
            'vehicle_uuid.exists' => 'El vehículo no existe.',
            'link_type.in' => 'El tipo de vinculación no es válido.',
        ];
    }

    public function attributes(): array
    {
        return [
            'company_uuid' => 'Empresa',
            'vehicle_uuid' => 'Vehículo',
            'link_type' => 'Tipo de vinculación',
        ];
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException($this->validationErrorResponse($validator->errors()->toArray()));
    }
}
