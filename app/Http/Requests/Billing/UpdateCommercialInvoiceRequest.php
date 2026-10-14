<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use App\Traits\HandlesApiResponse;
use Illuminate\Foundation\Http\FormRequest;

/** Edición de documento comercial en BORRADOR. */
class UpdateCommercialInvoiceRequest extends FormRequest
{
    use HandlesApiResponse, ValidaItemsComerciales;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge([
            'issue_date' => ['sometimes', 'date'],
            'valid_until' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'project_uuid' => ['nullable', 'uuid', 'exists:projects,uuid'],
            'contractor_uuid' => ['nullable', 'uuid', 'exists:contractors,uuid'],
            'object_contract_uuid' => ['nullable', 'uuid', 'exists:objects_contracts,uuid'],
            'vehicle_uuid' => ['nullable', 'uuid', 'exists:vehicles,uuid'],
            'origin_route' => ['nullable', 'string', 'max:255'],
            'destination_route' => ['nullable', 'string', 'max:255'],
            'service_start' => ['nullable', 'date'],
            'service_end' => ['nullable', 'date'],
            'payment_method_uuid' => ['nullable', 'uuid', 'exists:payment_methods,uuid'],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
        ], $this->reglasItems('items', false));
    }
}
