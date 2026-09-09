<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

class Signature extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'entity_type',
        'entity_id',
        'company_uuid',
        'ip_address',
        'latitude',
        'longitude',
        'path',
        'disk',
        'mime_type',
        'size_bytes',
        'speed',
        'state',
    ];

    protected $appends = ['url'];

    /**
     * Retorna la URL pública del archivo PNG.
     */
    public function getUrlAttribute(): string
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($this->disk);

        return $disk->url($this->path);
    }
}
