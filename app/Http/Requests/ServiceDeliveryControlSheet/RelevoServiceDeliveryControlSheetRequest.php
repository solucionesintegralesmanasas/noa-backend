<?php

declare(strict_types=1);

namespace App\Http\Requests\ServiceDeliveryControlSheet;

use App\Http\Requests\Procedure\Radicacion\ValidaEmpresaDelContexto;
use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Relevo de conductor sobre un vehículo de un proyecto.
 *
 * Reasigna el vehículo: activa al entrante y retira al saliente. No cierra ni
 * crea planillas. El entrante sale del catálogo de la empresa; si aún no está
 * en el proyecto, el relevo crea su asignación al vehículo.
 */
class RelevoServiceDeliveryControlSheetRequest extends FormRequest
{
    use HandlesApiResponse, ValidaEmpresaDelContexto, ValidaPertenenciaPcp;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->prepararEmpresaDelContexto();
    }

    public function rules(): array
    {
        return [
            'company_uuid' => ['required', 'uuid', 'exists:companies,uuid', $this->reglaEmpresaDelContexto()],
            'project_uuid' => ['required', 'uuid', $this->reglaProyectoDelContexto()],
            'vehicle_uuid' => ['required', 'uuid', $this->reglaVehiculoDelContexto()],
            'third_party_uuid' => ['required', 'uuid', $this->reglaTerceroDelContexto()],
        ];
    }

    public function messages(): array
    {
        return [
            'company_uuid.required' => 'El campo empresa es obligatorio.',
            'company_uuid.uuid' => 'El campo empresa debe ser un UUID válido.',
            'company_uuid.exists' => 'La empresa seleccionada no existe.',
            'project_uuid.required' => 'El campo proyecto es obligatorio para el relevo.',
            'project_uuid.uuid' => 'El proyecto debe ser un UUID válido.',
            'vehicle_uuid.required' => 'El campo vehículo es obligatorio para el relevo.',
            'vehicle_uuid.uuid' => 'El vehículo debe ser un UUID válido.',
            'third_party_uuid.required' => 'Selecciona el conductor que recibe el turno.',
            'third_party_uuid.uuid' => 'El conductor debe ser un UUID válido.',
        ];
    }

    public function attributes(): array
    {
        return [
            'company_uuid' => 'empresa',
            'project_uuid' => 'proyecto',
            'vehicle_uuid' => 'vehículo',
            'third_party_uuid' => 'conductor entrante',
        ];
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
