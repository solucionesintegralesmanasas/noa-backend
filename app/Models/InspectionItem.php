<?php

namespace App\Models;

use App\Traits\FormatsDates;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Items de inspección.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-28
 */
class InspectionItem extends Model
{
    use FormatsDates, HasFactory, HasUuid, LogsActivity;

    /**
     * @var string
     */
    protected $table = 'inspection_items';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'category',
        'item_name',
        'description',
    ];

    /**
     * @var array<int, string>
     */
    protected $hidden = [
        //
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        //
    ];
}
