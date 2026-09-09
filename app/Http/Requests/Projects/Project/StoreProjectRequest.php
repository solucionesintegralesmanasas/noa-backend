<?php

declare(strict_types=1);

namespace App\Http\Requests\Projects\Project;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Solicitud de validación para la creación de un Proyecto.
 */
class StoreProjectRequest extends FormRequest
{
    use HandlesApiResponse;

    /**
     * Determina si el usuario está autorizado para realizar esta solicitud.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normaliza el payload en caso de venir en multipart (FormData),
     * donde las asignaciones llegan como una cadena JSON.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('assignments') && is_string($this->input('assignments'))) {
            $decoded = json_decode((string) $this->input('assignments'), true);
            $this->merge(['assignments' => is_array($decoded) ? $decoded : []]);
        }
    }

    /**
     * Obtiene las reglas de validación que se aplican a la solicitud.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'company_uuid' => ['required', 'uuid', 'exists:companies,uuid'],
            'project_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('projects')->where(function ($query) {
                    return $query->where('company_uuid', $this->company_uuid);
                }),
            ],
            'start_date' => ['required', 'date'],
            'completion_date' => ['required', 'date', 'after_or_equal:start_date'],
            'project_value' => ['nullable', 'numeric', 'min:0'],
            'purchase_order' => ['nullable', 'string', 'max:255'],
            'purchase_order_file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'assignments' => ['nullable', 'array'],
            'assignments.*.third_party_uuid' => [
                'required',
                'uuid',
                Rule::exists('third_parties', 'uuid')->where(fn ($query) => $query->where('is_driver', 1)),
            ],
            'assignments.*.is_active' => ['sometimes', 'boolean'],
            'assignments.*.vehicle_uuid' => [
                'required',
                'uuid',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $index = (int) str_replace(['assignments.', '.vehicle_uuid'], '', $attribute);
                    $assignment = $this->input('assignments')[$index] ?? [];
                    $isActive = filter_var($assignment['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN);

                    $exists = $isActive
                        ? \Illuminate\Support\Facades\DB::table('vehicles')
                            ->where('uuid', $value)
                            ->where('is_active', 1)
                            ->exists()
                        : \Illuminate\Support\Facades\DB::table('vehicles')
                            ->where('uuid', $value)
                            ->exists();

                    if (! $exists) {
                        $fail($isActive
                            ? 'Solo se pueden asignar vehículos existentes y activos.'
                            : 'El vehículo de una asignación retirada debe existir en la flota.');
                    }
                },
            ],
        ];
    }

    /**
     * Regla de integridad:
     *  - cada asignación siempre vincula un conductor con SU vehículo (1:1);
     *  - un conductor o un vehículo no pueden repetirse dentro del proyecto.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $assignments = $this->input('assignments') ?? [];

            if (count($assignments) === 0) {
                return;
            }

            $driversSeen = [];
            $vehiclesSeen = [];

            foreach ($assignments as $index => $assignment) {
                $driver = (string) ($assignment['third_party_uuid'] ?? '');
                $vehicle = (string) ($assignment['vehicle_uuid'] ?? '');

                if ($driver !== '' && isset($driversSeen[$driver])) {
                    $validator->errors()->add("assignments.{$index}.third_party_uuid", 'El conductor ya está asignado a este proyecto.');
                }
                $driversSeen[$driver] = true;

                if ($vehicle !== '' && isset($vehiclesSeen[$vehicle])) {
                    $validator->errors()->add("assignments.{$index}.vehicle_uuid", 'El vehículo ya está asignado a este proyecto.');
                }
                $vehiclesSeen[$vehicle] = true;
            }
        });
    }

    /**
     * Maneja un intento de validación fallido.
     */
    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }

    /**
     * Obtiene los mensajes de error personalizados para las reglas de validación definidas.
     */
    public function messages(): array
    {
        return [
            'project_name.unique' => 'Ya existe un proyecto con este nombre en la empresa seleccionada.',
            'completion_date.after_or_equal' => 'La fecha de finalización debe ser posterior o igual a la fecha de inicio.',
            'purchase_order_file.mimes' => 'La orden de compra debe ser un archivo PDF.',
            'purchase_order_file.max' => 'La orden de compra no debe superar los 10 MB.',
            'assignments.*.third_party_uuid.required' => 'Cada asignación debe incluir un conductor.',
            'assignments.*.third_party_uuid.exists' => 'Solo se pueden asignar conductores activos (terceros tipo conductor).',
            'assignments.*.vehicle_uuid.required' => 'Cada conductor asignado debe incluir su vehículo.',
            'assignments.*.vehicle_uuid.exists' => 'Solo se pueden asignar vehículos existentes y activos.',
        ];
    }

    /**
     * Obtiene los nombres de los atributos de forma legible.
     */
    public function attributes(): array
    {
        return [
            'company_uuid' => 'Empresa',
            'project_name' => 'Nombre del proyecto',
            'start_date' => 'Fecha de inicio',
            'completion_date' => 'Fecha de finalización',
            'project_value' => 'Valor del proyecto',
            'purchase_order' => 'Orden de compra',
            'purchase_order_file' => 'Orden de compra',
            'assignments' => 'Asignaciones',
            'assignments.*.third_party_uuid' => 'Conductor',
            'assignments.*.vehicle_uuid' => 'Vehículo',
            'assignments.*.is_active' => 'Estado de la asignación',
        ];
    }
}