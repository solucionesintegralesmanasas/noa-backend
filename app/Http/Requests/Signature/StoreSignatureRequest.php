<?php

declare(strict_types=1);

namespace App\Http\Requests\Signature;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Class StoreSignatureRequest
 *
 * Valida que la firma llegue en formato PNG base64 y dentro del límite de peso.
 */
class StoreSignatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isUpdate = $this->isMethod('put') || $this->isMethod('patch');

        return [
            'entity_type' => $isUpdate ? ['nullable', 'string', 'in:vehicle,driver,contract,vehicle_inspection,vehicle_inspection_inspector,vehicle_inspection_coordinator'] : ['required', 'string', 'in:vehicle,driver,contract,vehicle_inspection,vehicle_inspection_inspector,vehicle_inspection_coordinator'],
            'entity_id' => $isUpdate ? ['nullable', 'integer', 'min:1'] : ['required', 'integer', 'min:1'],
            'role' => ['nullable', 'string', 'in:inspector,coordinator'],
            'company_uuid' => ['nullable', 'string', 'exists:companies,uuid'],
            'ip_address' => ['nullable', 'string', 'max:45'],
            'latitude' => ['nullable', 'string', 'max:255'],
            'longitude' => ['nullable', 'string', 'max:255'],
            'speed' => ['nullable', 'string', 'max:50'],
            'state' => ['nullable', 'string', 'in:DETENIDO,EN MOVIMIENTO'],

            // data:image/png;base64,{contenido}
            'signature' => [
                'required',
                'string',
                'regex:/^data:image\/png;base64,[A-Za-z0-9+\/]+=*$/',
                function (string $attribute, mixed $value, callable $fail): void {
                    [, $base64] = explode(',', $value, 2);
                    $sizeKb = (int) (strlen($base64) * 3 / 4 / 1024);

                    if ($sizeKb > 500) {
                        $fail("La firma no debe superar los 500 KB (actual: {$sizeKb} KB).");
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'signature.regex' => 'El formato de la firma no es válido. Se esperaba PNG en base64.',
            'entity_type.in' => 'El tipo de entidad ":input" no es válido.',
            'entity_id.integer' => 'El ID de entidad debe ser un entero.',
        ];
    }
}
