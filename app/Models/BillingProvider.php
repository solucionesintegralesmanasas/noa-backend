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
 * Proveedor tecnológico de facturación electrónica.
 *
 * Driver único: Factus (emisión electrónica DIAN). Las credenciales OAuth2
 * (client_id, client_secret, email, client_password) y los tokens
 * (access_token, refresh_token) se cifran en reposo.
 */
class BillingProvider extends Model
{
    use BelongsToCompany, FormatsDates, HasFactory, HasUuid, LogsActivity;

    protected $table = 'billing_providers';

    public const DRIVER_NULO = 'nulo';

    public const DRIVER_FACTUS = 'factus';

    protected $fillable = [
        'uuid',
        'name',
        'driver',
        'server_url',
        'send_to_dian',
        'settings',
        'client_id',
        'client_secret',
        'client_password',
        'access_token',
        'refresh_token',
        'access_token_expires_at',
        'email',
        'start_date',
        'end_date',
        'company_uuid',
        'description',
        'is_active',
    ];

    protected $hidden = ['id', 'client_secret', 'client_password', 'access_token', 'refresh_token'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'send_to_dian' => 'boolean',
        // Secreto de la aplicación OAuth2 (Factus), cifrado en reposo.
        'client_secret' => 'encrypted',
        // Contraseña del portal Factus, cifrada en reposo.
        'client_password' => 'encrypted',
        // Tokens OAuth2 de Factus, cifrados en reposo.
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'access_token_expires_at' => 'datetime',
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    /** Resuelve un override de mapeo Factus desde settings (ej. numbering_range_id). */
    public function ajuste(string $clave): ?string
    {
        return $this->settings[$clave] ?? null;
    }

    public function usaFactus(): bool
    {
        return $this->driver === self::DRIVER_FACTUS;
    }

    public function resolutions(): HasMany
    {
        return $this->hasMany(BillingResolution::class, 'billing_provider_uuid', 'uuid');
    }
}
