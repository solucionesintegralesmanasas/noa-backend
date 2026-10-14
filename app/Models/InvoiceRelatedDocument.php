<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\FormatsDates;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Documento antecedente referenciado por una factura (uso futuro DIAN). */
class InvoiceRelatedDocument extends Model
{
    use FormatsDates, HasFactory, HasUuid, LogsActivity;

    protected $table = 'invoice_related_documents';

    protected $fillable = ['uuid', 'invoice_uuid', 'code', 'issue_date', 'number'];

    protected $hidden = ['id'];

    protected $casts = ['issue_date' => 'date'];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(CommercialInvoice::class, 'invoice_uuid', 'uuid');
    }
}
