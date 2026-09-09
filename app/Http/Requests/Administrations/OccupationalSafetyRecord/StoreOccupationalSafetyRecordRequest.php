<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrations\OccupationalSafetyRecord;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Request para validar la creación de un Registro SST.
 */
class StoreOccupationalSafetyRecordRequest extends FormRequest
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
            'operation_year' => 'required|integer',
            'fatalities' => 'nullable|integer',
            'incapacitating_accidents_count' => 'nullable|integer',
            'total_incidents_count' => 'nullable|integer',
            'lost_days_count' => 'nullable|integer',
            'average_workers_count' => 'nullable|integer',
            'hours_worked' => 'nullable|numeric',
            'arl_accident_certificate_date' => 'nullable|date',
            'risk_level' => 'nullable|string|max:50',
            'arl_affiliation_certificate_date' => 'nullable|date',
            'sgsst_rating' => 'nullable|string|max:50',
            'sgsst_evaluation_date' => 'nullable|date',
            'sgsst_certificate_path' => 'nullable|string|max:255',
            'remarks' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'operation_year.required' => 'El año de operación es obligatorio.',
        ];
    }

    public function attributes(): array
    {
        return [
            'operation_year' => 'Año de Operación',
            'fatalities' => 'Fatalidades',
            'risk_level' => 'Nivel de Riesgo',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
