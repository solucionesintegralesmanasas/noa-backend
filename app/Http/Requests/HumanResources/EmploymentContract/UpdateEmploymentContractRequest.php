<?php

declare(strict_types=1);

namespace App\Http\Requests\HumanResources\EmploymentContract;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateEmploymentContractRequest extends FormRequest
{
    use HandlesApiResponse;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'contract_type' => ['sometimes', 'required', 'string', 'in:TERMINO_FIJO,TERMINO_INDEFINIDO,OBRA_LABOR,PRESTACION_SERVICIOS,APRENDIZAJE'],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'base_salary' => ['sometimes', 'required', 'numeric', 'min:0'],
            'salary_type' => ['nullable', 'string', 'in:ORDINARIO,INTEGRAL'],
            'transport_subsidy_applies' => ['nullable', 'boolean'],
            'working_hours_per_week' => ['nullable', 'numeric', 'min:1', 'max:168'],
            'status' => ['nullable', 'string', 'in:ACTIVO,SUSPENDIDO,TERMINADO'],
            'termination_reason' => ['nullable', 'string', 'max:255', 'required_if:status,TERMINADO'],
        ];
    }

    /**
     * Maneja un intento de validación fallido.
     */
    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }

    /**
     * Obtiene los nombres de los atributos de forma legible.
     */
    public function attributes(): array
    {
        return [
            'contract_type' => 'Tipo de contrato',
            'start_date' => 'Fecha de inicio',
            'end_date' => 'Fecha de finalización',
            'base_salary' => 'Salario base',
            'salary_type' => 'Naturaleza salarial',
            'transport_subsidy_applies' => 'Aplica auxilio de transporte',
            'working_hours_per_week' => 'Horas semanales',
            'status' => 'Estado del contrato',
            'termination_reason' => 'Motivo de terminación',
        ];
    }
}
