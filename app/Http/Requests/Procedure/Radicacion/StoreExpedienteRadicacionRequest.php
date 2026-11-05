<?php

declare(strict_types=1);

namespace App\Http\Requests\Procedure\Radicacion;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Petición de validación para crear un expediente de radicación.
 */
class StoreExpedienteRadicacionRequest extends FormRequest
{
    use HandlesApiResponse, ValidaEmpresaDelContexto;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // El formulario no envía empresa: se toma la del contexto para no crear
        // expedientes sin empresa (fuera del alcance multi-tenant y de la unicidad).
        if (! $this->input('company_uuid')) {
            $actual = $this->attributes->get('current_company_uuid');
            if ($actual) {
                $this->merge(['company_uuid' => $actual]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'link_type' => 'required|in:NUEVO_VEHICULO,CAMBIO_DE_EMPRESA,RENOVACION,DESVINCULACION_MUTUO,DESVINCULACION_UNILATERAL',
            'company_uuid' => ['nullable', 'uuid', 'exists:companies,uuid', $this->reglaEmpresaDelContexto()],
            'origin_company_uuid' => [
                'nullable', 'uuid', 'exists:companies,uuid',
                Rule::requiredIf(fn () => $this->input('link_type') === 'CAMBIO_DE_EMPRESA'),
                Rule::notIn([$this->input('company_uuid')]),
            ],
            'third_party_uuid' => 'nullable|uuid|exists:third_parties,uuid',
            'vehicle_uuid' => 'required|uuid|exists:vehicles,uuid',
            'procedure_code' => [
                'nullable', 'string', 'max:50',
                // El índice único (company_uuid, procedure_code) sobre expedientes padre
                // ya existe en BD (migración 2026_10_28_000001); esta regla da el 422
                // legible antes de llegar a la restricción de la base.
                Rule::unique('procedures', 'procedure_code')
                    ->where(fn ($q) => $q->where('company_uuid', $this->input('company_uuid') ?? $this->attributes->get('current_company_uuid'))
                        ->where(fn ($w) => $w->whereNull('parent_procedure_uuid')->orWhere('parent_procedure_uuid', ''))),
            ],
            'date_of_creation' => 'required|date',
            'city_uuid' => 'required|uuid|exists:cities,uuid',
            'subject' => 'nullable|string|max:255',
            // La Dirección territorial ya no se usa en la radicación: se admite sin ella.
            'territorial_director_uuid' => 'nullable|uuid|exists:territorial_directors,uuid',
            'territorial_director_name' => 'nullable|string|max:200',
            'territorial_name' => 'nullable|string|max:150',
            'paz_y_salvo' => 'nullable|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'link_type.required' => 'El tipo de vinculación es obligatorio.',
            'link_type.in' => 'El tipo de vinculación no es válido.',
            'origin_company_uuid.required' => 'La empresa de origen es obligatoria para el cambio de empresa.',
            'origin_company_uuid.exists' => 'La empresa de origen no existe.',
            'origin_company_uuid.not_in' => 'La empresa de origen no puede ser la misma empresa receptora.',
            'third_party_uuid.exists' => 'El tercero no existe.',
            'vehicle_uuid.required' => 'El vehículo es obligatorio.',
            'vehicle_uuid.exists' => 'El vehículo no existe.',
            'procedure_code.required' => 'El código de trámite es obligatorio.',
            'procedure_code.unique' => 'Ya existe un trámite con este código en la empresa.',
            'date_of_creation.required' => 'La fecha de radicación es obligatoria.',
            'city_uuid.required' => 'La ciudad es obligatoria.',
            'city_uuid.exists' => 'La ciudad no existe.',
            'territorial_director_uuid.exists' => 'El director territorial no existe.',
        ];
    }

    public function attributes(): array
    {
        return [
            'link_type' => 'Tipo de vinculación',
            'company_uuid' => 'Empresa',
            'origin_company_uuid' => 'Empresa de origen',
            'third_party_uuid' => 'Tercero',
            'vehicle_uuid' => 'Vehículo',
            'procedure_code' => 'Código de trámite',
            'date_of_creation' => 'Fecha de radicación',
            'city_uuid' => 'Ciudad',
            'subject' => 'Asunto',
            'territorial_director_uuid' => 'Director territorial',
            'territorial_director_name' => 'Nombre de la directora o director',
            'territorial_name' => 'Dirección territorial',
            'paz_y_salvo' => 'Paz y salvo',
        ];
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException($this->validationErrorResponse($validator->errors()->toArray()));
    }
}
