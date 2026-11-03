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
            'filter_type' => 'nullable|in:'.implode(',', VehicleReportService::FILTER_TYPES),
            'third_party_uuid' => 'nullable|uuid|exists:third_parties,uuid',
            'project_uuid' => 'nullable|uuid|exists:projects,uuid',
            'branch_uuid' => 'nullable|uuid|exists:branches,uuid',
            'ownership_type' => 'nullable|in:todos,propios,terceros',
            'document_type' => 'nullable|string|in:TODOS,ALL,SOAT,RCC,RCE,RTM,todos,all,soat,rcc,rce,rtm',
            'doc_status' => 'nullable|in:vigente,vencido,por_vencer,hoy,por_vencer_5d,mas_15d',
            'agreement_status' => 'nullable|in:vigente,vencido,por_vencer,hoy,por_vencer_5d,mas_15d',
            'affiliated_company' => 'nullable|string|max:200',
            'contracting_entity_name' => 'nullable|string|max:200',
            'operation_card_status' => 'nullable|in:vigente,vencida,por_vencer,0,1,hoy,por_vencer_5d,mas_15d',
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
            'branch_uuid' => 'Sede operativa',
            'ownership_type' => 'Tipo de vinculación / propiedad',
            'document_type' => 'Tipo de documento',
            'doc_status' => 'Estado del documento',
            'agreement_status' => 'Estado del convenio',
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
