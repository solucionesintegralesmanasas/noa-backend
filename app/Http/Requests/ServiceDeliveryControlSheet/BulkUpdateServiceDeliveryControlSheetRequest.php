<?php

declare(strict_types=1);

namespace App\Http\Requests\ServiceDeliveryControlSheet;

use App\Http\Requests\Procedure\Radicacion\ValidaEmpresaDelContexto;
use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Actualización masiva de hojas de control abiertas por proyecto: permite
 * ampliar o ajustar las fechas (y opcionalmente el recorrido) de todas las
 * hojas abiertas de un proyecto en una sola operación.
 */
class BulkUpdateServiceDeliveryControlSheetRequest extends FormRequest
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
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
            'daily_route' => ['sometimes', 'nullable', 'string', 'max:255'],
            'vehicle_uuids' => ['sometimes', 'nullable', 'array'],
            'vehicle_uuids.*' => ['required', 'uuid', 'distinct', $this->reglaVehiculoDelContexto()],
        ];
    }

    public function messages(): array
    {
        return [
            'company_uuid.required' => 'El campo empresa es obligatorio.',
            'company_uuid.uuid' => 'El campo empresa debe ser un UUID válido.',
            'company_uuid.exists' => 'La empresa seleccionada no existe.',
            'project_uuid.required' => 'El campo proyecto es obligatorio para la actualización por proyecto.',
            'project_uuid.exists' => 'El proyecto seleccionado no existe.',
            'start_date.date' => 'La fecha de inicio debe ser una fecha válida.',
            'end_date.date' => 'El campo fecha de fin debe ser una fecha válida.',
            'end_date.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'daily_route.string' => 'La ruta diaria debe ser una cadena de texto.',
            'daily_route.max' => 'La ruta diaria no debe exceder los 255 caracteres.',
            'vehicle_uuids.array' => 'Los coches del proyecto deben llegar como una lista.',
            'vehicle_uuids.*.distinct' => 'Un coche no puede repetirse en la lista.',
        ];
    }

    public function attributes(): array
    {
        return [
            'company_uuid' => 'empresa',
            'project_uuid' => 'proyecto',
            'start_date' => 'fecha de inicio',
            'end_date' => 'fecha de fin',
            'daily_route' => 'ruta diaria',
            'vehicle_uuids' => 'coches del proyecto',
            'vehicle_uuids.*' => 'coche del proyecto',
        ];
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
