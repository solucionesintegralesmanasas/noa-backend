<?php

declare(strict_types=1);

namespace App\Http\Requests\ServiceDeliveryControlSheet;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateServiceDeliveryControlSheetRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_uuid' => ['sometimes', 'required', 'uuid', 'exists:companies,uuid'],
            'official_name_and_surname' => ['sometimes', 'nullable', 'string', 'max:255'],
            'service_date' => ['sometimes', 'required', 'date'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
            'daily_route' => ['sometimes', 'nullable', 'string', 'max:255'],
            'start_time' => ['sometimes', 'nullable', 'date_format:H:i:s'],
            'end_time' => ['sometimes', 'nullable', 'date_format:H:i:s'],
            'total_hours' => ['sometimes', 'nullable', 'date_format:H:i:s'],
            'starting_kilometer' => ['sometimes', 'nullable', 'string', 'max:10'],
            'ending_kilometer' => ['sometimes', 'nullable', 'string', 'max:10'],
            'number_of_tolls' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'total_toll_value' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'type_of_control_sheet' => ['sometimes', 'nullable', 'in:DIRECTO_CON_LA_EMPRESA,SUBCONTRATADO'],
            'is_active' => ['sometimes', 'nullable', 'boolean'],
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
            'end_date.date' => 'La fecha de fin debe ser una fecha válida.',
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
            'type_of_control_sheet.in' => 'El tipo de control de hoja debe ser DIRECTO_CON_LA_EMPRESA o SUBCONTRATADO.',
            'is_active.boolean' => 'El estado activo debe ser verdadero o falso.',
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
        ];
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
