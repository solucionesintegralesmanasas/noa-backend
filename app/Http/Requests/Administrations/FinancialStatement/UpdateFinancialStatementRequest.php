<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrations\FinancialStatement;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Request para validar la actualización de un Estado Financiero.
 */
class UpdateFinancialStatementRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_uuid' => 'nullable|uuid|exists:companies,uuid',
            'fiscal_year' => 'nullable|integer',
            'currency' => 'nullable|string|max:10',
            'current_assets' => 'nullable|numeric',
            'inventory' => 'nullable|numeric',
            'total_assets' => 'nullable|numeric',
            'current_liabilities' => 'nullable|numeric',
            'financial_obligations' => 'nullable|numeric',
            'total_liabilities' => 'nullable|numeric',
            'retained_earnings' => 'nullable|numeric',
            'equity' => 'nullable|numeric',
            'operational_income' => 'nullable|numeric',
            'operating_profit_before_tax' => 'nullable|numeric',
            'net_income_period' => 'nullable|numeric',
            'depreciation_amortization' => 'nullable|numeric',
            'financial_expenses' => 'nullable|numeric',
            'remarks' => 'nullable|string',
        ];
    }

    public function attributes(): array
    {
        return [
            'fiscal_year' => 'Año Fiscal',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
