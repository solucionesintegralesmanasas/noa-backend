<?php

namespace App\Http\Middleware;

use App\Enum\ApiErrorCode;
use App\Exceptions\GeneralException;
use App\Traits\HandlesApiResponse;
use Closure;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * @class ApiErrorHandler
 *
 * @brief Middleware global que intercepta todas las excepciones de la API y las
 *        convierte en respuestas JSON estandarizadas usando el ecosistema existente:
 *        HandlesApiResponse, GeneralException y ApiErrorCode.
 *
 * @author Darwin Montes Lopez
 *
 * @version 1.0.0
 */
class ApiErrorHandler
{
    use HandlesApiResponse;

    /**
     * Handle an incoming request.
     *
     * Captura cualquier excepción lanzada durante el ciclo de vida de la solicitud
     * y la convierte en una respuesta JSON uniforme para el cliente API.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $next($request);
        } catch (ValidationException $e) {
            // Errores de validación de Form Requests → 422
            return $this->validationErrorResponse($e->errors(), $e->getMessage());

        } catch (GeneralException $e) {
            // Excepción de negocio personalizada: usa su httpStatus y detalles propios
            $errorCode = ApiErrorCode::EXCEPTION_CODE_ERROR[$e->getStatusCode()]
                ?? 'INTERNAL_SERVER_ERROR';

            return $this->errorResponse(
                message: $e->getMessage(),
                status: $e->getStatusCode(),
                errors: $e->getDetails(),
                errorCode: $errorCode,
                exception: $e
            );

        } catch (AuthenticationException $e) {
            // Token inválido / usuario no autenticado → 401
            return $this->unauthorizedResponse($e->getMessage() ?: 'No autenticado.');

        } catch (AuthorizationException $e) {
            // Política de Gate / Policy fallida → 403
            return $this->forbiddenResponse($e->getMessage() ?: 'Acceso prohibido.');

        } catch (ModelNotFoundException $e) {
            // Modelo Eloquent no encontrado (findOrFail) → 404
            $model = class_basename($e->getModel());

            return $this->notFoundResponse(
                message: "El recurso '{$model}' no fue encontrado.",
                resourceType: $model
            );

        } catch (NotFoundHttpException $e) {
            // Ruta no registrada → 404
            return $this->notFoundResponse('El endpoint solicitado no existe.');

        } catch (MethodNotAllowedHttpException $e) {
            // Método HTTP no soportado en la ruta → 405
            return $this->errorResponse(
                message: 'El método HTTP utilizado no está permitido para este endpoint.',
                status: Response::HTTP_METHOD_NOT_ALLOWED,
                errorCode: 'OPERATION_NOT_ALLOWED'
            );

        } catch (TooManyRequestsHttpException $e) {
            // Rate limiting → 429
            $retryAfter = $e->getHeaders()['Retry-After'] ?? null;

            return $this->tooManyRequestsResponse(
                message: 'Demasiadas solicitudes. Por favor, espera antes de reintentar.',
                retryAfter: $retryAfter ? (int) $retryAfter : null
            );

        } catch (QueryException $e) {
            // Error de base de datos → 500
            return $this->databaseErrorResponse(
                $e,
                'Error al procesar la operación en la base de datos.'
            );

        } catch (HttpException $e) {
            // Cualquier otra HttpException de Symfony → usa su código HTTP
            $errorCode = ApiErrorCode::EXCEPTION_CODE_ERROR[$e->getStatusCode()]
                ?? 'INTERNAL_SERVER_ERROR';

            return $this->errorResponse(
                message: $e->getMessage() ?: $this->defaultHttpMessage($e->getStatusCode()),
                status: $e->getStatusCode(),
                errorCode: $errorCode
            );

        } catch (Exception $e) {
            // Cualquier excepción de PHP estándar → delegar al trait
            return $this->handleException($e);

        } catch (Throwable $e) {
            // Errores fatales, TypeErrors, etc. que no extienden Exception → 500
            return $this->errorResponse(
                message: app()->environment('production')
                    ? 'Ha ocurrido un error interno. Por favor, intente más tarde.'
                    : $e->getMessage(),
                status: Response::HTTP_INTERNAL_SERVER_ERROR,
                errorCode: 'INTERNAL_SERVER_ERROR'
            );
        }
    }

    /**
     * Mensaje HTTP por defecto según código de estado.
     * Complementa los casos no contemplados por HandlesApiResponse.
     */
    private function defaultHttpMessage(int $status): string
    {
        return match ($status) {
            Response::HTTP_BAD_REQUEST => 'Solicitud incorrecta.',
            Response::HTTP_UNAUTHORIZED => 'No autenticado.',
            Response::HTTP_FORBIDDEN => 'Acceso prohibido.',
            Response::HTTP_NOT_FOUND => 'Recurso no encontrado.',
            Response::HTTP_METHOD_NOT_ALLOWED => 'Método no permitido.',
            Response::HTTP_CONFLICT => 'Conflicto con el estado del recurso.',
            Response::HTTP_UNPROCESSABLE_ENTITY => 'Los datos proporcionados no son válidos.',
            Response::HTTP_TOO_MANY_REQUESTS => 'Demasiadas solicitudes.',
            Response::HTTP_INTERNAL_SERVER_ERROR => 'Error interno del servidor.',
            Response::HTTP_SERVICE_UNAVAILABLE => 'Servicio temporalmente no disponible.',
            default => 'Error inesperado.',
        };
    }
}
