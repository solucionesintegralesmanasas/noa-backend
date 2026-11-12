<?php

declare(strict_types=1);

namespace App\Http\Requests\Procedure\ServiceProvisionContract;

use App\Http\Requests\Procedure\Radicacion\ValidaEmpresaDelContexto;
use App\Models\Procedure;
use App\Traits\HandlesApiResponse;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Petición de validación para la creación de un nuevo Contrato de Prestación.
 */
class StoreServiceProvisionContractRequest extends FormRequest
{
    use HandlesApiResponse, ValidaEmpresaDelContexto;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->prepararEmpresaDelContexto();
    }

    public function rules(): array
    {
        return [
            'company_uuid' => ['required', 'uuid', 'exists:companies,uuid', $this->reglaEmpresaDelContexto()],
            'procedure_uuid' => [
                'required',
                'uuid',
                'exists:procedures,uuid',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $companyUuid = $this->input('company_uuid') ?? $this->attributes->get('current_company_uuid');
                    if ($companyUuid && ! Procedure::where('company_uuid', $companyUuid)->where('uuid', $value)->exists()) {
                        $fail('El trámite no pertenece a la empresa activa.');
                    }
                },
            ],
            'vehicle_uuid' => 'nullable|uuid|exists:vehicles,uuid',
            'third_party_uuid' => 'nullable|uuid|exists:third_parties,uuid',
            'contract_number' => 'nullable|string|max:50',
            'type_of_action' => 'nullable|in:C,M,E',
            'contract_type' => 'nullable|in:1,2',
            'issue_date' => 'required|date',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'duration' => 'required|integer|min:1',
            'valuation_amount' => 'nullable|numeric|min:0',
            'coverage' => 'nullable|string|max:255',
            'object_description' => 'nullable|string',
            'status' => 'nullable|in:BORRADOR,PENDIENTE_FIRMA,FIRMADO,VENCIDO,CANCELADO',
            'document_hash' => 'nullable|string|max:128',
        ];
    }

    public function messages(): array
    {
        return [
            'procedure_uuid.required' => 'El trámite es obligatorio.',
            'procedure_uuid.exists' => 'El trámite seleccionado no existe.',
            'contract_number.required' => 'El número de contrato es obligatorio.',
            'contract_number.unique' => 'El número de contrato ya existe.',
            'issue_date.required' => 'La fecha de emisión es obligatoria.',
            'start_date.required' => 'La fecha de inicio es obligatoria.',
            'end_date.required' => 'La fecha de fin es obligatoria.',
            'end_date.after_or_equal' => 'La fecha de fin debe ser posterior o igual a la fecha de inicio.',
            'duration.required' => 'La duración es obligatoria.',
        ];
    }

    public function attributes(): array
    {
        return [
            'company_uuid' => 'Empresa',
            'procedure_uuid' => 'Trámite',
            'vehicle_uuid' => 'Vehículo',
            'third_party_uuid' => 'Tercero/Contratante',
            'contract_number' => 'Número de contrato',
            'issue_date' => 'Fecha de emisión',
            'start_date' => 'Fecha de inicio',
            'end_date' => 'Fecha de fin',
            'duration' => 'Duración (días)',
            'valuation_amount' => 'Valor de tasación',
            'coverage' => 'Cobertura',
            'object_description' => 'Descripción del objeto',
            'status' => 'Estado',
            'document_hash' => 'Hash del documento',
        ];
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException($this->validationErrorResponse($validator->errors()->toArray()));
    }
}