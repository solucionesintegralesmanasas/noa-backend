<?php

declare(strict_types=1);

namespace App\Http\Requests\Fleet\Owner;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateOwnerRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'third_party_uuid' => 'nullable|uuid|exists:third_parties,uuid',
            'vehicle_uuid' => 'nullable|uuid|exists:vehicles,uuid',
            'document_type_uuid' => 'sometimes|required|uuid|exists:type_of_documents,uuid',
            'owner_name' => 'sometimes|required|string|max:255',
            'document_number' => 'sometimes|required|string|max:20',
            'verification_digit' => 'nullable|string|max:1',
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
            'third_party_uuid' => 'Tercero',
            'vehicle_uuid' => 'Vehículo',
            'document_type_uuid' => 'Tipo de documento',
            'owner_name' => 'Nombre del propietario',
            'document_number' => 'Número de documento',
            'verification_digit' => 'Dígito de verificación',
        ];
    }
}
