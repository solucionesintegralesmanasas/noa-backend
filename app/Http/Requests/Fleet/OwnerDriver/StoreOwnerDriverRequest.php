<?php

declare(strict_types=1);

namespace App\Http\Requests\Fleet\OwnerDriver;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreOwnerDriverRequest extends FormRequest
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
            'driver_license_uuid' => 'required|uuid|exists:driver_licenses,uuid',
            'third_party_uuid' => 'required|uuid|exists:third_parties,uuid',
        ];
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }

    public function attributes(): array
    {
        return [
            'company_uuid' => 'Empresa',
            'driver_license_uuid' => 'Licencia de conducción',
            'third_party_uuid' => 'Tercero',
        ];
    }
}
