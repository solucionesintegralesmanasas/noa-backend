<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\FormatsDates;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo para la gestión de consecutivos de FUEC.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 */
class ItWasConsecutive extends Model
{
    use BelongsToCompany;
    use FormatsDates;
    use HasFactory;
    use HasUuid;
    use LogsActivity;

    /**
     * @var string
     */
    protected $table = 'fuec_consecutives';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'company_uuid',
        'type',
        'year',
        'contract_id',
        'current_consecutive',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'year' => 'integer',
        'contract_id' => 'integer',
        'current_consecutive' => 'integer',
    ];

    /**
     * Relación con el contratista/contrato asociado.
     */
    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class, 'contract_id', 'id');
    }
}
