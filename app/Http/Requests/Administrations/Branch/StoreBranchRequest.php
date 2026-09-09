<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrations\Branch;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Request para validar la creación de una Sucursal.
 */
class StoreBranchRequest extends FormRequest
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
            'name' => 'required|string|max:100',
            'address' => 'required|string|max:200',
            'municipality_uuid' => 'required|uuid|exists:cities,uuid',
            'is_primary' => 'nullable|boolean',
            'status' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la sucursal es obligatorio.',
            'address.required' => 'La dirección física es obligatoria.',
            'company_uuid.exists' => 'La empresa propietaria no existe.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Nombre Comercial',
            'address' => 'Dirección Física',
            'is_primary' => 'Sede Principal',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
