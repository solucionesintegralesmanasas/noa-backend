<?php

namespace App\Support\HumanResources;

use Carbon\Carbon;

/**
 * Modelos de contrato laboral/vinculación vigentes en Colombia.
 *
 * Basados en los modelos de Gerencie.com ajustados a la Ley 2466 de 2025
 * (indefinido, fijo, obra o labor, medio tiempo) más el contrato ocasional
 * (art. 6 CST), el de aprendizaje (Ley 789 de 2002) y el de prestación de servicios.
 *
 * build() devuelve un documento estructurado (título, encabezado, cláusulas, cierre)
 * que renderiza la vista pdf.hr.employment-contract.
 */
class EmploymentContractTemplates
{
    public const TYPES = [
        'TERMINO_INDEFINIDO' => 'Contrato individual de trabajo a término indefinido',
        'TERMINO_FIJO' => 'Contrato individual de trabajo a término fijo',
        'OBRA_LABOR' => 'Contrato individual de trabajo por duración de la obra o labor',
        'OCASIONAL' => 'Contrato de trabajo ocasional, accidental o transitorio',
        'APRENDIZAJE' => 'Contrato de aprendizaje',
        'PRESTACION_SERVICIOS' => 'Contrato de prestación de servicios',
    ];

    private const BLANK = '________________';

    /** Palabra con que se nombra a cada parte según el tipo de contrato. */
    private const PARTES = [
        'APRENDIZAJE' => ['LA EMPRESA PATROCINADORA', 'EL APRENDIZ'],
        'PRESTACION_SERVICIOS' => ['EL CONTRATANTE', 'EL CONTRATISTA'],
    ];

    public static function label(string $type): string
    {
        return self::TYPES[$type] ?? $type;
    }

    public static function isValid(string $type): bool
    {
        return isset(self::TYPES[$type]);
    }

    /**
     * @param  array<string, mixed>  $d  Datos ya normalizados (ver EmploymentContractPdfService).
     * @return array{title: string, intro: string, clauses: array<int, array{title: string, text: string}>, closing: string, signatures: array{0: string, 1: string}, notes: array<int, string>}
     */
    public static function build(string $type, array $d): array
    {
        [$empleador, $trabajador] = self::PARTES[$type] ?? ['EL EMPLEADOR', 'EL TRABAJADOR'];

        $doc = match ($type) {
            'TERMINO_INDEFINIDO' => self::indefinido($d),
            'TERMINO_FIJO' => self::fijo($d),
            'OBRA_LABOR' => self::obraLabor($d),
            'OCASIONAL' => self::ocasional($d),
            'APRENDIZAJE' => self::aprendizaje($d),
            'PRESTACION_SERVICIOS' => self::prestacionServicios($d),
            default => throw new \InvalidArgumentException("Tipo de contrato no soportado: {$type}"),
        };

        $doc['title'] = mb_strtoupper(self::TYPES[$type]);
        $doc['signatures'] = [$empleador, $trabajador];
        $doc['closing'] = self::cierre($d);

        return $doc;
    }

    // ───────────────────────────── Modelos ─────────────────────────────

    private static function indefinido(array $d): array
    {
        $clauses = self::clausulasBase($d);
        $clauses[] = ['PERIODO DE PRUEBA', self::prueba($d, false)];
        $clauses[] = ['DURACIÓN', 'El presente contrato se celebra a término indefinido y tendrá vigencia mientras subsistan las causas que le dieron origen y la materia del trabajo.'];
        $clauses[] = ['OBLIGACIONES DE LAS PARTES', self::obligaciones()];
        $clauses[] = ['TERMINACIÓN', 'El contrato podrá terminarse por las causas legales. EL TRABAJADOR que decida terminarlo dará aviso a EL EMPLEADOR con treinta (30) días calendario de anticipación, conforme al artículo 47 del Código Sustantivo del Trabajo, sin que el incumplimiento de ese aviso genere sanción alguna.'];
        $clauses[] = ['INTEGRIDAD', self::integridad()];

        return [
            'intro' => self::intro($d, 'EL EMPLEADOR', 'EL TRABAJADOR', 'el presente contrato de trabajo'),
            'clauses' => self::tituloClausulas($clauses),
            'notes' => [],
        ];
    }

    private static function fijo(array $d): array
    {
        $clauses = self::clausulasBase($d);
        $clauses[] = ['DURACIÓN', 'El presente contrato tendrá una duración de '.self::v($d['duration']).', contados desde el '.self::v($d['start_date']).' hasta el '.self::v($d['end_date']).', ambas fechas inclusive.'];
        $clauses[] = ['PERIODO DE PRUEBA', self::prueba($d, true)];
        $clauses[] = ['PRÓRROGA Y AVISO DE TERMINACIÓN', 'Si con treinta (30) días de antelación al vencimiento del plazo ninguna de las partes manifiesta por escrito su intención de terminarlo, el contrato se entenderá prorrogado en los términos del artículo 46 del Código Sustantivo del Trabajo. Las partes también podrán prorrogarlo por acuerdo escrito. En ningún caso la duración total, incluidas las prórrogas, podrá superar cuatro (4) años.'];
        $clauses[] = ['PRESTACIONES', 'EL TRABAJADOR tendrá derecho a las vacaciones y prestaciones sociales en proporción al tiempo laborado, cualquiera que este sea.'];
        $clauses[] = ['OBLIGACIONES DE LAS PARTES', self::obligaciones()];
        $clauses[] = ['TERMINACIÓN', 'El contrato podrá terminarse antes del vencimiento por justa causa o por las demás causas legales; si EL EMPLEADOR lo termina sin justa causa, pagará la indemnización del artículo 64 del Código Sustantivo del Trabajo.'];
        $clauses[] = ['INTEGRIDAD', self::integridad()];

        return [
            'intro' => self::intro($d, 'EL EMPLEADOR', 'EL TRABAJADOR', 'el presente contrato de trabajo a término fijo'),
            'clauses' => self::tituloClausulas($clauses),
            'notes' => ['El aviso de no prórroga debe entregarse por escrito con al menos treinta (30) días de antelación al vencimiento.'],
        ];
    }

    private static function obraLabor(array $d): array
    {
        $clauses = [
            ['OBRA O LABOR CONTRATADA', 'EL TRABAJADOR se vincula para ejecutar la siguiente obra o labor: '.self::v($d['work_description'], 4).' (descripción precisa y detallada: proyecto, contrato o necesidad concreta, lugar, alcance y actividades que comprende).'],
        ];
        $clauses = array_merge($clauses, self::clausulasBase($d, false));
        $clauses[] = ['DURACIÓN', 'El contrato durará el tiempo necesario para la ejecución de la obra o labor descrita en la cláusula primera, y terminará cuando esta concluya.'];
        $clauses[] = ['NUEVA OBRA', 'Si terminada la obra EL TRABAJADOR continúa prestando sus servicios, el contrato se entenderá a término indefinido desde el inicio, salvo que las partes adicionen por escrito este contrato con la descripción precisa de una nueva y diferente obra o labor.'];
        $clauses[] = ['PERIODO DE PRUEBA Y PRESTACIONES', self::prueba($d, false).' EL TRABAJADOR tendrá derecho a las vacaciones y prestaciones sociales en proporción al tiempo laborado, cualquiera que este sea.'];
        $clauses[] = ['OBLIGACIONES DE LAS PARTES', self::obligaciones()];
        $clauses[] = ['INTEGRIDAD', self::integridad()];

        return [
            'intro' => self::intro($d, 'EL EMPLEADOR', 'EL TRABAJADOR', 'el presente contrato de trabajo por la duración de la obra o labor determinada'),
            'clauses' => self::tituloClausulas($clauses),
            'notes' => ['La obra debe describirse de forma precisa y detallada; una descripción genérica, como «labores de construcción», hace que el contrato se tenga por indefinido.'],
        ];
    }

    private static function ocasional(array $d): array
    {
        $clauses = [
            ['OBJETO', 'EL TRABAJADOR se obliga a prestar personalmente sus servicios para ejecutar la siguiente labor ocasional, accidental o transitoria: '.self::v($d['work_description'], 4).', labor distinta de las actividades normales de EL EMPLEADOR.'],
            ['LUGAR', 'El servicio se prestará en '.self::v($d['place']).'.'],
            ['JORNADA', self::jornada($d)],
            ['SALARIO', self::salario($d)],
            ['DURACIÓN', 'De conformidad con el artículo 6 del Código Sustantivo del Trabajo, este contrato es de corta duración y no mayor de un (1) mes, contado desde el '.self::v($d['start_date']).' hasta el '.self::v($d['end_date']).'. Vencido el término, si EL TRABAJADOR continúa prestando el servicio o la labor deja de ser ocasional, el contrato se regirá por las reglas del contrato de trabajo que corresponda.'],
            ['SEGURIDAD SOCIAL Y PRESTACIONES', 'EL EMPLEADOR afiliará a EL TRABAJADOR al sistema de seguridad social integral y le reconocerá las prestaciones y demás derechos que la ley establece, en proporción al tiempo laborado.'],
            ['OBLIGACIONES DE LAS PARTES', self::obligaciones()],
            ['TERMINACIÓN', 'El contrato terminará al vencimiento del plazo o al concluir la labor, o antes por las causas legales.'],
            ['INTEGRIDAD', self::integridad()],
        ];

        return [
            'intro' => self::intro($d, 'EL EMPLEADOR', 'EL TRABAJADOR', 'el presente contrato de trabajo ocasional, accidental o transitorio'),
            'clauses' => self::tituloClausulas($clauses),
            'notes' => ['Solo procede para labores distintas de las actividades normales del empleador y por un término máximo de un mes (art. 6 CST).'],
        ];
    }

    private static function aprendizaje(array $d): array
    {
        $clauses = [
            ['OBJETO', 'LA EMPRESA PATROCINADORA se obliga a facilitar a EL APRENDIZ los medios para su formación académica y práctica en la ocupación o programa de '.self::v($d['position']).', que adelanta en '.self::v($d['institution']).', y EL APRENDIZ se obliga a cumplir con diligencia las actividades de formación y de práctica, de acuerdo con el plan de estudios.'],
            ['NATURALEZA', 'Conforme a la Ley 789 de 2002, el contrato de aprendizaje es una forma especial de vinculación dentro del Derecho Laboral, sin subordinación laboral, por lo cual no constituye contrato de trabajo.'],
            ['DURACIÓN', 'El contrato tendrá una duración de '.self::v($d['duration']).', contados desde el '.self::v($d['start_date']).' hasta el '.self::v($d['end_date']).', sin que pueda exceder el máximo de dos (2) años que establece la ley. Comprende una etapa lectiva (formación académica) y una etapa productiva (práctica en la empresa).'],
            ['APOYO DE SOSTENIMIENTO', 'LA EMPRESA PATROCINADORA reconocerá a EL APRENDIZ, como apoyo de sostenimiento mensual, la suma de '.self::money($d['salary']).', que en ningún caso constituye salario y que no podrá ser inferior al porcentaje del salario mínimo mensual legal vigente que la ley fija para cada etapa. Se pagará por periodos mensuales mediante '.self::v($d['payment_method']).'.'],
            ['SEGURIDAD SOCIAL', 'LA EMPRESA PATROCINADORA afiliará y pagará los aportes a salud de EL APRENDIZ y a riesgos laborales durante la etapa productiva, conforme a la normativa vigente.'],
            ['JORNADA DE LA PRÁCTICA', 'En la etapa productiva EL APRENDIZ cumplirá '.self::horas($d).' horas semanales, en el horario de '.self::v($d['schedule']).', sin exceder la jornada máxima legal ni afectar su formación académica.'],
            ['OBLIGACIONES DE LA EMPRESA PATROCINADORA', 'Facilitar los medios y espacios para la práctica en la ocupación objeto de formación; designar un tutor o responsable; suministrar la dotación y los elementos de protección personal necesarios; pagar oportunamente el apoyo de sostenimiento; y reportar las novedades a la institución de formación.'],
            ['OBLIGACIONES DEL APRENDIZ', 'Asistir puntualmente a las actividades de formación y de práctica; cumplir las normas del reglamento interno y de seguridad de la empresa; guardar reserva sobre la información a la que tenga acceso; y cuidar los elementos que se le confíen.'],
            ['TERMINACIÓN', 'El contrato terminará por vencimiento del plazo, por la finalización de la etapa lectiva o productiva, por mutuo acuerdo, por cancelación de la matrícula o por las demás causas que establece la ley y el reglamento de la institución de formación.'],
        ];

        return [
            'intro' => self::intro($d, 'LA EMPRESA PATROCINADORA', 'EL APRENDIZ', 'el presente contrato de aprendizaje'),
            'clauses' => self::tituloClausulas($clauses),
            'notes' => ['El contrato de aprendizaje debe constar por escrito y registrarse ante la entidad de formación (SENA o institución autorizada) conforme a la Ley 789 de 2002.'],
        ];
    }

    private static function prestacionServicios(array $d): array
    {
        $clauses = [
            ['OBJETO', 'EL CONTRATISTA se obliga con EL CONTRATANTE a prestar de manera independiente sus servicios profesionales o técnicos consistentes en: '.self::v($d['work_description'], 4).'.'],
            ['AUTONOMÍA E INDEPENDENCIA', 'EL CONTRATISTA ejecutará el objeto del contrato con plena autonomía técnica y administrativa, sin subordinación jurídica frente a EL CONTRATANTE, utilizando sus propios medios y organizando libremente su tiempo. Este contrato no genera relación laboral ni prestaciones sociales a cargo de EL CONTRATANTE.'],
            ['PLAZO', 'El plazo de ejecución es de '.self::v($d['duration']).', contado desde el '.self::v($d['start_date']).' hasta el '.self::v($d['end_date']).', y podrá prorrogarse por acuerdo escrito de las partes.'],
            ['HONORARIOS Y FORMA DE PAGO', 'EL CONTRATANTE pagará a EL CONTRATISTA honorarios mensuales de '.self::money($d['salary']).', contra presentación de la cuenta de cobro o factura y del soporte de pago de aportes a la seguridad social. El pago se realizará mediante '.self::v($d['payment_method']).'.'],
            ['SEGURIDAD SOCIAL', 'EL CONTRATISTA es responsable de su afiliación y del pago de sus aportes al Sistema de Seguridad Social Integral (salud, pensión y riesgos laborales) como trabajador independiente, y deberá acreditarlos a EL CONTRATANTE como condición para cada pago.'],
            ['OBLIGACIONES DEL CONTRATISTA', 'Ejecutar el objeto con calidad y oportunidad; entregar los informes que EL CONTRATANTE razonablemente solicite sobre el avance; guardar confidencialidad sobre la información a que acceda; y responder por los daños que cause por dolo o culpa en la ejecución.'],
            ['OBLIGACIONES DEL CONTRATANTE', 'Pagar oportunamente los honorarios pactados; suministrar la información necesaria para la ejecución del objeto; y designar al supervisor del contrato.'],
            ['TERMINACIÓN', 'El contrato terminará por vencimiento del plazo, por cumplimiento del objeto, por mutuo acuerdo o por incumplimiento grave de cualquiera de las partes, previo aviso escrito.'],
            ['SOLUCIÓN DE CONTROVERSIAS', 'Las diferencias que surjan se buscarán resolver de manera directa y, de no lograrse, se someterán a los jueces competentes de la ciudad de '.self::v($d['employer_city']).'.'],
        ];

        return [
            'intro' => self::intro($d, 'EL CONTRATANTE', 'EL CONTRATISTA', 'el presente contrato de prestación de servicios'),
            'clauses' => self::tituloClausulas($clauses),
            'notes' => ['Este contrato es de naturaleza civil o comercial. Si en la práctica existe subordinación, cumplimiento de horario y órdenes, la relación se tendrá como contrato de trabajo (principio de primacía de la realidad sobre las formas).'],
        ];
    }

    // ─────────────────────────── Cláusulas comunes ───────────────────────────

    /** @return array<int, array{0: string, 1: string}> */
    private static function clausulasBase(array $d, bool $conObjeto = true): array
    {
        $c = [];
        if ($conObjeto) {
            $c[] = ['OBJETO', 'EL TRABAJADOR se obliga a prestar personalmente sus servicios en el cargo de '.self::v($d['position']).', cumpliendo las funciones descritas en el anexo de funciones, que hace parte de este contrato, y las demás propias de la naturaleza del cargo.'];
        } else {
            $c[] = ['CARGO', 'EL TRABAJADOR desempeñará el cargo de '.self::v($d['position']).', cumpliendo las funciones propias de la obra o labor y las demás inherentes al cargo.'];
        }
        $c[] = ['LUGAR', 'El trabajador fue contratado en '.self::v($d['employer_city']).' y prestará sus servicios en '.self::v($d['place']).'. Cualquier cambio de lugar se hará conforme a la ley y sin desmejorar sus condiciones.'];
        $c[] = ['JORNADA', self::jornada($d)];
        $c[] = ['SALARIO', self::salario($d)];

        return $c;
    }

    private static function jornada(array $d): string
    {
        $parcial = ! empty($d['hours']) && (float) $d['hours'] < 40;
        $base = 'EL TRABAJADOR laborará '.self::horas($d).' horas semanales, distribuidas '.self::v($d['schedule'], 2).', sin exceder la jornada máxima legal vigente.';

        if ($parcial) {
            return $base.' Por tratarse de una jornada inferior a la máxima legal (medio tiempo o trabajo por días), el salario es proporcional a la jornada pactada y se calcula sobre un valor por hora no inferior al que resulta del salario mínimo legal vigente. El trabajo suplementario, nocturno, dominical y festivo se remunerará conforme a la ley.';
        }

        return $base.' El trabajo suplementario, nocturno, dominical y festivo se remunerará conforme a la ley.';
    }

    private static function salario(array $d): string
    {
        $periodo = self::v($d['pay_period'], 1);
        $medio = self::v($d['payment_method'], 1);

        if (($d['salary_type'] ?? 'ORDINARIO') === 'INTEGRAL') {
            $txt = 'EL EMPLEADOR pagará a EL TRABAJADOR un salario integral mensual de '.self::money($d['salary']).', pagadero por periodos '.$periodo.' vencidos, mediante '.$medio.'. Las partes declaran que dicho salario compensa el trabajo nocturno, suplementario, dominical y festivo, y las prestaciones sociales, con excepción de las vacaciones, conforme al artículo 132 del Código Sustantivo del Trabajo, y que incluye un factor prestacional del treinta por ciento (30%).';
        } else {
            $txt = 'EL EMPLEADOR pagará a EL TRABAJADOR un salario mensual de '.self::money($d['salary']).', pagadero por periodos '.$periodo.' vencidos, mediante '.$medio.'.';
            if ($d['transport'] ?? false) {
                $txt .= ' Se pagará además el auxilio de transporte legal vigente.';
            } else {
                $txt .= ' Cuando haya lugar, se pagará además el auxilio de transporte.';
            }
        }

        return $txt;
    }

    private static function prueba(array $d, bool $esFijo): string
    {
        $dias = self::v($d['probation_days'], 1);
        if ($esFijo) {
            return "Las partes acuerdan un periodo de prueba de {$dias} días calendario. Si el contrato es inferior a un año, el periodo de prueba no excederá la quinta parte del término pactado, y en ningún caso superará dos meses.";
        }

        return "Las partes acuerdan un periodo de prueba de {$dias} días calendario, sin que exceda de dos meses, durante el cual cualquiera de ellas podrá terminar el contrato sin previo aviso y sin indemnización.";
    }

    private static function obligaciones(): string
    {
        return 'Además de las obligaciones legales, EL TRABAJADOR se obliga a cumplir el reglamento interno de trabajo y las órdenes e instrucciones de EL EMPLEADOR relacionadas con el cargo, y EL EMPLEADOR a afiliarlo al sistema de seguridad social y a pagarle las prestaciones sociales que establece la ley.';
    }

    private static function integridad(): string
    {
        return 'Este contrato reemplaza cualquier acuerdo anterior sobre las mismas condiciones, sin perjuicio de los derechos ya causados en favor de EL TRABAJADOR.';
    }

    private static function intro(array $d, string $empleador, string $trabajador, string $contrato): string
    {
        $rep = ! empty($d['employer_rep']) ? ', representado por '.$d['employer_rep'] : ', representado por '.self::BLANK;

        return 'Entre '.self::v($d['employer_name']).', identificado con NIT n.º '.self::v($d['employer_nit']).', domiciliado en '.self::v($d['employer_address']).' ('.self::v($d['employer_city']).')'.$rep.', quien en adelante se denominará '.$empleador.', y '.self::v($d['worker_name']).', identificado con cédula n.º '.self::v($d['worker_doc']).', domiciliado en '.self::v($d['worker_address']).' ('.self::v($d['worker_city']).'), quien en adelante se denominará '.$trabajador.', se celebra '.$contrato.', que se regirá por las siguientes cláusulas:';
    }

    private static function cierre(array $d): string
    {
        $ciudad = self::v($d['signing_city']);
        $fecha = $d['signing_date'] ?? null;
        if ($fecha) {
            $f = Carbon::parse($fecha)->locale('es');

            return "Para constancia se firma en {$ciudad}, a los {$f->day} días del mes de {$f->translatedFormat('F')} de {$f->year}, en dos ejemplares del mismo tenor, entregándose uno a cada parte.";
        }

        return "Para constancia se firma en {$ciudad}, a los ____ días del mes de ______________ de ________, en dos ejemplares del mismo tenor, entregándose uno a cada parte.";
    }

    /** Antepone la numeración ordinal a las cláusulas. */
    private static function tituloClausulas(array $clauses): array
    {
        $ordinales = ['PRIMERA', 'SEGUNDA', 'TERCERA', 'CUARTA', 'QUINTA', 'SEXTA', 'SÉPTIMA', 'OCTAVA', 'NOVENA', 'DÉCIMA', 'DÉCIMA PRIMERA', 'DÉCIMA SEGUNDA', 'DÉCIMA TERCERA', 'DÉCIMA CUARTA'];

        $out = [];
        foreach (array_values($clauses) as $i => [$titulo, $texto]) {
            $out[] = ['title' => ($ordinales[$i] ?? ($i + 1).'.').'. '.$titulo, 'text' => $texto];
        }

        return $out;
    }

    // ───────────────────────────── Utilidades ─────────────────────────────

    private static function v(mixed $valor, int $lineas = 1): string
    {
        $valor = is_string($valor) ? trim($valor) : $valor;
        if ($valor === null || $valor === '') {
            return str_repeat(self::BLANK, $lineas);
        }

        return (string) $valor;
    }

    private static function money(mixed $valor): string
    {
        if ($valor === null || $valor === '' || (float) $valor <= 0) {
            return '$ ________________ (valor en letras: ________________________________)';
        }

        return '$ '.number_format((float) $valor, 0, ',', '.').' pesos moneda corriente';
    }

    private static function horas(array $d): string
    {
        if (empty($d['hours'])) {
            return '____';
        }
        $h = (float) $d['hours'];

        return rtrim(rtrim(number_format($h, 2, ',', ''), '0'), ',');
    }
}
