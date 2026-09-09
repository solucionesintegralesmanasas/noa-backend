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
 * Modelo TaxInformation que representa la tabla tax_information.
 * Información tributaria y fiscal complementaria
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class TaxInformation extends Model implements HasMedia
{
    use BelongsToCompany, FormatsDates, HasFactory, HasFiles, HasUuid, LogsActivity;

    /**
     * Tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'tax_information';

    /**
     * Atributos que son asignables en masa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'company_uuid',
        'is_withholding_agent_exempt',
        'tax_special_regime',
        'company_size',
        'financial_statements_path',
        'company_size_certificate_path',
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
        'is_withholding_agent_exempt' => 'boolean',
    ];
}
