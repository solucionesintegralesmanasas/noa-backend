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
    use HasApiTokens, HasFactory, HasRoles, HasUuid, LogsActivity, Notifiable, TwoFactorAuthentication;

    protected string $guard_name = 'api';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
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
