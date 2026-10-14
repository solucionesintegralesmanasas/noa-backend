<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\FormatsDates;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Abono o pago aplicado a una factura comercial. */
class CommercialInvoicePayment extends Model
{
    use FormatsDates, HasFactory, HasUuid, LogsActivity;

    protected $table = 'commercial_invoice_payments';

    protected $fillable = [
        'uuid',
        'invoice_uuid',
        'amount',
        'payment_date',
        'payment_method_uuid',
        'reference',
    ];

    protected $hidden = ['id'];

    protected $casts = [
        'amount' => 'float',
        'payment_date' => 'date',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(CommercialInvoice::class, 'invoice_uuid', 'uuid');
    }
}
