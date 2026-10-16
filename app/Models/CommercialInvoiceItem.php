<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\FormatsDates;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Ítem de documento comercial (cotización o factura interna). */
class CommercialInvoiceItem extends Model
{
    use FormatsDates, HasFactory, HasUuid, LogsActivity;

    protected $table = 'commercial_invoice_items';

    protected $fillable = [
        'uuid',
        'invoice_uuid',
        'order_index',
        'description',
        'measurement_unit_uuid',
        'quantity',
        'unit_price',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'line_total',
        'tax_type_uuid',
        'tribute_uuid',
        'fuec_uuid',
        'control_sheet_uuid',
        'vehicle_uuid',
    ];

    protected $hidden = ['id'];

    protected $casts = [
        'order_index' => 'integer',
        'quantity' => 'float',
        'unit_price' => 'float',
        'discount_amount' => 'float',
        'tax_rate' => 'float',
        'tax_amount' => 'float',
        'line_total' => 'float',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(CommercialInvoice::class, 'invoice_uuid', 'uuid');
    }

    public function withholdings(): HasMany
    {
        return $this->hasMany(CommercialInvoiceItemWithholding::class, 'invoice_item_uuid', 'uuid');
    }
}
