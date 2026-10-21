<?php

declare(strict_types=1);

namespace App\Http\Requests\Fleet\FuelLog;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateFuelLogRequest extends FormRequest
{
    use HandlesApiResponse, ValidaPertenenciaCombustible;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // La empresa del registro no se cambia desde el cuerpo: el servicio
            // conserva la del registro y el contexto solo acota qué se puede tocar.
            'vehicle_uuid' => ['sometimes', 'required', 'uuid', $this->reglaVehiculoDelContexto()],
            'driver_uuid' => ['sometimes', 'nullable', 'uuid', $this->reglaConductorDelContexto()],
            'date' => 'sometimes|required|date',
            'initial_odometer' => 'sometimes|nullable|integer|min:0',
            'final_odometer' => 'sometimes|nullable|integer|min:0',
            'total_spent' => 'sometimes|required|numeric|min:0',
            'fuel_price_per_gallon' => 'sometimes|nullable|numeric|min:0',
            'average_performance' => 'sometimes|nullable|numeric|min:0',
            'idle_hours' => 'sometimes|nullable|numeric|min:0',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $ini = $this->input('initial_odometer');
            $fin = $this->input('final_odometer');

            if ($ini !== null && $fin !== null && (int) $fin <= (int) $ini) {
                $v->errors()->add('final_odometer', 'El kilometraje final debe ser mayor al inicial.');
            }

            $gasto = (float) $this->input('total_spent', 0);
            $precio = (float) $this->input('fuel_price_per_gallon', 0);
            if ($gasto > 0 && $precio <= 0) {
                $v->errors()->add('fuel_price_per_gallon', 'Indique el valor por galón para calcular los galones.');
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
