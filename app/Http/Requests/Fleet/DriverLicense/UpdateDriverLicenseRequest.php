<?php

declare(strict_types=1);

namespace App\Http\Requests\Fleet\DriverLicense;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateDriverLicenseRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'driver_license' => 'sometimes|required|array',
            'driver_license.third_party_uuid' => 'sometimes|required|uuid|exists:third_parties,uuid',
            'driver_license.number' => 'sometimes|required|string|max:50',
            'driver_license.category' => 'sometimes|required|in:A1,A2,B1,B2,B3,C1,C2,C3',
            'driver_license.issue_date' => 'sometimes|required|date',
            'driver_license.expiration_date' => 'sometimes|required|date',
            'driver_license.restrictions' => 'nullable|string|max:255',
            'driver_license.status' => 'sometimes|required|in:ACTIVA,SUSPENDIDA,VENCIDA,CANCELADA',
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
            'driver_license' => 'Licencia de conducción',
            'driver_license.third_party_uuid' => 'Conductor',
            'driver_license.number' => 'Número de licencia',
            'driver_license.category' => 'Categoría',
            'driver_license.issue_date' => 'Fecha de expedición',
            'driver_license.expiration_date' => 'Fecha de expiración',
            'driver_license.restrictions' => 'Restricciones',
            'driver_license.status' => 'Estado',
        ];
    }
}
