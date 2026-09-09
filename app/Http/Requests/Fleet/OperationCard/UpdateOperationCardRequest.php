<?php

declare(strict_types=1);

namespace App\Http\Requests\Fleet\OperationCard;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateOperationCardRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vehicle_uuid' => 'sometimes|required|uuid|exists:vehicles,uuid',
            'affiliated_company' => 'sometimes|required|string|max:255',
            'area_of_coverage' => 'sometimes|required|string|max:255',
            'service_type' => 'sometimes|required|string|max:255',
            'transport_mode' => 'sometimes|required|string|max:255',
            'issue_date' => 'sometimes|required|date',
            'expiration_date' => 'sometimes|required|date',
            'operating_card_number' => 'sometimes|required|string|max:20',
            'internal_number' => 'sometimes|nullable|string|max:50',
            'status' => 'sometimes|required|boolean',
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
            'vehicle_uuid' => 'Vehículo',
            'affiliated_company' => 'Empresa afiliada',
            'area_of_coverage' => 'Área de cobertura',
            'service_type' => 'Tipo de servicio',
            'transport_mode' => 'Modo de transporte',
            'issue_date' => 'Fecha de emisión',
            'expiration_date' => 'Fecha de expiración',
            'operating_card_number' => 'Número de tarjeta de operación',
            'status' => 'Estado',
        ];
    }
}
