<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Filtros del reporte de actividad vehicular (rango de fechas obligatorio).
 */
class VehicleActivityReportRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha_desde' => 'required|date',
            'fecha_hasta' => 'required|date|after_or_equal:fecha_desde',
            'vehicle_uuid' => 'nullable|uuid',
            'project_uuid' => 'nullable|uuid',
            'per_page' => 'nullable|integer|min:1|max:50',
            'page' => 'nullable|integer|min:1',
        ];
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }

    public function attributes(): array
    {
        return [
            'fecha_desde' => 'Fecha desde',
            'fecha_hasta' => 'Fecha hasta',
            'vehicle_uuid' => 'Vehículo',
            'project_uuid' => 'Proyecto',
            'per_page' => 'Registros por página',
            'page' => 'Página',
        ];
    }

    /**
     * Filtros normalizados para el servicio.
     *
     * No se valida la pertenencia de vehículo/proyecto a la empresa: el
     * filtro solo acota lectura y cada brazo ya filtra por company_uuid.
     */
    public function reportFilters(?string $companyUuid = null): array
    {
        return array_merge($this->validated(), ['company_uuid' => $companyUuid]);
    }
}
