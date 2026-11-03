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
     * Son opcionales: solo se valida formato y rango cuando vienen diligenciados.
     */
    protected function reglasDatosPersona(): array
    {
        return [
            'birth_place_uuid' => [
                'nullable',
                'uuid',
                Rule::exists('cities', 'uuid'),
            ],
            'birth_date' => [
                'nullable',
                'date',
                'before:today',
            ],
            'id_card_issue_place_uuid' => [
                'nullable',
                'uuid',
                Rule::exists('cities', 'uuid'),
            ],
            'id_card_issue_date' => [
                'nullable',
                'date',
                'before_or_equal:today',
            ],
            'sex' => [
                'nullable',
                Rule::in(self::SEXOS),
            ],
            'marital_status' => [
                'nullable',
                Rule::in(self::ESTADOS_CIVILES),
            ],
            'blood_type' => [
                'nullable',
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
            'birth_date.before' => 'La fecha de nacimiento debe ser anterior a hoy.',
            'id_card_issue_date.before_or_equal' => 'La fecha de expedición de la cédula no puede ser posterior a hoy.',
            'sex.in' => 'El sexo seleccionado no es válido.',
            'marital_status.in' => 'El estado civil seleccionado no es válido.',
            'blood_type.in' => 'El grupo sanguíneo seleccionado no es válido.',
        ];
    }

    /**
     * Etiquetas legibles para los datos de cédula.
     */
    protected function atributosDatosPersona(): array
    {
        return [
            'birth_place_uuid' => 'Lugar de nacimiento',
            'birth_date' => 'Fecha de nacimiento',
            'id_card_issue_place_uuid' => 'Lugar de expedición de la cédula',
            'id_card_issue_date' => 'Fecha de expedición de la cédula',
            'sex' => 'Sexo',
            'marital_status' => 'Estado civil',
            'blood_type' => 'Grupo sanguíneo',
        ];
    }
}
