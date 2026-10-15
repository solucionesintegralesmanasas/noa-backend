<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\FormatsDates;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Retención de un ítem de nota débito. */
class DebitNoteItemWithholding extends Model
{
    use FormatsDates, HasFactory, HasUuid, LogsActivity;

    protected $table = 'debit_note_item_withholdings';

    protected $fillable = ['uuid', 'note_item_uuid', 'withholding_uuid', 'withholding_type', 'withholding_rate', 'amount'];

    protected $hidden = ['id'];

    protected $casts = ['withholding_rate' => 'float', 'amount' => 'float'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(DebitNoteItem::class, 'note_item_uuid', 'uuid');
    }
}
