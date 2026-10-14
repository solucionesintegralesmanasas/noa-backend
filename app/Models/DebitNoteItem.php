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

/** Ítem de nota débito interna. */
class DebitNoteItem extends Model
{
    use FormatsDates, HasFactory, HasUuid, LogsActivity;

    protected $table = 'debit_note_items';

    protected $fillable = [
        'uuid', 'note_uuid', 'order_index', 'description', 'measurement_unit_uuid',
        'quantity', 'unit_price', 'discount_amount', 'tax_rate', 'tax_amount',
        'line_total', 'tax_type_uuid', 'tribute_uuid',
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

    public function note(): BelongsTo
    {
        return $this->belongsTo(DebitNote::class, 'note_uuid', 'uuid');
    }

    public function withholdings(): HasMany
    {
        return $this->hasMany(DebitNoteItemWithholding::class, 'note_item_uuid', 'uuid');
    }
}
