<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs\DianParameter;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Solicitud de validación para la creación de un nuevo Parámetro DIAN.
 *
 * @author Darwin Montes
 *
 * @version 1.0.0
 */
class StoreDianParameterRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'unique:dian_parameters,year'],
            'uvt' => ['required', 'numeric'],
            'iva_withholding_rate' => ['nullable', 'numeric'],
            'minimum_wage' => ['nullable', 'numeric'],
            'transport_subsidy' => ['nullable', 'numeric'],
            'usury_rate' => ['nullable', 'numeric'],
            'observations' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'year.required' => 'El año fiscal es obligatorio.',
            'year.unique' => 'Ya existen parámetros registrados para este año.',
            'uvt.required' => 'El valor de la UVT es obligatorio.',
        ];
    }

    public function attributes(): array
    {
        return [
            'year' => 'Año Fiscal',
            'uvt' => 'Valor UVT',
            'iva_withholding_rate' => 'Tasa Retención IVA',
            'minimum_wage' => 'Salario Mínimo',
            'transport_subsidy' => 'Auxilio Transporte',
            'usury_rate' => 'Tasa de Usura',
            'observations' => 'Observaciones',
        ];
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
