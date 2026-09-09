<?php

declare(strict_types=1);

namespace App\Http\Requests\ThirdParties\ThirdParty;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Solicitud de validación para la actualización de un Tercero existente.
 */
class UpdateThirdPartyRequest extends FormRequest
{
    use HandlesApiResponse;

    /**
     * Determina si el usuario está autorizado para realizar esta solicitud.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Obtiene las reglas de validación que se aplican a la solicitud.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $uuid = $this->route('uuid');

        return [
            'person_type' => ['sometimes', 'required', 'string', 'in:NATURAL,JURIDICA'],
            'document_type_uuid' => ['sometimes', 'required', 'uuid', 'exists:type_of_documents,uuid'],
            'document_number' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('third_parties')->where(function ($query) {
                    $record = DB::table('third_parties')->where('uuid', $this->route('uuid'))->first();
                    $companyUuid = $record->company_uuid ?? null;

                    return $query->where('company_uuid', $companyUuid)
                        ->where('document_type_uuid', $this->document_type_uuid ?? ($record->document_type_uuid ?? null));
                })->ignore($uuid, 'uuid'),
            ],
            'nit_check_digit' => ['nullable', 'string', 'max:1'],
            'trade_name' => ['nullable', 'string', 'max:200'],
            'company_name' => ['nullable', 'string', 'max:200'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['sometimes', 'required', 'email', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:200'],
            'municipality_uuid' => ['sometimes', 'required', 'uuid', 'exists:cities,uuid'],
            'tax_regime' => ['sometimes', 'required', 'string', 'in:48,49,47,05,42'],
            'tax_responsibility_uuid' => ['sometimes', 'required', 'uuid', 'exists:tax_responsibilities,uuid'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'bank_account_type' => ['nullable', 'string', 'in:AHORROS,CORRIENTE,MONEDA_EXTRANJERA'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'cost_center_uuid' => ['nullable'],
            'is_customer' => ['nullable', 'boolean'],
            'is_supplier' => ['nullable', 'boolean'],
            'is_employee' => ['nullable', 'boolean'],
            'is_affiliate' => ['nullable', 'boolean'],
            'is_driver' => ['nullable', 'boolean'],
            'is_others' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
            'affiliate_uuid' => ['nullable', 'uuid', 'exists:third_parties,uuid'],
            'license_number' => ['nullable', 'string', 'max:50'],
            'license_category' => ['nullable', 'string', 'in:C1,C2,C3'],
            'license_issue_date' => ['nullable', 'date'],
            'license_expiration_date' => ['nullable', 'date', 'after:license_issue_date'],
            'license_restrictions' => ['nullable', 'string', 'max:255'],
            'license_status' => ['nullable', 'string', 'in:ACTIVA,SUSPENDIDA,VENCIDA,CANCELADA'],
        ];
    }

    /**
     * Validación adicional: un tercero empleado solo puede tener un único tipo y un único rol.
     * Tipo y rol son independientes.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $isEmployee = filter_var($this->input('is_employee'), FILTER_VALIDATE_BOOLEAN) || $this->input('is_employee') === '1' || $this->input('is_employee') === 1;
            if ($isEmployee) {
                $otherFlags = ['is_customer', 'is_supplier', 'is_affiliate', 'is_driver', 'is_others'];
                foreach ($otherFlags as $flag) {
                    if ($this->has($flag)) {
                        $val = $this->input($flag);
                        if (filter_var($val, FILTER_VALIDATE_BOOLEAN) || $val === '1' || $val === 1) {
                            $v->errors()->add($flag, 'Un empleado solo puede tener el tipo Empleado. Desmarque los demás tipos.');
                        }
                    }
                }
                if ($this->has('roles')) {
                    $roles = array_values(array_filter((array) $this->input('roles', [])));
                    if (count($roles) > 1) {
                        $v->errors()->add('roles', 'Un empleado solo puede tener un único rol de acceso.');
                    }
                }
            }
        });
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
     * Obtiene los mensajes de error personalizados para las reglas de validación definidas.
     */
    public function messages(): array
    {
        return [
            'document_number.unique' => 'Ya existe un tercero registrado con este número de documento para la empresa y tipo de documento seleccionados.',
        ];
    }

    /**
     * Obtiene los nombres de los atributos de forma legible.
     */
    public function attributes(): array
    {
        return [
            'person_type' => 'Tipo de persona',
            'document_type_uuid' => 'Tipo de documento',
            'document_number' => 'Número de documento',
            'nit_check_digit' => 'Dígito de verificación',
            'trade_name' => 'Nombre comercial',
            'company_name' => 'Razón social',
            'first_name' => 'Nombres',
            'last_name' => 'Apellidos',
            'email' => 'Correo electrónico',
            'phone' => 'Teléfono',
            'address' => 'Dirección',
            'municipality_uuid' => 'Municipio',
            'tax_regime' => 'Régimen tributario',
            'tax_responsibility_uuid' => 'Responsabilidad fiscal',
            'bank_account_number' => 'Número de cuenta bancaria',
            'bank_account_type' => 'Tipo de cuenta bancaria',
            'bank_name' => 'Nombre del banco',
            'cost_center_uuid' => 'Centro de costo',
            'is_customer' => 'Es cliente',
            'is_supplier' => 'Es proveedor',
            'is_employee' => 'Es empleado',
            'is_affiliate' => 'Es afiliado',
            'is_driver' => 'Es conductor',
            'is_others' => 'Otros',
            'is_active' => 'Estado activo',
            'roles' => 'Roles de acceso',
            'affiliate_uuid' => 'Afiliado asignado',
            'license_number' => 'Número de licencia',
            'license_category' => 'Categoría de la licencia',
            'license_issue_date' => 'Fecha de expedición',
            'license_expiration_date' => 'Fecha de expiración',
            'license_restrictions' => 'Restricciones de la licencia',
            'license_status' => 'Estado de la licencia',
        ];
    }
}
