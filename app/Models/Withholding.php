<?php

namespace App\Models;

use App\Traits\FormatsDates;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Tabla de retenciones tributarias según normativa DIAN.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-28
 */
class Withholding extends Model
{
    use FormatsDates, HasFactory, HasUuid, LogsActivity;

    /**
     * @var string
     */
    protected $table = 'withholdings';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'code',
        'name',
        'type',
        'dian_concept',
        'base_minimum',
        'rate',
        'debit_account',
        'credit_account',
        'applies_purchases',
        'applies_sales',
        'is_active',
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
        'base_minimum' => 'decimal:2',
        'rate' => 'decimal:6',
        'applies_purchases' => 'boolean',
        'applies_sales' => 'boolean',
        'is_active' => 'boolean',
    ];
}
