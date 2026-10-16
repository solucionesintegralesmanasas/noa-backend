<?php

declare(strict_types=1);

namespace App\Http\Requests\Tracking;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * FormRequest para validar la creación/actualización de geocercas.
 *
 * @author Darwin Montes
 *
 * @version 1.0.0
 *
 * @created 2026-09-10
 */
class StoreGeofenceRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'geofence' => 'required|array',
            'geofence.name' => 'required|string|max:255',
            'geofence.description' => 'nullable|string',
            'geofence.type' => 'required|string|in:circle,polygon',
            'geofence.center_lat' => 'required_if:geofence.type,circle|nullable|numeric|between:-90,90',
            'geofence.center_lng' => 'required_if:geofence.type,circle|nullable|numeric|between:-180,180',
            'geofence.radius_meters' => 'required_if:geofence.type,circle|nullable|integer|min:10|max:50000',
            'geofence.polygon_points' => 'required_if:geofence.type,polygon|nullable|array|min:3',
            'geofence.polygon_points.*.lat' => 'required|numeric|between:-90,90',
            'geofence.polygon_points.*.lng' => 'required|numeric|between:-180,180',
            'geofence.alert_on_enter' => 'sometimes|boolean',
            'geofence.alert_on_exit' => 'sometimes|boolean',
            'geofence.max_speed_kmh' => 'nullable|integer|min:1|max:200',
            'geofence.is_active' => 'sometimes|boolean',
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
            'geofence.name' => 'nombre',
            'geofence.description' => 'descripción',
            'geofence.type' => 'tipo',
            'geofence.center_lat' => 'latitud central',
            'geofence.center_lng' => 'longitud central',
            'geofence.radius_meters' => 'radio en metros',
            'geofence.polygon_points' => 'puntos del polígono',
            'geofence.alert_on_enter' => 'alerta al ingresar',
            'geofence.alert_on_exit' => 'alerta al salir',
            'geofence.max_speed_kmh' => 'velocidad máxima',
            'geofence.is_active' => 'activo',
        ];
    }
}