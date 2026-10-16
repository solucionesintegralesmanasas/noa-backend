<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use App\Traits\HandlesApiResponse;
use Illuminate\Foundation\Http\FormRequest;

/** Creación de nota crédito o débito interna. */
class StoreNoteRequest extends FormRequest
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
            'invoice_uuid' => ['required', 'uuid', 'exists:commercial_invoices,uuid'],
            'correction_concept_code' => ['required', 'string', 'max:5'],
            'observation' => ['nullable', 'string', 'max:250'],
            'issue_date' => ['required', 'date'],
            'payment_method_uuid' => ['nullable', 'uuid', 'exists:payment_methods,uuid'],
        ], $this->reglasItems());
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'invoice_uuid.required' => 'La factura afectada es obligatoria.',
            'correction_concept_code.required' => 'El concepto de corrección DIAN es obligatorio.',
            'items.required' => 'La nota debe tener al menos un ítem.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['invoice_uuid' => 'Factura', 'issue_date' => 'Fecha de emisión', 'items' => 'Ítems'];
    }
}
