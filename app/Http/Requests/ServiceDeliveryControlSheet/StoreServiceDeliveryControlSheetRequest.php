<?php

declare(strict_types=1);

namespace App\Http\Requests\ServiceDeliveryControlSheet;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreServiceDeliveryControlSheetRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_uuid' => ['required', 'uuid', 'exists:companies,uuid'],
            'official_name_and_surname' => ['nullable', 'string', 'max:255'],
            'service_date' => ['required', 'date'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'daily_route' => ['nullable', 'string', 'max:255'],
            'start_time' => ['nullable', 'date_format:H:i:s'],
            'end_time' => ['nullable', 'date_format:H:i:s'],
            'total_hours' => ['nullable', 'date_format:H:i:s'],
            'starting_kilometer' => ['nullable', 'string', 'max:10'],
            'ending_kilometer' => ['nullable', 'string', 'max:10'],
            'number_of_tolls' => ['nullable', 'integer', 'min:0'],
            'total_toll_value' => ['nullable', 'numeric', 'min:0'],
            'type_of_control_sheet' => ['nullable', 'in:DIRECTO_CON_LA_EMPRESA,SUBCONTRATADO,CON_VEHICULO_CONTRATADO,EXTERNO_PLATAFORMA'],
            'is_active' => ['nullable', 'boolean'],
            'vehicle_uuid' => ['nullable', 'uuid', 'exists:vehicles,uuid'],
            'third_party_uuid' => ['nullable', 'uuid', 'exists:third_parties,uuid'],
            'fuec_uuid' => ['nullable', 'uuid', 'exists:fuecs,uuid'],
            'vehicle_class_uuid' => ['nullable', 'uuid', 'exists:vehicle_class,uuid'],
            'vehicle_license_plate' => ['nullable', 'string', 'max:20'],
            'driver_name_and_surname' => ['nullable', 'string', 'max:255'],
            'driver_license_number' => ['nullable', 'string', 'max:20'],
            'project_uuid' => ['nullable', 'uuid', 'exists:projects,uuid'],
            'routes' => ['nullable', 'array'],
            'routes.*.origin' => ['nullable', 'string', 'max:255'],
            'routes.*.destination' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'company_uuid.required' => 'El campo empresa es obligatorio.',
            'company_uuid.uuid' => 'El campo empresa debe ser un UUID válido.',
            'company_uuid.exists' => 'La empresa seleccionada no existe.',
            'official_name_and_surname.string' => 'El nombre y apellido del funcionario debe ser una cadena de texto.',
            'official_name_and_surname.max' => 'El nombre y apellido del funcionario no debe exceder los 255 caracteres.',
            'service_date.required' => 'El campo fecha del servicio es obligatorio.',
            'service_date.date' => 'El campo fecha del servicio debe ser una fecha válida.',
            'start_date.date' => 'La fecha de inicio debe ser una fecha válida.',
            'end_date.date' => 'El campo fecha de fin debe ser una fecha válida.',
            'end_date.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'daily_route.string' => 'La ruta diaria debe ser una cadena de texto.',
            'daily_route.max' => 'La ruta diaria no debe exceder los 255 caracteres.',
            'start_time.date_format' => 'La hora de inicio debe tener el formato HH:MM:SS.',
            'end_time.date_format' => 'La hora final debe tener el formato HH:MM:SS.',
            'total_hours.date_format' => 'El total de horas debe tener el formato HH:MM:SS.',
            'starting_kilometer.string' => 'El kilometraje inicial debe ser una cadena de texto.',
            'starting_kilometer.max' => 'El kilometraje inicial no debe exceder los 10 caracteres.',
            'ending_kilometer.string' => 'El kilometraje final debe ser una cadena de texto.',
            'ending_kilometer.max' => 'El kilometraje final no debe exceder los 10 caracteres.',
            'number_of_tolls.integer' => 'El número de peajes debe ser un número entero.',
            'number_of_tolls.min' => 'El número de peajes no puede ser negativo.',
            'total_toll_value.numeric' => 'El valor total de peajes debe ser un número.',
            'total_toll_value.min' => 'El valor total de peajes no puede ser negativo.',
            'type_of_control_sheet.in' => 'El tipo de control de hoja debe ser DIRECTO_CON_LA_EMPRESA, SUBCONTRATADO, CON_VEHICULO_CONTRATADO o EXTERNO_PLATAFORMA.',
            'is_active.boolean' => 'El estado activo debe ser verdadero o falso.',
            'project_uuid.exists' => 'El proyecto seleccionado no existe.',
            'routes.array' => 'Los recorridos deben ser un array.',
            'routes.*.origin.string' => 'El origen debe ser una cadena de texto.',
            'routes.*.origin.max' => 'El origen no debe exceder los 255 caracteres.',
            'routes.*.destination.string' => 'El destino debe ser una cadena de texto.',
            'routes.*.destination.max' => 'El destino no debe exceder los 255 caracteres.',
        ];
    }

    public function attributes(): array
    {
        return [
            'company_uuid' => 'empresa',
            'official_name_and_surname' => 'nombre y apellido del funcionario',
            'service_date' => 'fecha del servicio',
            'start_date' => 'fecha de inicio',
            'end_date' => 'fecha de fin',
            'daily_route' => 'ruta diaria',
            'start_time' => 'hora de inicio',
            'end_time' => 'hora final',
            'total_hours' => 'total horas',
            'starting_kilometer' => 'kilometraje inicial',
            'ending_kilometer' => 'kilometraje final',
            'number_of_tolls' => 'número de peajes',
            'total_toll_value' => 'valor total peajes',
            'type_of_control_sheet' => 'tipo de control de hoja',
            'is_active' => 'estado activo',
            'project_uuid' => 'proyecto',
            'routes' => 'recorridos',
        ];
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}