<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Validación de los pasos de configuración básica de ApiDian (sección 01).
 * El formato de cada paso difiere, así que las reglas se eligen por acción.
 */
class ConfiguracionApiDianRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $accion = last(explode('.', (string) $this->route()?->getName()));

        return match ($accion) {
            'guardar' => [
                'identification_number' => ['required', 'integer'],
                'dv' => ['nullable', 'string', 'max:1'],
                'name' => ['required', 'string', 'max:200'],
                'graphic_representation_name' => ['nullable', 'string', 'max:200'],
                'registration_code' => ['nullable', 'string', 'max:50'],
                'economic_activity' => ['nullable', 'string', 'max:10'],
                'phone' => ['nullable', 'string', 'max:20'],
                'address' => ['nullable', 'string', 'max:200'],
                'email' => ['nullable', 'email', 'max:100'],
                'merchant_registration' => ['nullable', 'string', 'max:50'],
                'type_document_identification_id' => ['required', 'integer'],
                'type_organization_id' => ['required', 'integer'],
                'type_liability_id' => ['required', 'integer'],
                'type_regime_id' => ['required', 'integer'],
                'type_environment_id' => ['required', 'integer', 'in:1,2'],
                'payroll_type_environment_id' => ['nullable', 'integer', 'in:1,2'],
                'eqdocs_type_environment_id' => ['nullable', 'integer', 'in:1,2'],
                'municipality_id' => ['required', 'integer'],
                'municipality_name_facturation' => ['nullable', 'string', 'max:100'],
                'municipality_name_eventos' => ['nullable', 'string', 'max:100'],
                'postal_zone_code' => ['nullable', 'string', 'max:10'],
            ],
            'software' => [
                'id' => ['required', 'string', 'max:100'],
                'pin' => ['required', 'string', 'max:50'],
                'url' => ['nullable', 'string', 'max:200'],
            ],
            'software-nomina' => [
                'idpayroll' => ['required', 'string', 'max:100'],
                'pinpayroll' => ['required', 'string', 'max:50'],
                'send' => ['sometimes', 'boolean'],
            ],
            'certificado' => [
                'certificate' => ['required', 'string'],
                'password' => ['required', 'string', 'max:255'],
            ],
            'resoluciones' => [
                'type_document_id' => ['required', 'integer', 'in:1,4,5'],
                'prefix' => ['required', 'string', 'max:10'],
                'resolution' => ['nullable', 'string', 'max:50'],
                'resolution_date' => ['nullable', 'date'],
                'technical_key' => ['nullable', 'string', 'max:100'],
                'from_number' => ['required', 'integer', 'min:1'],
                'to_number' => ['required', 'integer', 'gte:from_number'],
                'date_from' => ['required', 'date'],
                'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            ],
            'ambiente' => [
                'type_environment_id' => ['required', 'integer', 'in:1,2'],
                'payroll_type_environment_id' => ['nullable', 'integer', 'in:1,2'],
                'eqdocs_type_environment_id' => ['nullable', 'integer', 'in:1,2'],
                'test_set_id' => ['nullable', 'string', 'max:100'],
            ],
            'webhook' => [
                'webhook_url' => ['nullable', 'url', 'max:200'],
                'webhook_secret' => ['nullable', 'string', 'max:255'],
            ],
            default => [],
        };
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'identification_number' => 'NIT',
            'name' => 'Razón social',
            'municipality_id' => 'Municipio',
            'type_environment_id' => 'Ambiente',
            'type_document_identification_id' => 'Tipo de documento',
            'type_organization_id' => 'Tipo de organización',
            'type_liability_id' => 'Responsabilidad tributaria',
            'type_regime_id' => 'Régimen',
            'prefix' => 'Prefijo',
            'from_number' => 'Desde',
            'to_number' => 'Hasta',
            'date_from' => 'Vigencia desde',
            'date_to' => 'Vigencia hasta',
            'certificate' => 'Certificado',
            'password' => 'Clave del certificado',
        ];
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
