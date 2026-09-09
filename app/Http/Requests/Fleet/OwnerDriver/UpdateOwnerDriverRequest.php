<?php

declare(strict_types=1);

namespace App\Http\Requests\Fleet\OwnerDriver;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateOwnerDriverRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'driver_license_uuid' => 'sometimes|required|uuid|exists:driver_licenses,uuid',
            'third_party_uuid' => 'sometimes|required|uuid|exists:third_parties,uuid',
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
            'driver_license_uuid' => 'Licencia de conducción',
            'third_party_uuid' => 'Tercero',
        ];
    }
}
