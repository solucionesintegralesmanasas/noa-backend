<?php

declare(strict_types=1);

namespace App\Http\Requests\Tracking;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * FormRequest para validar la recepción de ubicación GPS.
 *
 * @author Darwin Montes
 *
 * @version 1.0.0
 *
 * @created 2026-09-10
 */
class StoreLocationRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'location' => 'required|array',
            'location.latitude' => 'required|numeric|between:-90,90',
            'location.longitude' => 'required|numeric|between:-180,180',
            'location.altitude' => 'nullable|numeric',
            'location.speed' => 'required|numeric|min:0',
            'location.heading' => 'nullable|numeric|between:0,360',
            'location.accuracy' => 'nullable|numeric|min:0',
            'location.battery_level' => 'nullable|integer|between:0,100',
            'location.is_moving' => 'sometimes|boolean',
            'location.source' => 'sometimes|string|in:gps,network,fused',
            'location.recorded_at' => 'required|date',
            'location.vehicle_uuid' => 'nullable|uuid|exists:vehicles,uuid',
            'location.project_uuid' => 'nullable|uuid|exists:projects,uuid',
            'session_uuid' => 'nullable|uuid|exists:driver_location_sessions,uuid',
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
            'location.latitude' => 'latitud',
            'location.longitude' => 'longitud',
            'location.altitude' => 'altitud',
            'location.speed' => 'velocidad',
            'location.heading' => 'dirección',
            'location.accuracy' => 'precisión',
            'location.battery_level' => 'nivel de batería',
            'location.is_moving' => 'en movimiento',
            'location.source' => 'fuente de ubicación',
            'location.recorded_at' => 'fecha y hora de grabación',
            'location.vehicle_uuid' => 'vehículo',
            'location.project_uuid' => 'proyecto',
            'session_uuid' => 'sesión de tracking',
        ];
    }
}