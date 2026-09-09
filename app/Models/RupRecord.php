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
 * Modelo RupRecord que representa la tabla rup_records.
 * Registro Único de Proponentes (RUP) y calificaciones
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class RupRecord extends Model implements HasMedia
{
    use BelongsToCompany, FormatsDates, HasFactory, HasFiles, HasUuid, LogsActivity;

    /**
     * Tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'rup_records';

    /**
     * Atributos que son asignables en masa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'company_uuid',
        'registration_number',
        'issue_date',
        'expiration_date',
        'legal_capacity_score',
        'financial_capacity_score',
        'organizational_capacity_score',
        'contracting_capacity_score',
        'rup_certificate_path',
        'status',
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
        'issue_date' => 'date',
        'expiration_date' => 'date',
    ];
}
