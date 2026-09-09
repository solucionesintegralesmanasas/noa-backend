<?php

namespace App\Exceptions;

use App\Traits\HandlesApiResponse;
use App\Utils\Logger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\MissingAttributeException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\RelationNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use PDOException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Clase: Handler
 *
 * @author  Darwin Montes Lopez
 *
 * @version 4.0.3 — Laravel 12 (CRUD Exception Handling Complete)
 *
 * CHANGELOG v4.0.3:
 * - [FIX] Renombrado renderHttpException() → handleHttpException() para evitar conflicto
 *         con el método protegido de la clase padre ExceptionHandler, que retorna
 *         \Symfony\Component\HttpFoundation\Response y no puede ser sobreescrito
 *         con retorno JsonResponse (firmas incompatibles).
 * - [FIX] renderDeadlockException(): getSql() y getBindings() se acceden casteando
 *         DeadlockException a QueryException, que es su clase padre real. Esto resuelve
 *         el "Undefined method" que reporta el analizador estático del IDE en Laravel 12.
 *
 * CHANGELOG v4.0.2:
 * - [FIX] renderHttpException() — incompatibilidad de tipo con la clase padre.
 * - [FIX] Deadlock: eliminados method_exists() innecesarios.
 * - [FIX] logException(): el bloque QueryException cubre DeadlockException por herencia.
 *
 * CHANGELOG v4.0.1:
 * - [FIX] RelationNotFoundException::$model y $relation son propiedades públicas,
 *         no métodos getter.
 *
 * CHANGELOG v4.0.0:
 * - [ADD] Manejo completo de excepciones de Eloquent y base de datos.
 * - [ADD] Renderizado específico para cada tipo de excepción CRUD.
 * - [IMPROVE] Logging mejorado con contexto específico por tipo de excepción.
 */
class Handler extends ExceptionHandler
{
    use HandlesApiResponse;

    /**
     * Niveles de log personalizados por tipo de excepción.
     *
     * @var array<class-string<Throwable>, LogLevel::*>
     */
    protected $levels = [
        DeadlockException::class => 'critical',
        QueryException::class => 'error',
        MassAssignmentException::class => 'error',
        RelationNotFoundException::class => 'error',
        MissingAttributeException::class => 'error',
        ValidationException::class => 'warning',
    ];

    // =========================================================================
    // Punto de entrada público — llamado desde bootstrap/app.php
    // =========================================================================

    /**
     * Mapea cualquier Throwable a una JsonResponse estandarizada.
     *
     * Llamado desde el closure $exceptions->render() en bootstrap/app.php.
     * El orden de los bloques importa: de más específico a más genérico.
     * DeadlockException va ANTES de QueryException porque la extiende.
     */
    public function renderForApi(Request $request, Throwable $e): JsonResponse
    {
        $this->logException($e, $request);

        // =====================================================================
        // EXCEPCIONES DE DOMINIO PERSONALIZADAS
        // =====================================================================

        if ($e instanceof GeneralException) {
            return $this->renderGeneralException($e);
        }

        // =====================================================================
        // EXCEPCIONES DE SPATIE QUERY BUILDER (400)
        // =====================================================================

        if (str_starts_with(get_class($e), 'Spatie\QueryBuilder\Exceptions')) {
            return $this->errorResponse(
                $e->getMessage(),
                Response::HTTP_BAD_REQUEST,
                ['hint' => 'Verifique los parámetros enviados en la URL (filter, sort, include, fields).'],
                'QUERY_ARGUMENT_ERROR',
                [],
                $e
            );
        }

        // =====================================================================
        // EXCEPCIONES DE VALIDACIÓN (422)
        // =====================================================================

        if ($e instanceof ValidationException) {
            return $this->renderValidationException($e);
        }

        // =====================================================================
        // EXCEPCIONES DE AUTENTICACIÓN (401)
        // =====================================================================

        if ($e instanceof AuthenticationException) {
            return $this->renderAuthenticationException($e);
        }

        // =====================================================================
        // EXCEPCIONES DE AUTORIZACIÓN (403)
        // =====================================================================

        if ($e instanceof AuthorizationException) {
            return $this->renderAuthorizationException($e);
        }

        // =====================================================================
        // EXCEPCIONES DE RECURSOS (404)
        // =====================================================================

        if ($e instanceof ModelNotFoundException) {
            return $this->renderModelNotFoundException($e);
        }

        if ($e instanceof NotFoundHttpException) {
            return $this->renderNotFoundHttpException($e);
        }

        // =====================================================================
        // EXCEPCIONES HTTP (405, 429, etc.)
        // FIX v4.0.3: Se llama handleHttpException() en lugar de renderHttpException()
        // para no colisionar con el método protegido de la clase padre.
        // =====================================================================

        if ($e instanceof MethodNotAllowedHttpException) {
            return $this->renderMethodNotAllowedException($e);
        }

        if ($e instanceof ThrottleRequestsException) {
            return $this->renderThrottleRequestsException($e);
        }

        if ($e instanceof HttpException) {
            return $this->handleHttpException($e);  // ← nombre propio, sin conflicto
        }

        // =====================================================================
        // EXCEPCIONES DE ELOQUENT (400 - BAD REQUEST)
        // =====================================================================

        if ($e instanceof MassAssignmentException) {
            return $this->renderMassAssignmentException($e);
        }

        if ($e instanceof RelationNotFoundException) {
            return $this->renderRelationNotFoundException($e);
        }

        if ($e instanceof MissingAttributeException) {
            return $this->renderMissingAttributeException($e);
        }

        // =====================================================================
        // EXCEPCIONES DE BASE DE DATOS
        // DeadlockException ANTES de QueryException (es su clase hija).
        // =====================================================================

        if ($e instanceof DeadlockException) {
            return $this->renderDeadlockException($e);
        }

        if ($e instanceof ConnectionException) {
            return $this->renderConnectionException($e);
        }

        if ($e instanceof PDOException) {
            return $this->renderPDOException($e);
        }

        if ($e instanceof QueryException) {
            return $this->renderQueryException($e);
        }

        // =====================================================================
        // EXCEPCIONES DE ARGUMENTOS Y LÓGICA (400 / 500)
        // =====================================================================

        if ($e instanceof \InvalidArgumentException) {
            return $this->renderInvalidArgumentException($e);
        }

        if ($e instanceof \RuntimeException) {
            return $this->renderRuntimeException($e);
        }

        if ($e instanceof \LogicException) {
            return $this->renderLogicException($e);
        }

        // =====================================================================
        // CUALQUIER OTRA EXCEPCIÓN NO MANEJADA (500)
        // =====================================================================

        return $this->renderGenericException($e);
    }

    // =========================================================================
    // MÉTODOS DE RENDERIZADO POR TIPO DE EXCEPCIÓN
    // =========================================================================

    private function renderGeneralException(GeneralException $e): JsonResponse
    {
        return $this->errorResponse(
            $e->getMessage(),
            $e->getStatusCode(),
            $e->getDetails(),
            $this->getErrorCodeFromHttpStatus($e->getStatusCode()),
            [],
            $e
        );
    }

    private function renderValidationException(ValidationException $e): JsonResponse
    {
        return $this->validationErrorResponse($e->errors(), $e->getMessage());
    }

    private function renderAuthenticationException(AuthenticationException $e): JsonResponse
    {
        return $this->unauthorizedResponse(
            $e->getMessage() ?: 'No autenticado. Por favor inicie sesión.'
        );
    }

    private function renderAuthorizationException(AuthorizationException $e): JsonResponse
    {
        return $this->forbiddenResponse(
            $e->getMessage() ?: 'Acceso prohibido. No tiene permisos para realizar esta acción.'
        );
    }

    private function renderModelNotFoundException(ModelNotFoundException $e): JsonResponse
    {
        $modelClass = class_basename($e->getModel());

        return $this->notFoundResponse(
            $e->getMessage() ?: "El recurso '{$modelClass}' no fue encontrado.",
            $modelClass
        );
    }

    private function renderNotFoundHttpException(NotFoundHttpException $e): JsonResponse
    {
        return $this->errorResponse(
            $e->getMessage() ?: 'Endpoint no encontrado. Verifique la URL de la solicitud.',
            Response::HTTP_NOT_FOUND,
            ['path' => request()->path()],
            'ENDPOINT_NOT_FOUND'
        );
    }

    private function renderMethodNotAllowedException(MethodNotAllowedHttpException $e): JsonResponse
    {
        return $this->errorResponse(
            $e->getMessage() ?: 'Método HTTP no permitido para este endpoint.',
            Response::HTTP_METHOD_NOT_ALLOWED,
            ['allowed_methods' => $e->getHeaders()['Allow'] ?? []],
            'METHOD_NOT_ALLOWED'
        );
    }

    private function renderThrottleRequestsException(ThrottleRequestsException $e): JsonResponse
    {
        $retryAfter = isset($e->getHeaders()['Retry-After'])
            ? (int) $e->getHeaders()['Retry-After']
            : null;

        return $this->tooManyRequestsResponse(
            $e->getMessage() ?: 'Demasiadas solicitudes. Por favor, espere antes de reintentar.',
            $retryAfter
        );
    }

    /**
     * Maneja HttpException genérica.
     *
     * FIX v4.0.3: Renombrado de renderHttpException() a handleHttpException() para
     * evitar el conflicto con el método protegido de la clase padre ExceptionHandler,
     * cuya firma retorna \Symfony\Component\HttpFoundation\Response y es incompatible
     * con el retorno JsonResponse requerido aquí. PHP no permite sobreescribir un método
     * heredado con un tipo de retorno diferente.
     */
    private function handleHttpException(HttpException $e): JsonResponse
    {
        return $this->errorResponse(
            $e->getMessage() ?: 'Error HTTP',
            $e->getStatusCode(),
            [
                'http_status' => $e->getStatusCode(),
                'headers' => $e->getHeaders(),
            ],
            null,
            []
        );
    }

    private function renderMassAssignmentException(MassAssignmentException $e): JsonResponse
    {
        return $this->errorResponse(
            'Error de asignación masiva: '.$e->getMessage(),
            Response::HTTP_BAD_REQUEST,
            [
                'hint' => 'Verificar los campos fillable en el modelo',
                'suggestion' => 'Agregue el campo al array $fillable en el modelo o use forceFill()',
            ],
            'MASS_ASSIGNMENT_ERROR',
            [],
            $e
        );
    }

    /**
     * FIX v4.0.1: $model y $relation son propiedades públicas, no métodos getter.
     */
    private function renderRelationNotFoundException(RelationNotFoundException $e): JsonResponse
    {
        return $this->errorResponse(
            'Relación no encontrada: '.$e->getMessage(),
            Response::HTTP_BAD_REQUEST,
            [
                'model' => $e->model,
                'relation' => $e->relation,
                'hint' => 'Verificar que el método de relación existe en el modelo',
            ],
            'RELATION_NOT_FOUND',
            [],
            $e
        );
    }

    private function renderMissingAttributeException(MissingAttributeException $e): JsonResponse
    {
        return $this->errorResponse(
            'Atributo faltante: '.$e->getMessage(),
            Response::HTTP_BAD_REQUEST,
            [
                'hint' => 'Verificar que el atributo existe en la tabla de la base de datos',
            ],
            'MISSING_ATTRIBUTE',
            [],
            $e
        );
    }

    /**
     * FIX v4.0.3: getSql() y getBindings() se acceden casteando DeadlockException
     * a QueryException (su clase padre). Esto resuelve el error "Undefined method"
     * que reporta el analizador estático en Laravel 12, donde DeadlockException puede
     * no declarar esos métodos explícitamente en su propia clase.
     */
    private function renderDeadlockException(DeadlockException $e): JsonResponse
    {
        /** @var QueryException $asQuery */
        $asQuery = $e;

        return $this->errorResponse(
            'Conflicto de concurrencia en la base de datos. Por favor, reintente la operación.',
            Response::HTTP_CONFLICT,
            [
                'error_type' => 'DEADLOCK',
                'retry_after' => 3,
                'max_retries' => 3,
                'recovery_strategy' => 'Exponential backoff recommended',
                'sql' => $this->shouldIncludeDebugInfo() ? $asQuery->getSql() : null,
                'bindings' => $this->shouldIncludeDebugInfo() ? $asQuery->getBindings() : null,
            ],
            'DEADLOCK_ERROR',
            ['recovery_hint' => 'Reintentar con backoff exponencial (1s, 2s, 4s)'],
            $e
        );
    }

    private function renderConnectionException(ConnectionException $e): JsonResponse
    {
        return $this->errorResponse(
            'Error de conexión con el servidor de base de datos. Por favor, intente más tarde.',
            Response::HTTP_SERVICE_UNAVAILABLE,
            [
                'retry_after' => 30,
                'suggestion' => 'Verificar configuración de conexión en .env',
            ],
            'CONNECTION_ERROR',
            [],
            $e
        );
    }

    private function renderPDOException(PDOException $e): JsonResponse
    {
        return $this->errorResponse(
            'Error en la capa de acceso a datos. Por favor, intente más tarde.',
            Response::HTTP_SERVICE_UNAVAILABLE,
            [
                'sql_state' => $e->errorInfo[0] ?? null,
                'error_code' => $e->errorInfo[1] ?? null,
            ],
            'PDO_ERROR',
            [],
            $e
        );
    }

    private function renderQueryException(QueryException $e): JsonResponse
    {
        $errorCode = $e->errorInfo[1] ?? null;
        $sqlState = $e->errorInfo[0] ?? null;

        // Deadlock
        if ($errorCode === 1213 || $sqlState === '40P01') {
            return $this->errorResponse(
                'Conflicto de concurrencia. Por favor, reintente la operación.',
                Response::HTTP_CONFLICT,
                $this->extractDatabaseErrorDetails($e),
                'DEADLOCK_ERROR',
                ['retry_after' => 3],
                $e
            );
        }

        // Duplicate entry (MySQL 1062, PostgreSQL 23505)
        if ($errorCode === 1062 || $sqlState === '23505') {
            return $this->errorResponse(
                'El registro ya existe en el sistema.',
                Response::HTTP_CONFLICT,
                $this->extractDatabaseErrorDetails($e),
                'DUPLICATE_ENTRY',
                ['suggestion' => 'Verificar que el registro no exista previamente'],
                $e
            );
        }

        // Foreign key violation — DELETE (MySQL 1451, PostgreSQL 23503)
        if ($errorCode === 1451 || $sqlState === '23503') {
            return $this->errorResponse(
                'No se puede eliminar porque tiene registros relacionados.',
                Response::HTTP_CONFLICT,
                $this->extractDatabaseErrorDetails($e),
                'DEPENDENCY_CONSTRAINT_VIOLATION',
                ['suggestion' => 'Eliminar los registros relacionados primero'],
                $e
            );
        }

        // Foreign key violation — INSERT/UPDATE (MySQL 1452)
        if ($errorCode === 1452) {
            return $this->errorResponse(
                'El registro referenciado no existe.',
                Response::HTTP_BAD_REQUEST,
                $this->extractDatabaseErrorDetails($e),
                'FOREIGN_KEY_CONSTRAINT_VIOLATION',
                ['suggestion' => 'Verificar que el registro referenciado existe'],
                $e
            );
        }

        // Column cannot be null (MySQL 1048, PostgreSQL 23502)
        if ($errorCode === 1048 || $sqlState === '23502') {
            return $this->errorResponse(
                'Faltan campos requeridos para la operación.',
                Response::HTTP_BAD_REQUEST,
                $this->extractDatabaseErrorDetails($e),
                'MISSING_FIELD',
                [],
                $e
            );
        }

        // Table doesn't exist (MySQL 1146)
        if ($errorCode === 1146) {
            return $this->errorResponse(
                'Error de configuración: Tabla no encontrada.',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                $this->extractDatabaseErrorDetails($e),
                'DATABASE_ERROR',
                ['suggestion' => 'Ejecutar migraciones pendientes'],
                $e
            );
        }

        // Unknown database (MySQL 1049)
        if ($errorCode === 1049) {
            return $this->errorResponse(
                'Error de configuración: Base de datos no encontrada.',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                $this->extractDatabaseErrorDetails($e),
                'CONFIGURATION_ERROR',
                ['suggestion' => 'Verificar nombre de la base de datos en .env'],
                $e
            );
        }

        // Default
        return $this->errorResponse(
            $this->shouldIncludeDebugInfo()
                ? 'Error en la operación de base de datos: '.$e->getMessage()
                : 'Error en la operación de base de datos. Por favor, intente más tarde.',
            Response::HTTP_INTERNAL_SERVER_ERROR,
            $this->extractDatabaseErrorDetails($e),
            'DATABASE_ERROR',
            [],
            $e
        );
    }

    private function renderInvalidArgumentException(\InvalidArgumentException $e): JsonResponse
    {
        return $this->errorResponse(
            'Argumento inválido: '.$e->getMessage(),
            Response::HTTP_BAD_REQUEST,
            [],
            'QUERY_ARGUMENT_ERROR',
            [],
            $e
        );
    }

    private function renderRuntimeException(\RuntimeException $e): JsonResponse
    {
        return $this->errorResponse(
            $this->shouldIncludeDebugInfo()
                ? 'Error en tiempo de ejecución: '.$e->getMessage()
                : 'Error interno del servidor. Por favor, intente más tarde.',
            Response::HTTP_INTERNAL_SERVER_ERROR,
            [],
            'RUNTIME_ERROR',
            [],
            $e
        );
    }

    private function renderLogicException(\LogicException $e): JsonResponse
    {
        return $this->errorResponse(
            $this->shouldIncludeDebugInfo()
                ? 'Error lógico: '.$e->getMessage()
                : 'Error interno del servidor. Por favor, intente más tarde.',
            Response::HTTP_INTERNAL_SERVER_ERROR,
            [],
            'LOGIC_ERROR',
            [],
            $e
        );
    }

    private function renderGenericException(Throwable $e): JsonResponse
    {
        return $this->errorResponse(
            $this->shouldIncludeDebugInfo()
                ? $e->getMessage() ?: 'Ha ocurrido un error inesperado.'
                : 'Ha ocurrido un error interno. Por favor, intente más tarde.',
            Response::HTTP_INTERNAL_SERVER_ERROR,
            [],
            'INTERNAL_SERVER_ERROR',
            [],
            $e
        );
    }

    // =========================================================================
    // MÉTODOS AUXILIARES
    // =========================================================================

    /**
     * Registra la excepción con el nivel de log apropiado.
     *
     * El bloque QueryException cubre también a DeadlockException por herencia,
     * así que getSql()/getBindings() están disponibles en ambos casos.
     * RelationNotFoundException expone sus datos como propiedades públicas.
     */
    private function logException(Throwable $e, Request $request): void
    {
        $level = $this->levels[get_class($e)] ?? 'error';

        $context = [
            'exception_type' => get_class($e),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'request_id' => $this->getRequestId(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'route' => $request->route()?->getName(),
        ];

        // QueryException cubre también a DeadlockException (herencia)
        if ($e instanceof QueryException) {
            $context['sql'] = $e->getSql();
            $context['bindings'] = $e->getBindings();
            $context['error_code'] = $e->errorInfo[1] ?? null;
            $context['sql_state'] = $e->errorInfo[0] ?? null;
        }

        if ($e instanceof ModelNotFoundException) {
            $context['model'] = $e->getModel();
            $context['ids'] = $e->getIds();
        }

        if ($e instanceof RelationNotFoundException) {
            // Propiedades públicas directas, no métodos getter
            $context['model'] = $e->model;
            $context['relation'] = $e->relation;
        }

        if ($e instanceof ValidationException) {
            $context['errors'] = $e->errors();
        }

        if ($e instanceof ConnectionException || $e instanceof PDOException) {
            $context['connection'] = config('database.default');
        }

        if ($e instanceof MassAssignmentException) {
            $context['message'] = $e->getMessage();
        }

        Logger::{$level}($e->getMessage(), $e, $context);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractDatabaseErrorDetails(QueryException $e): array
    {
        $details = [
            'sql_state' => $e->errorInfo[0] ?? null,
            'error_code' => $e->errorInfo[1] ?? null,
            'database_message' => $e->errorInfo[2] ?? null,
        ];

        if ($this->shouldIncludeDebugInfo()) {
            $details['sql'] = $e->getSql();
            $details['bindings'] = $e->getBindings();
        }

        return $details;
    }

    private function getErrorCodeFromHttpStatus(int $httpStatus): ?string
    {
        return match ($httpStatus) {
            400 => 'INVALID_INPUT',
            401 => 'UNAUTHENTICATED',
            403 => 'FORBIDDEN',
            404 => 'RESOURCE_NOT_FOUND',
            409 => 'CONFLICT',
            422 => 'VALIDATION_ERROR',
            429 => 'TOO_MANY_REQUESTS',
            503 => 'SERVICE_UNAVAILABLE',
            default => null,
        };
    }

    private function shouldIncludeDebugInfo(): bool
    {
        return app()->environment(['local', 'development', 'staging']);
    }

    private function getRequestId(): string
    {
        static $requestId = null;

        return $requestId ??= request()->header('X-Request-ID') ?? uniqid('req_', true);
    }
}
