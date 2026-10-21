<?php

declare(strict_types=1);

namespace App\Http\Requests\Fleet\FuelLog;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreFuelLogRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_uuid' => 'nullable|uuid|exists:companies,uuid',
            'vehicle_uuid' => 'required|uuid|exists:vehicles,uuid',
            'driver_uuid' => 'nullable|uuid|exists:third_parties,uuid',
            'date' => 'required|date',
            'initial_odometer' => 'nullable|integer|min:0',
            'final_odometer' => 'nullable|integer|min:0',
            // Puede ser 0 en un día sin tanqueo; el precio por galón solo aplica si hay gasto.
            'total_spent' => 'required|numeric|min:0',
            'fuel_price_per_gallon' => 'nullable|numeric|min:0',
            'average_performance' => 'nullable|numeric|min:0',
            'idle_hours' => 'nullable|numeric|min:0',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $gasto = (float) $this->input('total_spent', 0);
            $precio = (float) $this->input('fuel_price_per_gallon', 0);

            if ($gasto > 0 && $precio <= 0) {
                $v->errors()->add('fuel_price_per_gallon', 'Indique el valor por galón para calcular los galones.');
            }

            $ini = $this->input('initial_odometer');
            $fin = $this->input('final_odometer');
            if ($ini !== null && $fin !== null && (int) $fin <= (int) $ini) {
                $v->errors()->add('final_odometer', 'El kilometraje final debe ser mayor al inicial.');
            }
        });
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
            'vehicle_uuid' => 'Vehículo',
            'driver_uuid' => 'Conductor',
            'date' => 'Fecha',
            'initial_odometer' => 'Kilometraje inicial',
            'final_odometer' => 'Kilometraje final',
            'total_spent' => 'Total tanqueo',
            'fuel_price_per_gallon' => 'Valor por galón',
            'average_performance' => 'Rendimiento promedio',
        ];
    }
}
