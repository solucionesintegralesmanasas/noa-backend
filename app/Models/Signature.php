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
        'signer_role',
        'scope',
        'status',
        'signed_at',
        'signer_uuid',
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

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    /** Roles válidos de firmante (SPEC-002 §6). */
    public const ROL_FUNCIONARIO = 'funcionario';

    public const ROL_CONDUCTOR = 'conductor';

    public const ROL_COORDINADOR = 'coordinador';

    /** Estados de vigencia de una firma (SPEC-002 §6). */
    public const STATUS_VIGENTE = 'vigente';

    public const STATUS_REEMPLAZADA = 'reemplazada';

    public const STATUS_REVOCADA = 'revocada';

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
