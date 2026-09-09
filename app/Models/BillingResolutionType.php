<?php

namespace App\Models;

use App\Traits\FormatsDates;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Tipos de documentos electrónicos autorizados por la DIAN.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-28
 */
class BillingResolutionType extends Model
{
    use FormatsDates, HasFactory, HasUuid, LogsActivity;

    /**
     * @var string
     */
    protected $table = 'billing_resolution_types';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'code',
        'name',
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
        'is_active' => 'boolean',
    ];
}
