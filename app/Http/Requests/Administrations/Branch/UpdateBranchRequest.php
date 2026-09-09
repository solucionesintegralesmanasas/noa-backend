<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrations\Branch;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Request para validar la actualización de una Sucursal.
 */
class UpdateBranchRequest extends FormRequest
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
            'name' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:200',
            'municipality_uuid' => 'nullable|uuid|exists:cities,uuid',
            'is_primary' => 'nullable|boolean',
            'status' => 'nullable|boolean',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Nombre Comercial',
            'address' => 'Dirección Física',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
