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

/**
 * Proveedor tecnológico de facturación electrónica (fase posterior).
 *
 * En fase 1 y 2 solo se registra el catálogo; ningún documento se transmite.
 */
class BillingProvider extends Model
{
    use BelongsToCompany, FormatsDates, HasFactory, HasUuid, LogsActivity;

    protected $table = 'billing_providers';

    public const DRIVER_NULO = 'nulo';

    public const DRIVER_APIDIAN = 'apidian';

    protected $fillable = [
        'uuid',
        'name',
        'driver',
        'server_url',
        'test_set_id',
        'send_to_dian',
        'settings',
        'client_id',
        'client_secret',
        'client_password',
        'email',
        'start_date',
        'end_date',
        'company_uuid',
        'description',
        'is_active',
    ];

    protected $hidden = ['id', 'client_secret', 'client_password'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'send_to_dian' => 'boolean',
        // ApiDian autentica con token; se cifra en reposo. Los valores guardados
        // antes de este cast estaban en texto plano y deben volver a guardarse.
        'client_secret' => 'encrypted',
        // Credenciales del portal (paso 00 de la colección) para renovar el JWT.
        'client_password' => 'encrypted',
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    /** Resuelve un override de mapeo ApiDian desde settings (ej. municipality_id). */
    public function ajuste(string $clave): ?string
    {
        return $this->settings[$clave] ?? null;
    }

    public function usaApiDian(): bool
    {
        return $this->driver === self::DRIVER_APIDIAN;
    }

    public function resolutions(): HasMany
    {
        return $this->hasMany(BillingResolution::class, 'billing_provider_uuid', 'uuid');
    }
}
