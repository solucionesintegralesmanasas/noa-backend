<?php

declare(strict_types=1);

namespace App\Http\Requests\Fleet\AffiliateAdminCharge;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateAffiliateAdminChargeRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_uuid' => 'sometimes|required|uuid|exists:companies,uuid',
            'payment_reference' => 'sometimes|required|string|max:50',
            'vehicle_uuid' => 'sometimes|required|uuid|exists:vehicles,uuid',
            'charge_type' => 'sometimes|required|in:CUOTA_ADMINISTRACION,PAGO_MENSUALIDAD,PAGO_CUPO',
            'concept' => 'sometimes|required|string|max:255',
            'amount' => 'sometimes|required|numeric|min:0',
            'currency_code' => 'sometimes|required|string|size:3',
            'period_date' => 'sometimes|required|date',
            'due_date' => 'sometimes|required|date',
            'next_payment_date' => 'sometimes|required|date|after_or_equal:due_date',
            'late_fee_percentage' => 'sometimes|required|numeric|min:0|max:100',
            'status' => 'sometimes|required|in:PENDIENTE,PAGADO,VENCIDO,EN_MORA,ANULADO',
            'payment_date' => 'nullable|date',
            'bank_reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
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
            'company_uuid' => 'empresa',
            'payment_reference' => 'referencia de pago',
            'vehicle_uuid' => 'vehículo',
            'charge_type' => 'tipo de cargo',
            'concept' => 'concepto',
            'amount' => 'monto',
            'currency_code' => 'código de moneda',
            'period_date' => 'fecha de periodo',
            'due_date' => 'fecha de vencimiento',
            'next_payment_date' => 'fecha del próximo pago',
            'late_fee_percentage' => 'porcentaje de mora',
            'status' => 'estado',
            'payment_date' => 'fecha de pago',
            'bank_reference' => 'referencia bancaria',
            'notes' => 'notes',
        ];
    }
}
