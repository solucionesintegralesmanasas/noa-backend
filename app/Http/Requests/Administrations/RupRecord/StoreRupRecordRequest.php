<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrations\RupRecord;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Request para validar la creación de un Registro RUP.
 */
class StoreRupRecordRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_uuid' => 'required|uuid|exists:companies,uuid',
            'registration_number' => 'required|string|max:50',
            'issue_date' => 'nullable|date',
            'expiration_date' => 'nullable|date',
            'legal_capacity_score' => 'nullable|numeric',
            'financial_capacity_score' => 'nullable|numeric',
            'organizational_capacity_score' => 'nullable|numeric',
            'contracting_capacity_score' => 'nullable|numeric',
            'rup_certificate_path' => 'nullable|string|max:255',
            'status' => 'nullable|in:VIGENTE,VENCIDO,RENOVADO,CANCELADO,SUSPENDIDO,NO_INSCRITO',
            'remarks' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'registration_number.required' => 'El número de registro RUP es obligatorio.',
        ];
    }

    public function attributes(): array
    {
        return [
            'registration_number' => 'Número de Registro',
            'legal_capacity_score' => 'Capacidad Jurídica',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
