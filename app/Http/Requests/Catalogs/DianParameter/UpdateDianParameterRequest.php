<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs\DianParameter;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Solicitud de validación para la actualización de un Parámetro DIAN existente.
 *
 * @author Darwin Montes
 *
 * @version 1.0.0
 */
class UpdateDianParameterRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $uuid = $this->route('uuid');

        return [
            'year' => ['nullable', 'integer', 'unique:dian_parameters,year,'.$uuid.',uuid'],
            'uvt' => ['nullable', 'numeric'],
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
            'year.unique' => 'Ya existen parámetros registrados para este año.',
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
