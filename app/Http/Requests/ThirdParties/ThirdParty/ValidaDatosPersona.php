<?php

namespace App\Http\Requests\ThirdParties\ThirdParty;

use Illuminate\Validation\Rule;

trait ValidaDatosPersona
{
    public const SEXOS = ['M', 'F', 'O'];

    public const ESTADOS_CIVILES = [
        'SOLTERO',
        'CASADO',
        'UNION_LIBRE',
        'SEPARADO',
        'DIVORCIADO',
        'VIUDO',
    ];

    public const GRUPOS_SANGUINEOS = [
        'A+',
        'A-',
        'B+',
        'B-',
        'O+',
        'O-',
        'AB+',
        'AB-',
    ];

    /**
     * Reglas de validación para los datos de cédula de personas naturales.
     */
    protected function reglasDatosPersona(): array
    {
        $esNatural = $this->input('person_type') === 'NATURAL';

        return [
            'id_card_issue_place_uuid' => [
                'nullable',
                Rule::requiredIf($esNatural),
                'uuid',
                Rule::exists('cities', 'uuid'),
            ],
            'id_card_issue_date' => [
                'nullable',
                Rule::requiredIf($esNatural),
                'date',
                'before_or_equal:today',
            ],
            'sex' => [
                'nullable',
                Rule::requiredIf($esNatural),
                Rule::in(self::SEXOS),
            ],
            'marital_status' => [
                'nullable',
                Rule::requiredIf($esNatural),
                Rule::in(self::ESTADOS_CIVILES),
            ],
            'blood_type' => [
                'nullable',
                Rule::requiredIf($esNatural),
                Rule::in(self::GRUPOS_SANGUINEOS),
            ],
        ];
    }

    /**
     * Mensajes de validación personalizados en español para los datos de cédula.
     */
    protected function mensajesDatosPersona(): array
    {
        return [
            'id_card_issue_place_uuid.required' => 'El lugar de expedición de la cédula es obligatorio para personas naturales.',
            'id_card_issue_date.required' => 'La fecha de expedición de la cédula es obligatoria para personas naturales.',
            'id_card_issue_date.before_or_equal' => 'La fecha de expedición de la cédula no puede ser posterior a hoy.',
            'sex.required' => 'El sexo es obligatorio para personas naturales.',
            'sex.in' => 'El sexo seleccionado no es válido.',
            'marital_status.required' => 'El estado civil es obligatorio para personas naturales.',
            'marital_status.in' => 'El estado civil seleccionado no es válido.',
            'blood_type.required' => 'El grupo sanguíneo es obligatorio para personas naturales.',
            'blood_type.in' => 'El grupo sanguíneo seleccionado no es válido.',
        ];
    }

    /**
     * Etiquetas legibles para los datos de cédula.
     */
    protected function atributosDatosPersona(): array
    {
        return [
            'id_card_issue_place_uuid' => 'Lugar de expedición de la cédula',
            'id_card_issue_date' => 'Fecha de expedición de la cédula',
            'sex' => 'Sexo',
            'marital_status' => 'Estado civil',
            'blood_type' => 'Grupo sanguíneo',
        ];
    }
}
