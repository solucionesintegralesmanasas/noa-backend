<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/** Reglas compartidas de ítems comerciales (cotización, factura y notas). */
trait ValidaItemsComerciales
{
    /** @return array<string, mixed> */
    public function reglasItems(string $prefijo = 'items', bool $requeridos = true): array
    {
        return [
            $prefijo => [$requeridos ? 'required' : 'sometimes', 'array', 'min:1'],
            $prefijo.'.*.description' => ['required', 'string', 'max:500'],
            $prefijo.'.*.measurement_unit_uuid' => ['nullable', 'uuid', 'exists:measurement_units,uuid'],
            $prefijo.'.*.quantity' => ['nullable', 'numeric', 'min:0'],
            $prefijo.'.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            $prefijo.'.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            $prefijo.'.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            $prefijo.'.*.tax_type_uuid' => ['nullable', 'uuid', 'exists:tax_types,uuid'],
            $prefijo.'.*.tribute_uuid' => ['nullable', 'uuid', 'exists:tributes,uuid'],
            $prefijo.'.*.fuec_uuid' => ['nullable', 'uuid'],
            $prefijo.'.*.control_sheet_uuid' => ['nullable', 'uuid'],
            $prefijo.'.*.vehicle_uuid' => ['nullable', 'uuid', 'exists:vehicles,uuid'],
            $prefijo.'.*.withholdings' => ['nullable', 'array'],
            $prefijo.'.*.withholdings.*.withholding_uuid' => ['nullable', 'uuid', 'exists:withholdings,uuid'],
            $prefijo.'.*.withholdings.*.withholding_type' => ['required_with:'.$prefijo.'.*.withholdings', 'string', 'max:50'],
            $prefijo.'.*.withholdings.*.withholding_rate' => ['required_with:'.$prefijo.'.*.withholdings', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
