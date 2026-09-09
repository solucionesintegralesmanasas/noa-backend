<?php

namespace App\Models;

use App\Traits\FormatsDates;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Parámetros DIAN.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-28
 */
class DianParameter extends Model
{
    use FormatsDates, HasFactory, HasUuid, LogsActivity;

    /**
     * @var string
     */
    protected $table = 'dian_parameters';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'year',
        'uvt',
        'iva_withholding_rate',
        'minimum_wage',
        'transport_subsidy',
        'usury_rate',
        'observations',
    ];

    /**
     * @var array<int, string>
     */
    protected $hidden = [
        //
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'uvt' => 'decimal:2',
        'iva_withholding_rate' => 'decimal:4',
        'minimum_wage' => 'decimal:2',
        'transport_subsidy' => 'decimal:2',
        'usury_rate' => 'decimal:4',
    ];
}
