<?php

declare(strict_types=1);

namespace App\Http\Requests\Fleet\SocialSecurityContribution;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreSocialSecurityContributionRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'third_party_uuid' => 'required|uuid|exists:third_parties,uuid',
            'billing_period' => 'required|date',
            'pila_pin' => 'required|string|max:50',
            'contribution_type' => 'sometimes|required|in:E,Y,I,S',
            'ibc_amount' => 'nullable|numeric',
            'health_paid' => 'sometimes|required|boolean',
            'pension_paid' => 'sometimes|required|boolean',
            'risk_labor_paid' => 'sometimes|required|boolean',
            'compensation_fund_paid' => 'sometimes|required|boolean',
            'eps_name' => 'nullable|string|max:100',
            'eps_affiliation_date' => 'nullable|date',
            'pension_name' => 'nullable|string|max:100',
            'pension_affiliation_date' => 'nullable|date',
            'risk_labor_name' => 'nullable|string|max:100',
            'risk_labor_affiliation_date' => 'nullable|date',
            'compensation_fund_name' => 'nullable|string|max:100',
            'compensation_fund_affiliation_date' => 'nullable|date',
            'payment_date' => 'nullable|date',
            'status' => 'sometimes|required|in:PENDIENTE,CUMPLIDO,EN MORA,PAGADO Y EN CURSO',
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
            'third_party_uuid' => 'Tercero',
            'billing_period' => 'Periodo de facturación',
            'pila_pin' => 'PIN de PILA',
            'contribution_type' => 'Tipo de aporte',
            'ibc_amount' => 'Valor del IBC',
            'health_paid' => 'Aporte a salud',
            'pension_paid' => 'Aporte a pensión',
            'risk_labor_paid' => 'Aporte a riesgos laborales',
            'compensation_fund_paid' => 'Aporte a fondo de compensación',
            'eps_name' => 'Nombre de la EPS',
            'eps_affiliation_date' => 'Fecha de afiliación EPS',
            'pension_name' => 'Nombre de la pensión',
            'pension_affiliation_date' => 'Fecha de afiliación Fondo de Pensión',
            'risk_labor_name' => 'Nombre del fondo de riesgos laborales',
            'risk_labor_affiliation_date' => 'Fecha de afiliación ARL',
            'compensation_fund_name' => 'Nombre del fondo de compensación',
            'compensation_fund_affiliation_date' => 'Fecha de afiliación Caja de Compensación',
            'payment_date' => 'Fecha de pago',
            'status' => 'Estado',
            'notes' => 'Notas adicionales',
        ];
    }
}
