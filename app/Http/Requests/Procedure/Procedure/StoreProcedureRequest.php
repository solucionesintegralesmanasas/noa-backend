<?php

declare(strict_types=1);

namespace App\Http\Requests\Procedure\Procedure;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Petición de validación para la creación de un nuevo Tramite.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class StoreProcedureRequest extends FormRequest
{
    use HandlesApiResponse;

    /**
     * Determina si el usuario está autorizado para realizar esta petición.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Obtiene las reglas de validación que se aplicarán a la petición.
     */
    public function rules(): array
    {
        return [
            'link_type' => 'nullable|in:CAMBIO_DE_EMPRESA,NUEVO_VEHICULO',
            'company_uuid' => 'nullable|uuid|exists:companies,uuid',
            'third_party_uuid' => 'nullable|uuid|exists:third_parties,uuid',
            'vehicle_uuid' => 'nullable|uuid|exists:vehicles,uuid',
            'procedure_code' => 'required|string|max:50',
            'filed_number' => 'nullable|string|max:50',
            'procedure_type' => 'required|in:CARTA_DE_ACEPTACION,CAPACIDAD_TRANSPORTADORA,INCLUSION_DE_POLIZAS,TARJETA_DE_OPERACION,DESVINCULACION',
            'date_of_creation' => 'required|date',
            'city_uuid' => 'required|uuid|exists:cities,uuid',
            'subject' => 'nullable|string|max:255',
            'territorial_director_uuid' => 'required|uuid|exists:territorial_directors,uuid',
            'status' => 'nullable|in:RECIBIDO,EN_PROCESO,COMPLETADO,CANCELADO',
        ];
    }

    /**
     * Obtiene los mensajes de error personalizados para las reglas definidas.
     */
    public function messages(): array
    {
        return [
            'link_type.in' => 'El tipo de enlace seleccionado no es válido.',
            'company_uuid.exists' => 'La empresa seleccionada no existe.',
            'third_party_uuid.exists' => 'El tercero seleccionado no existe.',
            'vehicle_uuid.exists' => 'El vehículo seleccionado no existe.',
            'procedure_code.required' => 'El código de procedimiento es obligatorio.',
            'procedure_type.required' => 'El tipo de procedimiento es obligatorio.',
            'procedure_type.in' => 'El tipo de procedimiento seleccionado no es válido.',
            'date_of_creation.required' => 'La fecha de creación es obligatoria.',
            'city_uuid.required' => 'La ciudad es obligatoria.',
            'city_uuid.exists' => 'La ciudad seleccionada no existe.',
            'territorial_director_uuid.required' => 'El director territorial es obligatorio.',
            'territorial_director_uuid.exists' => 'El director territorial seleccionado no existe.',
            'status.in' => 'El estado seleccionado no es válido.',
        ];
    }

    /**
     * Obtiene los atributos personalizados para los errores de validación.
     */
    public function attributes(): array
    {
        return [
            'link_type' => 'Tipo de enlace',
            'company_uuid' => 'Empresa',
            'third_party_uuid' => 'Tercero',
            'vehicle_uuid' => 'Vehículo',
            'procedure_code' => 'Código de trámite',
            'filed_number' => 'Número de radicación',
            'procedure_type' => 'Tipo de trámite',
            'date_of_creation' => 'Fecha de creación',
            'city_uuid' => 'Ciudad',
            'subject' => 'Asunto',
            'territorial_director_uuid' => 'Director territorial',
            'status' => 'Estado del trámite',
        ];
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
}
