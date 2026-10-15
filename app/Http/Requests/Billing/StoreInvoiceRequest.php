<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use App\Traits\HandlesApiResponse;
use Illuminate\Foundation\Http\FormRequest;

/** Creación de factura interna (fase 2, sin facturación electrónica). */
class StoreInvoiceRequest extends FormRequest
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
            'billing_resolution_uuid' => ['required', 'uuid', 'exists:billing_resolutions,uuid'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
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
            'billing_resolution_uuid.required' => 'La resolución de facturación es obligatoria.',
            'third_party_uuid.required' => 'El cliente es obligatorio.',
            'items.required' => 'La factura debe tener al menos un ítem.',
            'items.min' => 'La factura debe tener al menos un ítem.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'company_uuid' => 'Empresa',
            'billing_resolution_uuid' => 'Resolución',
            'third_party_uuid' => 'Cliente',
            'issue_date' => 'Fecha de emisión',
            'due_date' => 'Vencimiento',
            'items' => 'Ítems',
        ];
    }
}
