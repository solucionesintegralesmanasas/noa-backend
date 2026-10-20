<?php

declare(strict_types=1);

namespace App\Http\Requests\Procedure\Radicacion;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Consulta del siguiente código de trámite libre para una empresa.
 *
 * La empresa que se consulta debe ser la del contexto de la petición (SUPERADMIN
 * opera en cualquiera): antes un usuario podía pasar `?company_uuid=` de otra
 * empresa y obtener un código calculado fuera de su alcance.
 */
class SiguienteCodigoRadicacionRequest extends FormRequest
{
    use HandlesApiResponse, ValidaEmpresaDelContexto;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // El formulario no envía empresa: se usa la activa de la petición.
        if (! $this->input('company_uuid')) {
            $actual = $this->attributes->get('current_company_uuid');
            if ($actual) {
                $this->merge(['company_uuid' => $actual]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'company_uuid' => ['nullable', 'uuid', 'exists:companies,uuid', $this->reglaEmpresaDelContexto()],
        ];
    }

    public function messages(): array
    {
        return [
            'company_uuid.uuid' => 'La empresa no es válida.',
            'company_uuid.exists' => 'La empresa no existe.',
        ];
    }

    public function attributes(): array
    {
        return ['company_uuid' => 'Empresa'];
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException($this->validationErrorResponse($validator->errors()->toArray()));
    }
}
