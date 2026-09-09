<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Exceptions\GeneralException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Método enableForUser.
 *
 * @param  App\Models\User  $user
 * @return mixed|null
 */
class TwoFactorService
{
    /**
     * Método confirmForUser.
     *
     * @param  App\Models\User  $user
     * @param  string  $code
     * @return mixed|null
     */
    public function enableForUser(User $user): mixed
    {
        return $user->createTwoFactorAuth();
    }

    /**
     * Método verifyForUser.
     *
     * @param  App\Models\User  $user
     */
    public function confirmForUser(User $user, string $code): mixed
    {
        return $user->confirmTwoFactorAuth($code);
    }

    /**
     * Verifica el código de autenticación de doble factor y regenera el token de acceso.
     *
     * @return array<string, mixed>|bool
     */
    public function verifyForUser(User $user, string $code): array|bool
    {
        // Validar si es código de recuperación o TOTP
        $isValid = $user->validateTwoFactorCode($code);
        $isRecovery = $isValid && ! (strlen($code) === 6 && is_numeric($code));

        if ($isValid) {
            $currentToken = $user->currentAccessToken();
            if ($currentToken instanceof PersonalAccessToken) {
                $currentToken->delete();
            }

            return [
                'token' => $user->createToken('auth_token', ['*'])->plainTextToken,
                'is_recovery' => $isRecovery,
            ];
        }

        return false;
    }

    /**
     * Desactiva el doble factor de autenticación (2FA) para el usuario tras validar su contraseña.
     *
     *
     * @throws GeneralException
     */
    public function disableForUser(User $user, string $password): void
    {
        if (! Hash::check($password, $user->password)) {
            throw GeneralException::unauthorized('La contraseña introducida es incorrecta');
        }

        $user->disableTwoFactorAuth();
    }
}
