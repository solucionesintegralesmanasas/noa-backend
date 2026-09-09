<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Events\UserAuthenticated;
use App\Exceptions\GeneralException;
use App\Models\DriverLicense;
use App\Models\OwnerDriver;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Class AuthenticationService
 *
 * Servicio de negocio para la gestión de AuthenticationService.
 */
class AuthenticationService
{
    /**
     * Autentica a un usuario utilizando las credenciales proporcionadas.
     *
     * @param  array<string, mixed>  $credentials
     * @return array<string, mixed>
     *
     * @throws GeneralException
     */
    public function authenticateUser(array $credentials): array
    {
        $email = $credentials['email'];
        $ip = request()->ip();

        $this->enforceRateLimiting($email, $ip);
        $user = $this->resolveUserByCredentials($email);
        $this->validateAccountLockStatus($user);

        if (! Auth::attempt($credentials)) {
            $this->processFailedAuthentication($email, $ip, $user);
        }

        // Limpiar los intentos fallidos en el rate limiter al iniciar sesión exitosamente
        RateLimiter::clear($this->getRateLimitKey($email, $ip));

        event(new UserAuthenticated($user, $email, $ip));

        return $this->processSuccessfulAuthentication($user);
    }

    /**
     * Procesa la autenticación exitosa del usuario.
     *
     * @return array<string, mixed>
     */
    private function processSuccessfulAuthentication(User $user): array
    {
        $this->validateEmailVerification($user);
        $user->update([
            'last_login_at' => now(),
            'failed_login_attempts' => 0,
        ]);

        $tokens = $this->generateTokenPair($user);
        $companies = $user->companies()->wherePivot('is_active', true)->get();
        $defaultCompany = $companies->first();

        return [
            'status' => 'success',
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_in' => config('auth.tokens.access_token_hours') * 3600,
            'user' => $this->buildUserPayload($user),
        ];
    }

    /**
     * Genera el par de tokens de acceso y refresco para el usuario.
     *
     * @return array<string, string>
     */
    private function generateTokenPair(User $user): array
    {
        return [
            'access_token' => $user->createToken(
                'access_token',
                ['*'],
                now()->addHours(config('auth.tokens.access_token_hours'))
            )->plainTextToken,
            'refresh_token' => $user->createToken(
                'refresh_token',
                ['refresh'],
                now()->addDays(config('auth.tokens.refresh_token_days'))
            )->plainTextToken,
        ];
    }

    /**
     * Procesa una autenticación fallida, incrementando los intentos y bloqueando si es necesario.
     *
     * @throws GeneralException
     */
    private function processFailedAuthentication(string $username, string $ip, ?User $user): void
    {
        RateLimiter::hit($this->getRateLimitKey($username, $ip));

        if ($user) {
            $user->increment('failed_login_attempts', 1, []);
            if ($user->failed_login_attempts >= config('auth.tokens.max_attempts')) {
                $user->update([
                    'locked_until' => now()->addMinutes(config('auth.tokens.lockout_minutes')),
                ]);
            }
        }

        throw GeneralException::unauthorized('Credenciales incorrectas');
    }

    /**
     * Aplica el límite de intentos de inicio de sesión.
     *
     * @throws GeneralException
     */
    private function enforceRateLimiting(string $username, string $ip): void
    {
        if (RateLimiter::tooManyAttempts($this->getRateLimitKey($username, $ip), config('auth.tokens.max_attempts'))) {
            throw GeneralException::tooManyRequests('Demasiados intentos fallidos.');
        }
    }

    /**
     * Resuelve un usuario mediante su email o nombre de usuario.
     */
    private function resolveUserByCredentials(string $username): ?User
    {
        return User::query()
            ->where('email', $username)
            ->orWhere('user_name', $username)
            ->first();
    }

    /**
     * Valida si la cuenta del usuario está bloqueada temporalmente.
     *
     * @throws GeneralException
     */
    private function validateAccountLockStatus(?User $user): void
    {
        if ($user?->locked_until && now()->lessThan($user->locked_until)) {
            throw GeneralException::forbidden('Cuenta bloqueada temporalmente.');
        }
    }

    /**
     * Valida que el correo electrónico del usuario esté verificado.
     *
     * @throws GeneralException
     */
    private function validateEmailVerification(User $user): void
    {
        if (! $user->email_verified_at) {
            throw GeneralException::forbidden('Email no verificado.');
        }
    }

    /**
     * Genera la clave única para el rate limiter basada en usuario e IP.
     */
    private function getRateLimitKey(string $username, string $ip): string
    {
        return strtolower($username) . '|' . $ip;
    }

    /**
     * Crea la cookie HTTP-only con el refresh token.
     *
     * @return Symfony\Component\HttpFoundation\Cookie
     */
    public function createRefreshTokenCookie(string $refreshToken): Cookie
    {
        return cookie(
            'refresh_token',
            $refreshToken,
            config('auth.tokens.refresh_token_days') * 24 * 60,
            null,
            null,
            request()->secure(),
            true,
            false,
            'lax'
        );
    }

    /**
     * Extrae el refresh token de la cookie o del body de la petición.
     */
    public function extractRefreshToken(Request $request): ?string
    {
        return $request->cookie('refresh_token') ?? $request->input('refresh_token');
    }

    /**
     * Valida un token de refresco y genera un nuevo par de tokens.
     *
     * @throws GeneralException
     */
    public function refreshAccessToken(string $refreshToken): array
    {
        $tokenInstance = PersonalAccessToken::findToken($refreshToken);

        if (! $tokenInstance || ! $tokenInstance->tokenable || ! $tokenInstance->can('refresh')) {
            throw GeneralException::unauthorized('Token de refresco inválido o expirado.');
        }

        if ($tokenInstance->expires_at && now()->greaterThan($tokenInstance->expires_at)) {
            throw GeneralException::unauthorized('Token de refresco expirado.');
        }

        $user = $tokenInstance->tokenable;

        // Rotar el token: eliminar el token de refresco anterior para evitar reuso
        $tokenInstance->delete();

        $tokens = $this->generateTokenPair($user);

        return [
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_in' => config('auth.tokens.access_token_hours') * 3600,
            'message' => 'Tokens renovados exitosamente.',
        ];
    }

    /**
     * Método validateToken.
     */
    public function validateToken(string $token): array
    {
        $tokenInstance = PersonalAccessToken::findToken($token);

        if (! $tokenInstance || ! $tokenInstance->tokenable) {
            throw GeneralException::unauthorized('Token inválido o no encontrado.');
        }

        if ($tokenInstance->expires_at && now()->greaterThan($tokenInstance->expires_at)) {
            throw GeneralException::unauthorized('El token ha expirado.');
        }

        $user = $tokenInstance->tokenable;

        return [
            'valid' => true,
            'token_id' => $tokenInstance->id,
            'name' => $tokenInstance->name,
            'abilities' => $tokenInstance->abilities,
            'expires_at' => $tokenInstance->expires_at ? $tokenInstance->expires_at->toIso8601String() : null,
            'user' => $this->buildUserPayload($user),
        ];
    }

    /**
     * Obtiene la información detallada del usuario autenticado.
     *
     * @return array<string, mixed>
     */
    public function getCurrentUser(User $user): array
    {
        return [
            'user' => $this->buildUserPayload($user),
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'subscription' => null,
            'message' => 'Información del usuario obtenida exitosamente.',
        ];
    }

    /**
     * Termina la sesión del usuario revocando el token actual.
     */
    public function terminateUserSession(User $user): void
    {
        $currentToken = $user->currentAccessToken();
        if ($currentToken instanceof PersonalAccessToken) {
            // Registrar la actividad de cierre de sesión con Spatie
            activity()
                ->performedOn($user)
                ->causedBy($user)
                ->event('logout')
                ->withProperties([
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent() ?? 'N/A',
                ])
                ->log('cerró sesión');

            $currentToken->delete();
        }
    }

    /**
     * Obtiene las sesiones activas del usuario.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActiveSessions(User $user, ?int $currentTokenId = null): array
    {
        $sessions = $user->tokens()
            ->where('name', 'access_token')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->orderBy('last_used_at', 'desc')
            ->get();

        return $sessions->map(function (PersonalAccessToken $token) use ($currentTokenId) {
            return [
                'id' => $token->id,
                'name' => $token->name,
                'last_used_at' => $token->last_used_at ? $token->last_used_at->toIso8601String() : null,
                'created_at' => $token->created_at ? $token->created_at->toIso8601String() : null,
                'expires_at' => $token->expires_at ? $token->expires_at->toIso8601String() : null,
                'is_current' => $currentTokenId !== null && $token->id === $currentTokenId,
            ];
        })->toArray();
    }

    /**
     * Revoca un token específico del usuario.
     *
     * @throws GeneralException
     */
    public function revokeUserToken(User $user, int $tokenId): void
    {
        $token = $user->tokens()->find($tokenId);

        if (! $token) {
            throw GeneralException::notFound('El token especificado no existe o no pertenece al usuario.');
        }

        $token->delete();
    }

    /**
     * Revoca todos los tokens del usuario, opcionalmente exceptuando el actual.
     */
    public function revokeAllUserTokens(User $user, ?int $currentTokenId = null): void
    {
        if ($currentTokenId !== null) {
            // Revocar todos los tokens excepto el actual
            $user->tokens()->where('id', '!=', $currentTokenId)->delete();
        } else {
            // Revocar todos los tokens
            $user->tokens()->delete();
        }
    }

    /**
     * Obtiene los detalles de expiración del token.
     *
     * @return array<string, mixed>
     */
    public function getTokenExpirationDetails(PersonalAccessToken $token): array
    {
        $expiresAt = $token->expires_at;

        if (! $expiresAt) {
            return [
                'expires_at' => null,
                'seconds_remaining' => null,
                'is_expired' => false,
                'message' => 'El token no expira.',
            ];
        }

        $secondsRemaining = max(0, now()->diffInSeconds($expiresAt, false));

        return [
            'expires_at' => $expiresAt->toIso8601String(),
            'seconds_remaining' => $secondsRemaining,
            'is_expired' => $secondsRemaining <= 0,
        ];
    }

    /**
     * Extiende la validez de la sesión actual sumando las horas de validez por defecto.
     *
     * @return array<string, mixed>
     */
    public function extendTokenSession(PersonalAccessToken $token): array
    {
        $newExpiration = now()->addHours(config('auth.tokens.access_token_hours'));
        $token->update([
            'expires_at' => $newExpiration,
        ]);

        return [
            'extended' => true,
            'expires_at' => $newExpiration->toIso8601String(),
            'message' => 'Sesión extendida exitosamente.',
        ];
    }

    /**
     * Construye el payload centralizado del usuario, inyectando dependencias de terceros si es conductor.
     *
     * @return array<string, mixed>
     */
    private function buildUserPayload(User $user): array
    {
        $companies = $user->companies()->wherePivot('is_active', true)->get();
        $defaultCompany = $companies->first();

        $thirdPartyUuid = $defaultCompany?->pivot->third_party_uuid;
        $uuidDriver = null;

        if ($user->hasRole('CONDUCTOR') && $thirdPartyUuid) {
            $uuidDriver = $thirdPartyUuid; // Guardamos el UUID original del conductor

            // Buscamos el propietario/afiliado vinculado a la licencia del conductor
            $affiliate = OwnerDriver::withoutGlobalScopes()->whereIn(
                'driver_license_uuid',
                DriverLicense::withoutGlobalScopes()
                    ->where('third_party_uuid', $uuidDriver)
                    ->whereIn('status', ['VIGENTE', 'ACTIVA'])
                    ->pluck('uuid')
            )->first();

            if ($affiliate) {
                // Reemplazamos el third_party_uuid por el del propietario/afiliado
                $thirdPartyUuid = $affiliate->third_party_uuid;
            }
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->user_name,
            'company_uuid' => $defaultCompany?->uuid,
            'logo' => $defaultCompany?->logo_url,
            'third_party_uuid' => $thirdPartyUuid,
            'uuid_driver' => $uuidDriver,
            'companies' => $companies->map(function ($c) {
                return [
                    'uuid' => $c->uuid,
                    'name' => $c->business_name,
                    'third_party_uuid' => $c->pivot->third_party_uuid,
                    'logo' => $c->logo_url,
                ];
            })->toArray(),
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ];
    }
}
