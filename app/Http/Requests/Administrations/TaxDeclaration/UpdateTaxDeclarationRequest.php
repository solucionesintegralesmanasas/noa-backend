<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrations\TaxDeclaration;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Request para validar la actualización de una Declaración de Renta.
 */
class UpdateTaxDeclarationRequest extends FormRequest
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
            'gross_assets' => 'nullable|numeric',
            'net_assets' => 'nullable|numeric',
            'total_gross_income' => 'nullable|numeric',
            'ordinary_net_income' => 'nullable|numeric',
            'pre_tax_net_profit' => 'nullable|numeric',
            'total_operating_non_operating_income' => 'nullable|numeric',
            'remarks' => 'nullable|string',
            'status' => 'nullable|in:BORRADOR,PRESENTADO',
        ];
    }

    public function attributes(): array
    {
        return [
            'fiscal_year' => 'Año Gravable',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
