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
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Nota crédito interna (sin transmisión DIAN por ahora). */
class CreditNote extends Model
{
    use BelongsToCompany, FormatsDates, HasFactory, HasUuid, LogsActivity;

    protected $table = 'credit_notes';

    protected $fillable = [
        'uuid', 'company_uuid', 'invoice_uuid', 'third_party_uuid', 'note_number',
        'correction_concept_code', 'observation', 'issue_date', 'payment_method_uuid',
        'subtotal', 'total_discount', 'total_tax', 'total_withholding', 'total',
        'status', 'electronic_status', 'cude', 'dian_response',
    ];

    protected $hidden = ['id'];

    protected $casts = [
        'issue_date' => 'date',
        'subtotal' => 'float',
        'total_discount' => 'float',
        'total_tax' => 'float',
        'total_withholding' => 'float',
        'total' => 'float',
        'dian_response' => 'array',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(CreditNoteItem::class, 'note_uuid', 'uuid')->orderBy('order_index');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(CommercialInvoice::class, 'invoice_uuid', 'uuid');
    }
}
