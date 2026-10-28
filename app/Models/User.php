<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laragear\TwoFactor\Contracts\TwoFactorAuthenticatable;
use Laragear\TwoFactor\TwoFactorAuthentication;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements TwoFactorAuthenticatable
{
    use HasApiTokens, HasFactory, HasUuid, LogsActivity, Notifiable, TwoFactorAuthentication;
    use HasRoles {
        hasRole as spatieHasRole;
    }

    /**
     * Mapa de alias canónicos de roles para evitar discrepancias de vocabulario (Issue #1).
     * Mapea alias comunes ('administrador', 'admin', 'super-admin') a sus nombres oficiales.
     */
    public const ROLE_ALIASES = [
        'ADMINISTRADOR' => 'ADMIN_EMPRESA',
        'ADMIN' => 'ADMIN_EMPRESA',
        'ADMIN_EMPRESA' => 'ADMIN_EMPRESA',
        'ADMINEMPRESA' => 'ADMIN_EMPRESA',
        'SUPER_ADMIN' => 'SUPERADMIN',
        'SUPER-ADMIN' => 'SUPERADMIN',
        'SUPERADMIN' => 'SUPERADMIN',
    ];

    /**
     * Normaliza un nombre de rol a su identificador canónico del sistema.
     */
    public static function normalizeRoleName(string $role): string
    {
        $normalized = strtoupper(trim(str_replace(['-', ' '], '_', $role)));
        $sinGuiones = strtoupper(trim(str_replace(['-', '_', ' '], '', $role)));

        return self::ROLE_ALIASES[$sinGuiones] ?? (self::ROLE_ALIASES[$normalized] ?? $normalized);
    }

    /**
     * Sobrescribe hasRole de Spatie para soportar alias canónicos (Issue #1).
     * Garantiza que hasRole('administrador'), hasRole('admin') y hasRole('ADMIN_EMPRESA')
     * resuelvan exactamente lo mismo.
     *
     * @param string|int|array|\Spatie\Permission\Contracts\Role|\Illuminate\Support\Collection $roles
     * @param string|null $guard
     * @return bool
     */
    public function hasRole($roles, string $guard = null): bool
    {
        if (is_string($roles)) {
            $canonical = self::normalizeRoleName($roles);
            if ($this->spatieHasRole($canonical, $guard)) {
                return true;
            }
            return $canonical !== $roles && $this->spatieHasRole($roles, $guard);
        }

        if (is_array($roles) || $roles instanceof \Illuminate\Support\Collection) {
            $normalizedRoles = collect($roles)->flatMap(function ($r) {
                if (is_string($r)) {
                    $canon = self::normalizeRoleName($r);
                    return $canon !== $r ? [$canon, $r] : [$r];
                }
                return [$r];
            })->unique()->all();

            return $this->spatieHasRole($normalizedRoles, $guard);
        }

        return $this->spatieHasRole($roles, $guard);
    }

    protected string $guard_name = 'api';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'user_name',
        'password',
        'verification_code',
        'verification_code_expires_at',
        'last_login_at',
        'failed_login_attempts',
        'locked_until',
        'status',
        'google_id',
        'google_email',
        'google_drive_refresh_token',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'google_drive_refresh_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'verification_code_expires_at' => 'datetime',
            'last_login_at' => 'datetime',
            'locked_until' => 'datetime',
        ];
    }

    /**
     * Cada vez que se crea un usuario, el correo queda verificado automáticamente
     * (el sistema no tiene flujo de verificación por email; autenticación los bloquea).
     * Solo se omite si el flujo que crea el usuario lo indica explícitamente con null.
     */
    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (! array_key_exists('email_verified_at', $user->getAttributes())) {
                $user->email_verified_at = now();
            }
        });
    }

    /**
     * Devuelve todos los permisos del usuario como colección de nombres.
     * Alias para getAllPermissions()->pluck('name').
     *
     * @return Collection<int, string>
     */
    public function getAllPermissionsAttribute(): Collection
    {
        return $this->getAllPermissions()->pluck('name');
    }

    /**
     * Empresas a las que pertenece este usuario.
     */
    public function companies()
    {
        return $this->belongsToMany(Company::class, 'company_user', 'user_id', 'company_uuid', 'id', 'uuid')
            ->withPivot('third_party_uuid', 'is_active')
            ->withTimestamps();
    }

    /**
     * Verifica si el usuario tiene acceso a la empresa.
     */
    public function canAccessCompany(Company $company): bool
    {
        return $this->companies()->where('company_uuid', $company->uuid)->exists();
    }
}
