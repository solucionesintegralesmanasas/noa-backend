<?php

namespace App\Models;

use App\Traits\FormatsDates;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Centros de costo por empresa.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-06-08
 */
class CostCenter extends Model
{
    use FormatsDates, HasFactory, HasUuid, LogsActivity;

    /**
     * @var string
     */
    protected $table = 'cost_centers';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'company_uuid',
        'code',
        'name',
        'description',
        'parent_code',
        'level',
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
        'level' => 'integer',
    ];

    /**
     * Relación con la empresa propietaria.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_uuid', 'uuid');
    }
}
