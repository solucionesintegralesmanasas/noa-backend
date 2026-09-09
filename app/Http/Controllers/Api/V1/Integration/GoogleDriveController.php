<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Integration;

use App\Http\Controllers\Controller;
use App\Services\Integration\GoogleDriveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use OpenApi\Attributes as OA;
use Throwable;

/**
 * Clase GoogleOAuthController
 *
 * Controlador para gestionar el inicio de sesión y vinculación con Google Drive mediante OAuth2.
 */
class GoogleDriveController extends Controller
{
    /**
     * Constructor del controlador.
     */
    public function __construct(
        private readonly GoogleDriveService $googleService
    ) {}

    /**
     * Retorna la URL a la que el frontend debe redirigir al usuario para iniciar sesión con Google.
     * Pide permisos específicos para Google Drive.
     *
     * @throws Throwable
     */
    #[OA\Get(
        path: '/api/v1/integrations/google-drive/authorize',
        summary: 'Redirigir a Google para autorización',
        operationId: 'redirectToGoogleGoogleDrive',
        tags: ['GoogleDrive'],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function redirectToGoogle(Request $request): JsonResponse
    {
        try {
            // Generamos la URL de redirección usando Socialite
            // Pedimos acceso offline para obtener el refresh_token y el scope de Drive
            /** @var GoogleProvider $driver */
            $driver = Socialite::driver('google');

            $url = $driver->scopes(['https://www.googleapis.com/auth/drive.file'])
                ->with(['access_type' => 'offline', 'prompt' => 'consent'])
                ->stateless()
                ->redirect()
                ->getTargetUrl();

            return $this->successResponse([
                'redirect_url' => $url,
            ], 'URL de redirección generada exitosamente.');
        } catch (Throwable $e) {
            return $this->handleException($e, [
                'action' => 'redirect_to_google',
                'ip' => $request->ip(),
            ]);
        }
    }

    /**
     * Recibe la respuesta de Google con el código de autorización, obtiene los tokens
     * y los guarda en el usuario actual.
     *
     * @throws Throwable
     */
    #[OA\Get(
        path: '/api/v1/integrations/google-drive/callback',
        summary: 'Callback de autenticación de Google Drive',
        operationId: 'handleCallbackGoogleDrive',
        tags: ['GoogleDrive'],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function handleCallback(Request $request): JsonResponse
    {
        try {
            /** @var GoogleProvider $driver */
            $driver = Socialite::driver('google');
            $googleUser = $driver->stateless()->user();

            // Usamos el servicio para vincular la cuenta al usuario autenticado actual
            $user = $request->user();

            if (! $user) {
                return $this->unauthorizedResponse('Debes iniciar sesión primero para vincular tu cuenta de Google.');
            }

            $result = $this->googleService->linkGoogleAccountToUser($user, $googleUser);

            return $this->successResponse($result, $result['message']);
        } catch (Throwable $e) {
            return $this->handleException($e, [
                'action' => 'google_oauth_callback',
                'ip' => $request->ip(),
            ]);
        }
    }

    /**
     * Obtiene la cantidad de almacenamiento usado y disponible en Google Drive.
     *
     * @throws Throwable
     */
    #[OA\Get(
        path: '/api/v1/integrations/google-drive/storage-quota',
        summary: 'Obtener cuota de almacenamiento de Google Drive',
        operationId: 'getStorageQuotaGoogleDrive',
        tags: ['GoogleDrive'],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function getStorageQuota(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user) {
                return $this->unauthorizedResponse('Debes iniciar sesión primero.');
            }

            $result = $this->googleService->getStorageQuota($user);

            return $this->successResponse($result['data'], $result['message']);
        } catch (Throwable $e) {
            return $this->handleException($e, [
                'action' => 'google_drive_storage_quota',
                'ip' => $request->ip(),
            ]);
        }
    }
}
