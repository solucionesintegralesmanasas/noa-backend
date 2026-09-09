<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrations\CostCenter;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Request para validar la creación de un Centro de Costo.
 */
class StoreCostCenterRequest extends FormRequest
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
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'parent_code' => 'nullable|string|max:50',
            'level' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'company_uuid.required' => 'La empresa asociada es obligatoria.',
            'company_uuid.exists' => 'La empresa seleccionada no existe.',
            'code.required' => 'El código del centro de costo es obligatorio.',
            'name.required' => 'El nombre del centro de costo es obligatorio.',
        ];
    }

    public function attributes(): array
    {
        return [
            'company_uuid' => 'Empresa',
            'code' => 'Código',
            'name' => 'Nombre',
            'description' => 'Descripción',
            'parent_code' => 'Código Padre',
            'level' => 'Nivel',
            'is_active' => 'Activo',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
