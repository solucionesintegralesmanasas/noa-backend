<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\AuthenticationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use OpenApi\Attributes as OA;
use Throwable;

/**
 * Controlador API para la gestión de autenticación y sesiones de usuario.
 *
 * Expone endpoints para login, logout, refresh de tokens, consulta del usuario
 * autenticado y administración de sesiones activas.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-29
 */
class AuthController extends Controller
{
    /**
     * Constructor del controlador.
     */
    public function __construct(
        private readonly AuthenticationService $authService
    ) {}

    /**
     * Autentica un usuario con sus credenciales y genera un par de tokens de acceso y refresco.
     * El token de refresco se establece de forma segura como una cookie HTTP-only.
     *
     * @throws Throwable
     */
    #[OA\Post(
        path: '/api/v1/login',
        summary: 'Iniciar sesión con credenciales de usuario',
        operationId: 'loginUser',
        tags: ['Autenticación'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', example: 'usuario@empresa.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'secret123'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Login exitoso. Retorna access_token, expires_in y datos del usuario. El refresh_token se envía en cookie HTTP-only.',
            ),
            new OA\Response(response: 401, description: 'Credenciales incorrectas.'),
            new OA\Response(response: 422, description: 'Error de validación.'),
            new OA\Response(response: 429, description: 'Demasiados intentos fallidos.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $credentials = $request->validated();
            $result = $this->authService->authenticateUser($credentials);

            // Manejo de flujo 2FA (si fuera necesario extenderlo)
            if (isset($result['status']) && $result['status'] === 'two_factor_required') {
                return $this->successResponse($result, $result['message']);
            }

            // Creamos la cookie HTTP-only para el refresh token
            $refreshCookie = $this->authService->createRefreshTokenCookie($result['refresh_token']);

            // Retornamos la respuesta estandarizada
            return $this->successResponse([
                'access_token' => $result['access_token'],
                'expires_in' => $result['expires_in'],
                'token_type' => 'Bearer',
                'user' => $result['user'],
            ], '¡Bienvenido! Login exitoso.')->withCookie($refreshCookie);
        } catch (Throwable $e) {
            return $this->handleException($e, [
                'action' => 'login',
                'email' => $request->get('email'),
                'ip' => $request->ip(),
            ]);
        }
    }

    /**
     * Renueva el token de acceso utilizando un token de refresco existente.
     *
     * @throws Throwable
     */
    #[OA\Post(
        path: '/api/v1/refresh-token',
        summary: 'Renovar el access token mediante el refresh token',
        operationId: 'refreshToken',
        tags: ['Autenticación'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Tokens renovados exitosamente. El nuevo refresh_token se envía en cookie HTTP-only.',
            ),
            new OA\Response(response: 401, description: 'Refresh token no proporcionado, inválido o expirado.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function refresh(Request $request): JsonResponse
    {
        try {
            $refreshToken = $this->authService->extractRefreshToken($request);

            if (! $refreshToken) {
                return $this->unauthorizedResponse('Refresh token no proporcionado.');
            }

            $result = $this->authService->refreshAccessToken($refreshToken);
            $refreshCookie = $this->authService->createRefreshTokenCookie($result['refresh_token']);

            return $this->successResponse([
                'access_token' => $result['access_token'],
                'expires_in' => $result['expires_in'],
                'token_type' => 'Bearer',
            ], $result['message'])->withCookie($refreshCookie);
        } catch (Throwable $e) {
            return $this->handleException($e, [
                'action' => 'refresh_token',
                'ip' => $request->ip(),
            ]);
        }
    }

    /**
     * Retorna la información detallada del usuario autenticado actual.
     *
     * @throws Throwable
     */
    #[OA\Get(
        path: '/api/v1/me',
        summary: 'Obtener información del usuario autenticado',
        operationId: 'getAuthenticatedUser',
        tags: ['Autenticación'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Información del usuario obtenida exitosamente. Incluye roles y permisos.',
            ),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function me(Request $request): JsonResponse
    {
        try {
            $result = $this->authService->getCurrentUser($request->user());

            return $this->successResponse($result, $result['message']);
        } catch (Throwable $e) {
            return $this->handleException($e, [
                'action' => 'me',
                'user_id' => $request->user()?->id,
            ]);
        }
    }

    /**
     * Cierra la sesión activa revocando el token de acceso utilizado para la petición.
     * También purga la cookie segura del refresh token del cliente.
     *
     * @throws Throwable
     */
    #[OA\Post(
        path: '/api/v1/logout',
        summary: 'Cerrar sesión y revocar el token activo',
        operationId: 'logoutUser',
        tags: ['Autenticación'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Sesión cerrada exitosamente.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        try {
            $this->authService->terminateUserSession($request->user());

            // Olvidamos la cookie al hacer logout
            return $this->successResponse(null, 'Sesión cerrada exitosamente.')
                ->withCookie(cookie()->forget('refresh_token'));
        } catch (Throwable $e) {
            return $this->handleException($e, [
                'action' => 'logout',
                'user_id' => $request->user()?->id,
            ]);
        }
    }

    /**
     * Valida un token de acceso recibido en la petición.
     *
     * @throws Throwable
     */
    #[OA\Post(
        path: '/api/v1/validate-token',
        summary: 'Validar un token de acceso Bearer',
        operationId: 'validateToken',
        tags: ['Autenticación'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Token válido. Retorna metadatos del token y del usuario propietario.',
            ),
            new OA\Response(response: 401, description: 'Token no proporcionado, inválido o expirado.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function validateToken(Request $request): JsonResponse
    {
        try {
            $token = $request->bearerToken() ?? $request->input('token');

            if (! $token) {
                return $this->unauthorizedResponse('Token no proporcionado.');
            }

            $result = $this->authService->validateToken($token);

            return $this->successResponse($result, 'Token válido.');
        } catch (Throwable $e) {
            return $this->handleException($e, [
                'action' => 'validate_token',
                'ip' => $request->ip(),
            ]);
        }
    }

    /**
     * Devuelve la lista de sesiones activas del usuario autenticado.
     *
     * @throws Throwable
     */
    #[OA\Get(
        path: '/api/v1/active-sessions',
        summary: 'Listar sesiones activas del usuario autenticado',
        operationId: 'listActiveSessions',
        tags: ['Autenticación'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Listado de sesiones activas obtenido exitosamente.',
            ),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function activeSessions(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $currentToken = $user->currentAccessToken();
            $currentTokenId = ($currentToken instanceof PersonalAccessToken) ? $currentToken->id : null;

            $sessions = $this->authService->getActiveSessions($user, $currentTokenId);

            return $this->successResponse($sessions, 'Sesiones activas obtenidas exitosamente.');
        } catch (Throwable $e) {
            return $this->handleException($e, [
                'action' => 'active_sessions',
                'user_id' => $request->user()?->id,
            ]);
        }
    }

    /**
     * Revoca un token específico (cierre de sesión remoto de un dispositivo).
     *
     * @throws Throwable
     */
    #[OA\Post(
        path: '/api/v1/revoke-token',
        summary: 'Revocar un token de sesión específico',
        operationId: 'revokeToken',
        tags: ['Autenticación'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['token_id'],
                properties: [
                    new OA\Property(property: 'token_id', type: 'integer', description: 'ID del token a revocar', example: 42),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Sesión revocada exitosamente.'),
            new OA\Response(response: 400, description: 'El ID del token es requerido.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 404, description: 'Token no encontrado o no pertenece al usuario.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function revokeToken(Request $request): JsonResponse
    {
        try {
            $tokenId = $request->input('token_id');

            if (! $tokenId) {
                return $this->errorResponse('El ID del token es requerido.', 400);
            }

            $user = $request->user();
            $this->authService->revokeUserToken($user, (int) $tokenId);

            return $this->successResponse(null, 'Sesión revocada exitosamente.');
        } catch (Throwable $e) {
            return $this->handleException($e, [
                'action' => 'revoke_token',
                'user_id' => $request->user()?->id,
                'token_id' => $request->input('token_id'),
            ]);
        }
    }

    /**
     * Revoca todas las sesiones activas del usuario autenticado.
     *
     * @throws Throwable
     */
    #[OA\Post(
        path: '/api/v1/revoke-all-tokens',
        summary: 'Revocar todas las sesiones activas del usuario',
        operationId: 'revokeAllTokens',
        tags: ['Autenticación'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Todas las sesiones cerradas exitosamente.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function revokeAllTokens(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $currentToken = $user->currentAccessToken();
            $currentTokenId = $currentToken ? $currentToken->id : null;

            // Por seguridad revocamos todas las sesiones (incluyendo la actual, como se recomendó)
            $this->authService->revokeAllUserTokens($user);

            return $this->successResponse(null, 'Todas las sesiones han sido cerradas exitosamente.');
        } catch (Throwable $e) {
            return $this->handleException($e, [
                'action' => 'revoke_all_tokens',
                'user_id' => $request->user()?->id,
            ]);
        }
    }

    /**
     * Muestra la información del token de acceso actual utilizado para la petición.
     *
     * @throws Throwable
     */
    #[OA\Get(
        path: '/api/v1/token-info',
        summary: 'Obtener metadatos del token de acceso actual',
        operationId: 'getTokenInfo',
        tags: ['Autenticación'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Información del token obtenida exitosamente. Incluye ID, nombre, abilities, expiración y usuario.',
            ),
            new OA\Response(response: 401, description: 'Token de acceso no válido o no encontrado.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function tokenInfo(Request $request): JsonResponse
    {
        try {
            $tokenInstance = $request->user()->currentAccessToken();

            if (! $tokenInstance) {
                return $this->unauthorizedResponse('Token de acceso no válido o no encontrado.');
            }

            $info = $this->authService->validateToken($request->bearerToken());

            return $this->successResponse($info, 'Información del token obtenida exitosamente.');
        } catch (Throwable $e) {
            return $this->handleException($e, [
                'action' => 'token_info',
                'user_id' => $request->user()?->id,
            ]);
        }
    }

    /**
     * Verifica la expiración y tiempo restante del token actual.
     *
     * @throws Throwable
     */
    #[OA\Get(
        path: '/api/v1/check-token-expiration',
        summary: 'Verificar estado de expiración del token actual',
        operationId: 'checkTokenExpiration',
        tags: ['Autenticación'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Detalles de expiración obtenidos. Incluye expires_at, seconds_remaining e is_expired.',
            ),
            new OA\Response(response: 401, description: 'Token no válido o no encontrado.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function checkTokenExpiration(Request $request): JsonResponse
    {
        try {
            $tokenInstance = $request->user()->currentAccessToken();

            if (! $tokenInstance) {
                return $this->unauthorizedResponse('Token no válido o no encontrado.');
            }

            $details = $this->authService->getTokenExpirationDetails($tokenInstance);

            return $this->successResponse($details, 'Detalles de expiración del token obtenidos.');
        } catch (Throwable $e) {
            return $this->handleException($e, [
                'action' => 'check_token_expiration',
                'user_id' => $request->user()?->id,
            ]);
        }
    }

    /**
     * Extiende el tiempo de vida (expiración) del token actual de la sesión.
     *
     * @throws Throwable
     */
    #[OA\Post(
        path: '/api/v1/extend-session',
        summary: 'Extender la validez del token de acceso actual',
        operationId: 'extendSession',
        tags: ['Autenticación'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Sesión extendida exitosamente. Retorna la nueva fecha de expiración.',
            ),
            new OA\Response(response: 401, description: 'Token no válido o no encontrado.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function extendSession(Request $request): JsonResponse
    {
        try {
            $tokenInstance = $request->user()->currentAccessToken();

            if (! $tokenInstance) {
                return $this->unauthorizedResponse('Token no válido o no encontrado.');
            }

            $result = $this->authService->extendTokenSession($tokenInstance);

            return $this->successResponse($result, 'Sesión extendida exitosamente.');
        } catch (Throwable $e) {
            return $this->handleException($e, [
                'action' => 'extend_session',
                'user_id' => $request->user()?->id,
            ]);
        }
    }
}
