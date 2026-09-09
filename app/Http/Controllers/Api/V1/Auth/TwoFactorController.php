<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Controlador API para la gestión del Doble Factor de Autenticación (2FA).
 *
 * Expone el flujo completo de 2FA: activación provisional (enable), confirmación
 * con el primer código TOTP (confirm), verificación durante el login (verify)
 * y desactivación de la funcionalidad (disable).
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-29
 */
class TwoFactorController extends Controller
{
    /**
     * Constructor del controlador.
     */
    public function __construct(
        protected TwoFactorService $twoFactorService
    ) {}

    /**
     * Inicia el proceso de activación de 2FA generando el código QR y secreto provisionales.
     *
     * @throws Throwable
     */
    #[OA\Get(
        path: '/api/v1/2fa/enable',
        summary: 'Iniciar activación de 2FA: obtener QR y secreto provisional',
        operationId: 'enableTwoFactor',
        tags: ['Autenticación 2FA'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Configuración 2FA generada. Retorna qr_code, secret y uri.'),
            new OA\Response(response: 400, description: 'El 2FA ya se encuentra activo en esta cuenta.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function enable(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if ($user->hasTwoFactorEnabled()) {
                return $this->errorResponse(
                    'La autenticación de doble factor ya se encuentra activa en esta cuenta.',
                    Response::HTTP_BAD_REQUEST,
                    [],
                    'TWO_FACTOR_ALREADY_ENABLED'
                );
            }

            $secret = $this->twoFactorService->enableForUser($user);

            return $this->successResponse([
                'qr_code' => $secret->toQr(),
                'secret' => $secret->toString(),
                'uri' => $secret->toUri(),
            ], 'Configuración de 2FA generada exitosamente.');
        } catch (Throwable $e) {
            return $this->handleException($e, [
                'action' => '2fa_enable',
                'user_id' => $request->user()?->id,
            ]);
        }
    }

    /**
     * Confirma la activación del doble factor (2FA) validando el primer código TOTP.
     *
     * @throws Throwable
     */
    #[OA\Post(
        path: '/api/v1/2fa/confirm',
        summary: 'Confirmar activación de 2FA con el primer código TOTP',
        operationId: 'confirmTwoFactor',
        tags: ['Autenticación 2FA'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['code'],
                properties: [
                    new OA\Property(property: 'code', type: 'string', minLength: 6, maxLength: 6, description: 'Código TOTP de 6 dígitos', example: '123456'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '2FA activado. Retorna los códigos de recuperación de un solo uso.'),
            new OA\Response(response: 400, description: 'El 2FA ya estaba confirmado y activo.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 422, description: 'Código TOTP incorrecto o expirado.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function confirm(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'code' => 'required|string|size:6',
            ]);

            $user = $request->user();
            if ($user->hasTwoFactorEnabled()) {
                return $this->errorResponse(
                    'La autenticación de doble factor ya está confirmada y activa.',
                    Response::HTTP_BAD_REQUEST,
                    [],
                    'TWO_FACTOR_ALREADY_CONFIRMED'
                );
            }

            if (! $this->twoFactorService->confirmForUser($user, $request->code)) {
                return $this->errorResponse(
                    'El código de verificación de 6 dígitos introducido es incorrecto o ha expirado.',
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    ['code' => ['El código introducido no es válido']],
                    'VALIDATION_ERROR'
                );
            }

            return $this->successResponse([
                'recovery_codes' => $user->getRecoveryCodes(),
            ], 'Autenticación de dos factores activada y confirmada exitosamente. Guarde sus códigos de recuperación.');
        } catch (Throwable $e) {
            return $this->handleException($e, [
                'action' => '2fa_confirm',
                'user_id' => $request->user()?->id,
            ]);
        }
    }

    /**
     * Verifica el código 2FA durante el inicio de sesión (desafío) y emite el token final.
     *
     * @throws Throwable
     */
    #[OA\Post(
        path: '/api/v1/2fa/verify',
        summary: 'Verificar código 2FA en el desafío de login y obtener token definitivo',
        operationId: 'verifyTwoFactor',
        tags: ['Autenticación 2FA'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['code'],
                properties: [
                    new OA\Property(property: 'code', type: 'string', description: 'Código TOTP de 6 dígitos o código de recuperación', example: '654321'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Login completado. Retorna token definitivo y si se usó código de recuperación.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 403, description: 'El token no posee el permiso 2fa:challenge.'),
            new OA\Response(response: 422, description: 'Código TOTP o de recuperación incorrecto.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function verify(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'code' => 'required|string',
            ]);

            $user = $request->user();

            if (! $user->tokenCan('2fa:challenge')) {
                return $this->errorResponse(
                    'Permiso denegado. El token actual no posee permisos para el desafío 2FA.',
                    Response::HTTP_FORBIDDEN,
                    [],
                    'FORBIDDEN'
                );
            }

            $result = $this->twoFactorService->verifyForUser($user, $request->code);

            if (! $result) {
                return $this->errorResponse(
                    'El código de autenticación o de recuperación ingresado es incorrecto.',
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    ['code' => ['El código introducido no es válido']],
                    'VALIDATION_ERROR'
                );
            }

            return $this->successResponse([
                'user' => $user,
                'token' => $result['token'],
                'two_factor_active' => true,
                'used_recovery_code' => $result['is_recovery'],
            ], 'Inicio de sesión completado. Doble factor verificado.');
        } catch (Throwable $e) {
            return $this->handleException($e, [
                'action' => '2fa_verify',
                'user_id' => $request->user()?->id,
            ]);
        }
    }

    /**
     * Desactiva el doble factor de autenticación (2FA) para la cuenta del usuario.
     *
     * @throws Throwable
     */
    #[OA\Post(
        path: '/api/v1/2fa/disable',
        summary: 'Desactivar 2FA de la cuenta con confirmación de contraseña',
        operationId: 'disableTwoFactor',
        tags: ['Autenticación 2FA'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['password'],
                properties: [
                    new OA\Property(property: 'password', type: 'string', format: 'password', description: 'Contraseña actual para confirmar la desactivación', example: 'secret123'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '2FA desactivado correctamente de la cuenta.'),
            new OA\Response(response: 400, description: 'El 2FA ya se encontraba inactivo.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function disable(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'password' => 'required|string',
            ]);

            $user = $request->user();

            if (! $user->hasTwoFactorEnabled()) {
                return $this->errorResponse(
                    'La autenticación de doble factor ya se encuentra inactiva.',
                    Response::HTTP_BAD_REQUEST,
                    [],
                    'TWO_FACTOR_NOT_ENABLED'
                );
            }

            $this->twoFactorService->disableForUser($user, $request->password);

            return $this->successResponse(
                null,
                'Autenticación de dos factores desactivada correctamente de su cuenta.'
            );
        } catch (Throwable $e) {
            return $this->handleException($e, [
                'action' => '2fa_disable',
                'user_id' => $request->user()?->id,
            ]);
        }
    }
}
