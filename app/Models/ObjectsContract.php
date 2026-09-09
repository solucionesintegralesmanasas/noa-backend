<?php

namespace App\Models;

use App\Traits\FormatsDates;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Descripción funcional de la clase ObjectsContract.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-01-20
 */
class ObjectsContract extends Model
{
    use FormatsDates;
    use HasFactory;
    use HasUuid;
    use LogsActivity;

    /**
     * @var string
     */
    protected $table = 'objects_contracts';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'name',
        'description',
    ];

    /**
     * @var array<int, string>
     */
    protected $hidden = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [];

    /**
     * Los Fuecs que pertenecen a este objeto de contrato
     *
     * @return HasMany
     */
    public function fuecs()
    {
        return $this->hasMany(Fuec::class, 'object_contract_uuid', 'uuid');
    }
}
