<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\FormatsDates;
use App\Traits\HasFiles;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;

/**
 * Modelo OccupationalSafetyRecord que representa la tabla occupational_safety_records.
 * Registros de seguridad y salud en el trabajo (SST)
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class OccupationalSafetyRecord extends Model implements HasMedia
{
    use BelongsToCompany, FormatsDates, HasFactory, HasFiles, HasUuid, LogsActivity;

    /**
     * Tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'occupational_safety_records';

    /**
     * Atributos que son asignables en masa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'company_uuid',
        'operation_year',
        'fatalities',
        'incapacitating_accidents_count',
        'total_incidents_count',
        'lost_days_count',
        'average_workers_count',
        'hours_worked',
        'arl_accident_certificate_date',
        'risk_level',
        'arl_affiliation_certificate_date',
        'sgsst_rating',
        'sgsst_evaluation_date',
        'sgsst_certificate_path',
        'remarks',
    ];

    /**
     * Atributos que deben ser ocultos para la serialización.
     *
     * @var array<int, string>
     */
    protected $hidden = [
    ];

    /**
     * Atributos que deben ser convertidos a tipos nativos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'arl_accident_certificate_date' => 'date',
        'arl_affiliation_certificate_date' => 'date',
        'sgsst_evaluation_date' => 'date',
    ];
}
