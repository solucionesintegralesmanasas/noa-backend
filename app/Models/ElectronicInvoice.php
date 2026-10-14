<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\FormatsDates;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Respuesta del proveedor tecnológico / DIAN.
 * En fase 1 y 2 solo existe en estado PENDIENTE (sin transmisión).
 */
class ElectronicInvoice extends Model
{
    use FormatsDates, HasFactory, HasUuid, LogsActivity;

    protected $table = 'electronic_invoices';

    protected $fillable = [
        'uuid',
        'invoice_uuid',
        'status',
        'validated_at',
        'cufe',
        'provider_document_id',
        'xml_url',
        'pdf_url',
        'errors',
        'raw_response',
    ];

    protected $hidden = ['id'];

    protected $casts = [
        'validated_at' => 'datetime',
        'errors' => 'array',
        'raw_response' => 'array',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(CommercialInvoice::class, 'invoice_uuid', 'uuid');
    }
}
