<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use App\Traits\HandlesApiResponse;
use Illuminate\Foundation\Http\FormRequest;

/** Creación de cotización (fase 1). */
class StoreQuotationRequest extends FormRequest
{
    use HandlesApiResponse, ValidaItemsComerciales;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Si el formulario no envía empresa, se usa la del contexto
     * (cabecera X-Company-UUID o primera empresa del usuario).
     */
    protected function prepareForValidation(): void
    {
        if (! $this->input('company_uuid')) {
            $this->merge(['company_uuid' => $this->attributes->get('current_company_uuid')]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge([
            'company_uuid' => ['required', 'uuid', 'exists:companies,uuid'],
            'branch_uuid' => ['nullable', 'uuid', 'exists:branches,uuid'],
            'issue_date' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'third_party_uuid' => ['required', 'uuid', 'exists:third_parties,uuid'],
            'project_uuid' => ['nullable', 'uuid', 'exists:projects,uuid'],
            'contractor_uuid' => ['nullable', 'uuid', 'exists:contractors,uuid'],
            'object_contract_uuid' => ['nullable', 'uuid', 'exists:objects_contracts,uuid'],
            'vehicle_uuid' => ['nullable', 'uuid', 'exists:vehicles,uuid'],
            'origin_route' => ['nullable', 'string', 'max:255'],
            'destination_route' => ['nullable', 'string', 'max:255'],
            'service_start' => ['nullable', 'date'],
            'service_end' => ['nullable', 'date', 'after_or_equal:service_start'],
            'payment_method_uuid' => ['nullable', 'uuid', 'exists:payment_methods,uuid'],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
        ], $this->reglasItems());
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'third_party_uuid.required' => 'El cliente es obligatorio.',
            'items.required' => 'La cotización debe tener al menos un ítem.',
            'items.min' => 'La cotización debe tener al menos un ítem.',
            'valid_until.after_or_equal' => 'La vigencia debe ser posterior o igual a la fecha de emisión.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'company_uuid' => 'Empresa',
            'third_party_uuid' => 'Cliente',
            'issue_date' => 'Fecha de emisión',
            'valid_until' => 'Vigencia',
            'items' => 'Ítems',
        ];
    }
}
