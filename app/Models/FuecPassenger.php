<?php

namespace App\Models;

use App\Traits\FormatsDates;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Descripción funcional de la clase FuecPassenger.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-01-20
 */
class FuecPassenger extends Model
{
    use FormatsDates;
    use HasFactory;
    use HasUuid;
    use LogsActivity;

    /**
     * @var string
     */
    protected $table = 'fuec_passengers';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'fuec_uuid',
        'type_of_document_uuid',
        'document_number',
        'first_and_last_name',
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
     * El FUEC asociado a este pasajero
     *
     * @return BelongsTo
     */
    public function fuec()
    {
        return $this->belongsTo(Fuec::class, 'fuec_uuid', 'uuid');
    }

    /**
     * El tipo de documento asociado a este pasajero
     *
     * @return BelongsTo
     */
    public function typeOfDocument()
    {
        return $this->belongsTo(TypeOfDocument::class, 'type_of_document_uuid', 'uuid');
    }
}
