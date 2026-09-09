<?php

namespace App\Models;

use App\Traits\FormatsDates;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de ciudades y municipios colombianos.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-28
 */
class City extends Model
{
    use FormatsDates, HasFactory, HasUuid, LogsActivity;

    /**
     * @var string
     */
    protected $table = 'cities';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'department_uuid',
        'dane_code',
        'name',
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
        //
    ];

    /**
     * Relación con departamento.
     */
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_uuid', 'uuid');
    }
}
