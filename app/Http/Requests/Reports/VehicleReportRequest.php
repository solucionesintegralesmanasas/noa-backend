<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use App\Services\Reports\VehicleReportService;
use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class VehicleReportRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'filter_type' => 'required|in:'.implode(',', VehicleReportService::FILTER_TYPES),
            'third_party_uuid' => 'nullable|uuid|exists:third_parties,uuid',
            'project_uuid' => 'nullable|uuid|exists:projects,uuid',
            'document_type' => 'nullable|in:SOAT,RCC,RCE,RTM',
            'doc_status' => 'nullable|in:vigente,vencido,por_vencer',
            'affiliated_company' => 'nullable|string|max:200',
            'contracting_entity_name' => 'nullable|string|max:200',
            'operation_card_status' => 'nullable|in:vigente,vencida,por_vencer,0,1',
            'operating_card_number' => 'nullable|string|max:50',
            'operation_card_expiry_from' => 'nullable|date',
            'operation_card_expiry_to' => 'nullable|date|after_or_equal:operation_card_expiry_from',
            'maintenance_status' => 'nullable|in:vencido,proximo_30d,pendiente',
            'maintenance_from' => 'nullable|date',
            'maintenance_to' => 'nullable|date|after_or_equal:maintenance_from',
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:1|max:50',
            'page' => 'nullable|integer|min:1',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $type = $this->input('filter_type');
            match ($type) {
                VehicleReportService::FILTER_AFFILIATE,
                VehicleReportService::FILTER_DRIVER => $this->requireField($validator, 'third_party_uuid'),
                VehicleReportService::FILTER_PROJECT => $this->requireField($validator, 'project_uuid'),
                VehicleReportService::FILTER_OPERATION_CARD => $this->requireField($validator, 'affiliated_company'),
                VehicleReportService::FILTER_AGREEMENT => $this->requireField($validator, 'contracting_entity_name'),
                default => null,
            };
        });
    }

    protected function requireField($validator, string $field): void
    {
        if (empty($this->input($field))) {
            $validator->errors()->add($field, "El campo {$field} es obligatorio para este tipo de filtro.");
        }
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
            'filter_type' => 'Tipo de filtro',
            'third_party_uuid' => 'Tercero',
            'project_uuid' => 'Proyecto',
            'document_type' => 'Tipo de documento',
            'doc_status' => 'Estado del documento',
            'operation_card_status' => 'Estado de la tarjeta de operación',
            'affiliated_company' => 'Empresa de la tarjeta de operación',
            'contracting_entity_name' => 'Convenio',
            'operating_card_number' => 'Número de tarjeta de operación',
            'maintenance_status' => 'Estado de mantenimiento',
            'search' => 'Búsqueda',
        ];
    }

    /**
     * Filtros normalizados para el servicio.
     */
    public function reportFilters(?string $companyUuid = null): array
    {
        return array_merge($this->validated(), ['company_uuid' => $companyUuid]);
    }
}
