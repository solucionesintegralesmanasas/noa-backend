<?php

namespace App\Traits;

use App\Enum\ApiErrorCode;
use App\Exceptions\GeneralException;
use App\Utils\Logger;
use GDebrauwer\Hateoas\Traits\CreatesLinks;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\MissingAttributeException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\RelationNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PDOException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * @trait HandlesApiResponse
 *
 * @brief Trait para estandarizar las respuestas de la API y el manejo de errores.
 *
 * @version 3.2.0
 *
 * @lastUpdate 2026-03-24
 *
 * CHANGELOG v3.2.0:
 * - [FIX CRÍTICO] Reemplazado Exception por \Throwable en handleException(), logError(),
 *   errorResponse(), getExceptionDetails(), determineStatusCode(), determineErrorMessage(),
 *   getErrorCodeFromException(), buildErrorStructure(), getDebugInfo() y getErrorContext().
 *   Esto resuelve el crash en cascada cuando PHP lanza TypeError, Error, ParseError, etc.,
 *   ya que estas clases implementan Throwable pero NO extienden Exception.
 *
 * CHANGELOG v3.1.1:
 * - [FIX] RelationNotFoundException::$model y $relation son propiedades públicas,
 *         no métodos. Reemplazado $e->getModel()/$e->getRelation() por $e->model/$e->relation.
 *
 * CHANGELOG v3.1.0:
 * - [ADD] Manejo completo de excepciones de Eloquent (MassAssignment, RelationNotFound, MissingAttribute)
 * - [ADD] Manejo de excepciones de base de datos (DeadlockException, ConnectionException, PDOException)
 * - [ADD] Manejo de excepciones de argumentos (InvalidArgumentException, RuntimeException, LogicException)
 * - [ADD] Códigos de error específicos para operaciones CRUD
 * - [IMPROVE] getQueryExceptionStatusCode() con soporte MySQL y PostgreSQL
 * - [IMPROVE] getExceptionDetails() con detalles específicos por tipo de excepción
 */
trait HandlesApiResponse
{
    use CreatesLinks;

    /** @var array<string, int> Mapa de códigos de error legibles a códigos internos */
    private array $errorCodes = ApiErrorCode::ERROR_CODES;

    /** @var array<int, string> Mapa de códigos HTTP a códigos de error de excepción */
    private array $exceptionCodes = ApiErrorCode::EXCEPTION_CODE_ERROR;

    // =========================================================================
    // RESPUESTAS DE ÉXITO
    // =========================================================================

    /**
     * Respuesta exitosa base.
     *
     * @param  mixed  $data  Datos a retornar en la respuesta.
     * @param  string  $message  Mensaje descriptivo de la operación.
     * @param  int  $status  Código HTTP de respuesta (default 200).
     * @param  array<mixed>  $meta  Metadata adicional opcional.
     */
    protected function successResponse(
        mixed $data = null,
        string $message = 'Operación exitosa',
        int $status = Response::HTTP_OK,
        array $meta = []
    ): JsonResponse {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => now()->toISOString(),
            'request_id' => $this->getRequestId(),
        ];

        if (! empty($meta)) {
            $response['meta'] = $meta;
        }

        $response['_links'] = $this->generateHateoasLinks($data, $status);

        return response()->json($response, $status);
    }

    /**
     * Respuesta para recurso creado (HTTP 201).
     */
    protected function createdResponse(
        mixed $data,
        string $message = 'Recurso creado exitosamente'
    ): JsonResponse {
        return $this->successResponse($data, $message, Response::HTTP_CREATED);
    }

    /**
     * Respuesta sin contenido (HTTP 204).
     */
    protected function noContentResponse(string $message = 'Operación completada'): Response
    {
        return response()->noContent();
    }

    /**
     * Respuesta paginada base.
     */
    protected function paginatedResponse(
        LengthAwarePaginator $paginatedData,
        string $message = 'Datos obtenidos exitosamente',
        array $additionalMeta = []
    ): JsonResponse {
        $meta = array_merge(
            ['pagination' => $this->buildPaginationMeta($paginatedData)],
            $additionalMeta
        );

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $paginatedData->items(),
            'timestamp' => now()->toISOString(),
            'request_id' => $this->getRequestId(),
            'meta' => $meta,
            '_links' => $this->generatePaginationLinks($paginatedData),
        ], Response::HTTP_OK);
    }

    // =========================================================================
    // RESPUESTAS DE ERROR — GENÉRICAS
    // =========================================================================

    /**
     * Respuesta de error base.
     *
     * NOTA: Se usa \Throwable en lugar de Exception para cubrir TypeError,
     * Error, ParseError, ArithmeticError, etc. que no extienden Exception.
     */
    protected function errorResponse(
        string $message,
        int $status = Response::HTTP_INTERNAL_SERVER_ERROR,
        array $errors = [],
        ?string $errorCode = null,
        array $meta = [],
        ?\Throwable $exception = null
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
            'error' => $this->buildErrorStructure($status, $errorCode, $errors, $exception),
        ];

        if (! empty($meta)) {
            $response['meta'] = $meta;
        }

        if ($this->shouldIncludeDebugInfo()) {
            $response['error']['debug'] = $this->getDebugInfo($exception, $errorCode);
        }

        return response()->json($response, $status);
    }

    /**
     * Error de validación (HTTP 422).
     */
    protected function validationErrorResponse(
        array $errors,
        string $message = 'Los datos proporcionados no son válidos.'
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'internal_code' => $this->errorCodes['VALIDATION_ERROR'] ?? null,
                'timestamp' => now()->toISOString(),
                'request_id' => $this->getRequestId(),
                'details' => $this->formatValidationErrors($errors),
            ],
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Recurso no encontrado (HTTP 404).
     */
    protected function notFoundResponse(
        string $message = 'Recurso no encontrado',
        ?string $resourceType = null
    ): JsonResponse {
        return $this->errorResponse(
            $message,
            Response::HTTP_NOT_FOUND,
            $this->buildNotFoundDetails($resourceType),
            'RESOURCE_NOT_FOUND',
            ['resource_type' => $resourceType]
        );
    }

    /**
     * No autenticado (HTTP 401).
     */
    protected function unauthorizedResponse(string $message = 'No autorizado'): JsonResponse
    {
        return $this->errorResponse($message, Response::HTTP_UNAUTHORIZED, [], 'UNAUTHENTICATED');
    }

    /**
     * Acceso prohibido (HTTP 403).
     */
    protected function forbiddenResponse(string $message = 'Acceso prohibido'): JsonResponse
    {
        return $this->errorResponse($message, Response::HTTP_FORBIDDEN, [], 'FORBIDDEN');
    }

    /**
     * Conflicto de estado (HTTP 409).
     */
    protected function conflictResponse(
        string $message = 'Conflicto en la solicitud',
        array $conflictDetails = []
    ): JsonResponse {
        return $this->errorResponse($message, Response::HTTP_CONFLICT, $conflictDetails, 'CONFLICT');
    }

    /**
     * Demasiadas solicitudes (HTTP 429).
     */
    protected function tooManyRequestsResponse(
        string $message = 'Demasiadas solicitudes',
        ?int $retryAfter = null
    ): JsonResponse {
        $meta = $retryAfter ? ['retry_after' => $retryAfter] : [];

        $jsonResponse = $this->errorResponse(
            $message,
            Response::HTTP_TOO_MANY_REQUESTS,
            [],
            'TOO_MANY_REQUESTS',
            $meta
        );

        if ($retryAfter) {
            $jsonResponse->header('Retry-After', (string) $retryAfter);
        }

        return $jsonResponse;
    }

    /**
     * Error de base de datos (HTTP 500).
     */
    protected function databaseErrorResponse(
        QueryException $e,
        string $message = 'Error en la base de datos'
    ): JsonResponse {
        return $this->errorResponse(
            $message,
            Response::HTTP_INTERNAL_SERVER_ERROR,
            $this->extractDatabaseErrorDetails($e),
            $this->mapDatabaseErrorCode($e),
            [],
            $e
        );
    }

    // =========================================================================
    // MANEJO DE EXCEPCIONES (PRINCIPAL)
    // =========================================================================

    /**
     * Punto de entrada principal para manejar excepciones en controladores.
     *
     * Acepta \Throwable para cubrir tanto Exception como Error (TypeError,
     * ParseError, ArithmeticError, DivisionByZeroError, etc.).
     */
    protected function handleException(\Throwable $e, array $context = []): JsonResponse
    {
        $this->logError($e, $context);

        // --- Excepciones de validación ---
        if ($e instanceof ValidationException) {
            return $this->validationErrorResponse($e->errors(), $e->getMessage());
        }

        // --- Excepciones de Spatie Query Builder ---
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

        // --- Excepciones de Eloquent ---
        if ($e instanceof ModelNotFoundException) {
            return $this->notFoundResponse(
                $e->getMessage() ?: 'Recurso no encontrado',
                class_basename($e->getModel())
            );
        }

        if ($e instanceof MassAssignmentException) {
            return $this->errorResponse(
                $e->getMessage(),
                Response::HTTP_BAD_REQUEST,
                ['hint' => 'Verificar los campos fillable en el modelo'],
                'MASS_ASSIGNMENT_ERROR',
                [],
                $e
            );
        }

        if ($e instanceof RelationNotFoundException) {
            return $this->errorResponse(
                $e->getMessage(),
                Response::HTTP_BAD_REQUEST,
                ['model' => $e->model, 'relation' => $e->relation],
                'RELATION_NOT_FOUND',
                [],
                $e
            );
        }

        if ($e instanceof MissingAttributeException) {
            return $this->errorResponse(
                $e->getMessage(),
                Response::HTTP_BAD_REQUEST,
                [],
                'MISSING_ATTRIBUTE',
                [],
                $e
            );
        }

        // --- Excepciones de base de datos ---
        if ($e instanceof DeadlockException) {
            return $this->errorResponse(
                'Conflicto de concurrencia en la base de datos. Por favor, reintente la operación.',
                Response::HTTP_CONFLICT,
                ['retry_after' => 3, 'max_retries' => 3],
                'DEADLOCK_ERROR',
                ['recovery_hint' => 'Reintentar con backoff exponencial'],
                $e
            );
        }

        if ($e instanceof QueryException) {
            return $this->handleQueryException($e);
        }

        if ($e instanceof ConnectionException || $e instanceof PDOException) {
            return $this->errorResponse(
                'Error de conexión con la base de datos. Por favor, intente más tarde.',
                Response::HTTP_SERVICE_UNAVAILABLE,
                [],
                'CONNECTION_ERROR',
                ['retry_after' => 30],
                $e
            );
        }

        // --- TypeError y otros Error de PHP ---
        if ($e instanceof \TypeError) {
            return $this->errorResponse(
                $this->shouldIncludeDebugInfo()
                    ? $e->getMessage()
                    : 'Error interno del servidor.',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                [],
                'TYPE_ERROR',
                [],
                $e
            );
        }

        if ($e instanceof \Error) {
            return $this->errorResponse(
                $this->shouldIncludeDebugInfo()
                    ? $e->getMessage()
                    : 'Error interno del servidor.',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                [],
                'INTERNAL_ERROR',
                [],
                $e
            );
        }

        // --- Resto de Throwable / Exception ---
        $statusCode = $this->determineStatusCode($e);
        $message = $this->determineErrorMessage($e, $statusCode);
        $errorCode = $this->getErrorCodeFromException($e);
        $errorDetails = $this->getExceptionDetails($e);

        return $this->errorResponse(
            $message,
            $statusCode,
            $errorDetails,
            $errorCode,
            [],
            $e
        );
    }

    // =========================================================================
    // MÉTODOS AUXILIARES — MANEJO DE EXCEPCIONES
    // =========================================================================

    /**
     * Maneja específicamente las excepciones de base de datos (QueryException).
     */
    private function handleQueryException(QueryException $e): JsonResponse
    {
        $errorCode = $e->errorInfo[1] ?? null;
        $sqlState = $e->errorInfo[0] ?? null;

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

        if ($errorCode === 1062 || $sqlState === '23505') {
            return $this->errorResponse(
                'El registro ya existe en el sistema.',
                Response::HTTP_CONFLICT,
                $this->extractDatabaseErrorDetails($e),
                'DUPLICATE_ENTRY',
                [],
                $e
            );
        }

        if ($errorCode === 1451 || $sqlState === '23503') {
            return $this->errorResponse(
                'No se puede eliminar porque tiene registros relacionados.',
                Response::HTTP_CONFLICT,
                $this->extractDatabaseErrorDetails($e),
                'DEPENDENCY_CONSTRAINT_VIOLATION',
                [],
                $e
            );
        }

        if ($errorCode === 1452) {
            return $this->errorResponse(
                'El registro referenciado no existe.',
                Response::HTTP_BAD_REQUEST,
                $this->extractDatabaseErrorDetails($e),
                'FOREIGN_KEY_CONSTRAINT_VIOLATION',
                [],
                $e
            );
        }

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

        return $this->errorResponse(
            'Error en la operación de base de datos.',
            Response::HTTP_INTERNAL_SERVER_ERROR,
            $this->extractDatabaseErrorDetails($e),
            'DATABASE_ERROR',
            [],
            $e
        );
    }

    /**
     * Determina el código HTTP apropiado para un Throwable.
     */
    private function determineStatusCode(\Throwable $e): int
    {
        $code = $e->getCode();

        if (is_int($code) && $code >= 100 && $code < 600) {
            return $code;
        }

        return match (true) {
            // Excepciones de Eloquent
            $e instanceof ModelNotFoundException => Response::HTTP_NOT_FOUND,
            $e instanceof MassAssignmentException => Response::HTTP_BAD_REQUEST,
            $e instanceof RelationNotFoundException => Response::HTTP_BAD_REQUEST,
            $e instanceof MissingAttributeException => Response::HTTP_BAD_REQUEST,

            // Excepciones de Base de Datos
            $e instanceof DeadlockException => Response::HTTP_CONFLICT,
            $e instanceof QueryException => $this->getQueryExceptionStatusCode($e),
            $e instanceof ConnectionException => Response::HTTP_SERVICE_UNAVAILABLE,
            $e instanceof PDOException => Response::HTTP_SERVICE_UNAVAILABLE,

            // Excepciones de Argumentos/Runtime
            $e instanceof \InvalidArgumentException => Response::HTTP_BAD_REQUEST,
            $e instanceof \RuntimeException => Response::HTTP_INTERNAL_SERVER_ERROR,
            $e instanceof \LogicException => Response::HTTP_INTERNAL_SERVER_ERROR,

            // Errores de PHP (TypeError, ParseError, etc.)
            $e instanceof \TypeError => Response::HTTP_INTERNAL_SERVER_ERROR,
            $e instanceof \Error => Response::HTTP_INTERNAL_SERVER_ERROR,

            // Excepciones HTTP/Symfony
            $e instanceof AuthenticationException => Response::HTTP_UNAUTHORIZED,
            $e instanceof AuthorizationException => Response::HTTP_FORBIDDEN,
            $e instanceof NotFoundHttpException => Response::HTTP_NOT_FOUND,
            $e instanceof MethodNotAllowedHttpException => Response::HTTP_METHOD_NOT_ALLOWED,
            $e instanceof TooManyRequestsHttpException => Response::HTTP_TOO_MANY_REQUESTS,
            $e instanceof HttpException => $e->getStatusCode(),

            default => Response::HTTP_INTERNAL_SERVER_ERROR,
        };
    }

    /**
     * Determina el código HTTP según el error SQL (soporte MySQL y PostgreSQL).
     */
    private function getQueryExceptionStatusCode(QueryException $e): int
    {
        $errorCode = $e->errorInfo[1] ?? null;
        $sqlState = $e->errorInfo[0] ?? null;

        return match (true) {
            $errorCode === 1062 => Response::HTTP_CONFLICT,
            $errorCode === 1451 => Response::HTTP_CONFLICT,
            $errorCode === 1452 => Response::HTTP_BAD_REQUEST,
            $errorCode === 1048 => Response::HTTP_BAD_REQUEST,
            $errorCode === 1146 => Response::HTTP_INTERNAL_SERVER_ERROR,
            $errorCode === 1049 => Response::HTTP_SERVICE_UNAVAILABLE,
            $errorCode === 2002 => Response::HTTP_SERVICE_UNAVAILABLE,
            $errorCode === 1205 => Response::HTTP_CONFLICT,
            $errorCode === 1213 => Response::HTTP_CONFLICT,

            $sqlState === '23505' => Response::HTTP_CONFLICT,
            $sqlState === '23503' => Response::HTTP_CONFLICT,
            $sqlState === '23502' => Response::HTTP_BAD_REQUEST,
            $sqlState === '40P01' => Response::HTTP_CONFLICT,
            $sqlState === '08006' => Response::HTTP_SERVICE_UNAVAILABLE,

            default => Response::HTTP_INTERNAL_SERVER_ERROR,
        };
    }

    /**
     * Determina el mensaje de error según el entorno.
     */
    private function determineErrorMessage(\Throwable $e, int $statusCode): string
    {
        if ($statusCode >= 500 && app()->environment('production')) {
            return 'Ha ocurrido un error interno. Por favor, intente más tarde.';
        }

        return $e->getMessage() ?: 'Ha ocurrido un error inesperado.';
    }

    /**
     * Obtiene el código de error semántico desde un Throwable.
     */
    private function getErrorCodeFromException(\Throwable $e): ?string
    {
        if ($e instanceof GeneralException) {
            return match ($e->getStatusCode()) {
                404 => $this->exceptionCodes[404] ?? 'RESOURCE_NOT_FOUND',
                401 => $this->exceptionCodes[401] ?? 'UNAUTHENTICATED',
                403 => $this->exceptionCodes[403] ?? 'FORBIDDEN',
                400 => $this->exceptionCodes[400] ?? 'INVALID_INPUT',
                409 => $this->exceptionCodes[409] ?? 'CONFLICT',
                422 => $this->exceptionCodes[422] ?? 'VALIDATION_ERROR',
                429 => $this->exceptionCodes[429] ?? 'TOO_MANY_REQUESTS',
                default => $this->exceptionCodes[500] ?? 'INTERNAL_SERVER_ERROR',
            };
        }

        return match (true) {
            $e instanceof MassAssignmentException => 'MASS_ASSIGNMENT_ERROR',
            $e instanceof RelationNotFoundException => 'RELATION_NOT_FOUND',
            $e instanceof MissingAttributeException => 'MISSING_ATTRIBUTE',
            $e instanceof DeadlockException => 'DEADLOCK_ERROR',
            $e instanceof QueryException => 'DATABASE_ERROR',
            $e instanceof ConnectionException => 'CONNECTION_ERROR',
            $e instanceof PDOException => 'PDO_ERROR',
            $e instanceof \InvalidArgumentException => 'QUERY_ARGUMENT_ERROR',
            $e instanceof \RuntimeException => 'RUNTIME_ERROR',
            $e instanceof \LogicException => 'LOGIC_ERROR',
            $e instanceof \TypeError => 'TYPE_ERROR',
            $e instanceof \Error => 'INTERNAL_ERROR',
            default => null,
        };
    }

    /**
     * Extrae detalles específicos según el tipo de Throwable.
     */
    private function getExceptionDetails(\Throwable $e): array
    {
        $details = [];

        if ($e instanceof QueryException) {
            $details['database_error'] = [
                'sql_state' => $e->errorInfo[0] ?? null,
                'error_code' => $e->errorInfo[1] ?? null,
                'message' => $e->errorInfo[2] ?? null,
                'sql' => $this->shouldIncludeDebugInfo() ? $e->getSql() : null,
                'bindings' => $this->shouldIncludeDebugInfo() ? $e->getBindings() : null,
            ];
        }

        if ($e instanceof DeadlockException) {
            $details['deadlock'] = [
                'message' => $e->getMessage(),
                'suggestion' => 'Reintentar la transacción con backoff exponencial',
            ];
        }

        if ($e instanceof ConnectionException) {
            $details['connection'] = [
                'message' => $e->getMessage(),
                'database_config' => $this->shouldIncludeDebugInfo() ? config('database.default') : null,
            ];
        }

        if ($e instanceof PDOException) {
            $details['pdo'] = [
                'error_code' => $e->errorInfo[0] ?? null,
                'sql_state' => $e->errorInfo[1] ?? null,
                'message' => $e->errorInfo[2] ?? $e->getMessage(),
            ];
        }

        if ($e instanceof MassAssignmentException) {
            $details['mass_assignment'] = [
                'message' => $e->getMessage(),
                'hint' => 'Verificar el array $fillable o $guarded en el modelo',
            ];
        }

        if ($e instanceof RelationNotFoundException) {
            $details['relation'] = [
                'message' => $e->getMessage(),
                'model' => $e->model,
                'relation' => $e->relation,
            ];
        }

        if ($e instanceof ModelNotFoundException) {
            $details['model_error'] = [
                'model' => $e->getModel(),
                'ids' => $e->getIds(),
            ];
        }

        if ($e instanceof HttpException) {
            $details['http_error'] = [
                'status_code' => $e->getStatusCode(),
                'headers' => $e->getHeaders(),
            ];
        }

        if ($e instanceof ValidationException) {
            $details['validation_error'] = [
                'errors' => $e->errors(),
            ];
        }

        // Detalles para TypeError y errores de PHP en entornos no productivos
        if ($e instanceof \TypeError || $e instanceof \Error) {
            if ($this->shouldIncludeDebugInfo()) {
                $details['php_error'] = [
                    'type' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ];
            }
        }

        return $details;
    }

    /**
     * Mapea el código de error MySQL/MariaDB/PostgreSQL a un código semántico.
     */
    private function mapDatabaseErrorCode(QueryException $e): string
    {
        $errorCode = $e->errorInfo[1] ?? null;
        $sqlState = $e->errorInfo[0] ?? null;

        return match (true) {
            $errorCode === 1062 || $sqlState === '23505' => 'DUPLICATE_ENTRY',
            $errorCode === 1451 || $sqlState === '23503' => 'DEPENDENCY_CONSTRAINT_VIOLATION',
            $errorCode === 1452 => 'FOREIGN_KEY_CONSTRAINT_VIOLATION',
            $errorCode === 1213 || $sqlState === '40P01' => 'DEADLOCK_ERROR',
            default => 'DATABASE_ERROR',
        };
    }

    /**
     * Extrae detalles de QueryException para respuesta.
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

    /**
     * Registra el error en el sistema de logging.
     */
    private function logError(\Throwable $e, array $context = []): void
    {
        $logLevel = match (true) {
            $e instanceof DeadlockException,
            $e instanceof ConnectionException => 'critical',
            $e instanceof QueryException => 'error',
            $e instanceof ValidationException => 'warning',
            $e instanceof \TypeError,
            $e instanceof \Error => 'error',
            default => 'error',
        };

        Logger::{$logLevel}($e->getMessage(), $e, array_merge($context, [
            'request_id' => $this->getRequestId(),
            'path' => request()->path(),
            'method' => request()->method(),
            'exception_type' => get_class($e),
            'status_code' => $this->determineStatusCode($e),
        ]));
    }

    // =========================================================================
    // MÉTODOS AUXILIARES PRIVADOS — CONSTRUCCIÓN DE RESPUESTA
    // =========================================================================

    /**
     * Construye la estructura de error estandarizada.
     */
    private function buildErrorStructure(
        int $status,
        ?string $errorCode,
        array $errors,
        ?\Throwable $exception
    ): array {
        return [
            'code' => $errorCode,
            'internal_code' => $this->getInternalErrorCode($errorCode),
            'timestamp' => now()->toISOString(),
            'request_id' => $this->getRequestId(),
            'http_status' => $status,
            'category' => $this->getErrorCategory($status),
            'details' => empty($errors) ? new \stdClass : $errors,
            'context' => $this->getErrorContext($exception),
            'recovery' => $this->getRecoveryActions($errorCode, $status),
        ];
    }

    /**
     * Construye detalles para respuesta 404.
     */
    private function buildNotFoundDetails(?string $resourceType): array
    {
        $details = [];
        $route = Route::current();

        if ($route && ! empty($route->parameters())) {
            $resourceId = array_values($route->parameters())[0] ?? null;

            if ($resourceId) {
                $details = [
                    'resource_type' => $resourceType ?? $this->guessResourceTypeFromRoute(),
                    'resource_id' => $resourceId,
                    'searched_in' => $this->getScopeFromRoute(),
                ];
            }
        }

        return $details;
    }

    /**
     * Formatea errores de validación.
     */
    private function formatValidationErrors(array $errors): array
    {
        $details = [];

        foreach ($errors as $field => $messages) {
            $details[] = [
                'field' => $field,
                'messages' => array_values((array) $messages),
                'code' => 'INVALID_FIELD_VALUE',
            ];
        }

        return $details;
    }

    /**
     * Construye la metadata de paginación.
     */
    private function buildPaginationMeta(LengthAwarePaginator $paginatedData): array
    {
        return [
            'current_page' => $paginatedData->currentPage(),
            'last_page' => $paginatedData->lastPage(),
            'per_page' => $paginatedData->perPage(),
            'total' => $paginatedData->total(),
            'from' => $paginatedData->firstItem(),
            'to' => $paginatedData->lastItem(),
            'has_more_pages' => $paginatedData->hasMorePages(),
        ];
    }

    /**
     * Retorna un ID único para la request actual.
     */
    private function getRequestId(): string
    {
        static $requestId = null;

        return $requestId ??= request()->header('X-Request-ID') ?? uniqid('req_', true);
    }

    /**
     * Retorna el código interno numérico para un código de error semántico.
     */
    private function getInternalErrorCode(?string $errorCode): ?int
    {
        return $errorCode ? ($this->errorCodes[$errorCode] ?? null) : null;
    }

    /**
     * Clasifica el error según el rango del código HTTP.
     */
    private function getErrorCategory(int $status): string
    {
        return match (true) {
            $status >= 400 && $status < 500 => 'client_error',
            $status >= 500 && $status < 600 => 'server_error',
            default => 'unknown',
        };
    }

    /**
     * Construye el contexto de la request para incluir en la respuesta de error.
     */
    private function getErrorContext(?\Throwable $exception = null): array
    {
        $context = [
            'endpoint' => request()->path(),
            'method' => request()->method(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
            'referer' => request()->header('Referer'),
        ];

        if ($this->shouldIncludeDebugInfo()) {
            $requestData = $this->sanitizeRequestData();
            if (! empty($requestData)) {
                $context['request_data'] = $requestData;
            }
        }

        $route = Route::current();
        if ($route) {
            $context['route'] = [
                'name' => $route->getName(),
                'parameters' => $route->parameters(),
                'middleware' => $route->middleware(),
            ];
        }

        return $context;
    }

    /**
     * Devuelve los datos del request sin campos sensibles.
     */
    private function sanitizeRequestData(): array
    {
        return request()->except($this->getSensitiveFields());
    }

    /**
     * Lista de campos sensibles que nunca deben exponerse.
     */
    protected function getSensitiveFields(): array
    {
        return [
            'password',
            'password_confirmation',
            'token',
            'secret',
            'credit_card',
            'card_number',
            'cvv',
            'pin',
        ];
    }

    /**
     * Construye las acciones de recuperación sugeridas.
     */
    private function getRecoveryActions(?string $errorCode, int $status): array
    {
        $actions = $errorCode ? (ApiErrorCode::RECOVERY_ACTIONS[$errorCode] ?? []) : [];

        if ($status >= 500) {
            $actions['retry_request'] = 'Reintentar la solicitud después de unos minutos';
            $actions['check_service_status'] = 'Verificar el estado del servicio';
        }

        if ($status === 429) {
            $actions['implement_rate_limiting'] = 'Implementar límites de velocidad en el cliente';
            $actions['use_exponential_backoff'] = 'Usar backoff exponencial para reintentos';
        }

        return $actions;
    }

    /**
     * Determina si se debe incluir información de depuración.
     */
    private function shouldIncludeDebugInfo(): bool
    {
        return app()->environment(['local', 'development', 'staging']);
    }

    /**
     * Construye el bloque de debug para entornos no productivos.
     */
    private function getDebugInfo(?\Throwable $exception, ?string $errorCode): array
    {
        $debug = [
            'error_code_description' => ApiErrorCode::DESCRIPTIONS[$errorCode ?? 'UNKNOWN'] ?? 'Error desconocido',
            'suggested_action' => ApiErrorCode::ACTIONS[$errorCode ?? 'UNKNOWN'] ?? 'Contactar soporte técnico',
        ];

        if ($exception) {
            $debug['exception'] = [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];

            if ($exception instanceof QueryException && $this->shouldIncludeDebugInfo()) {
                $debug['database'] = [
                    'sql' => $exception->getSql(),
                    'bindings' => $exception->getBindings(),
                ];
            }
        }

        $debug['system'] = [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'environment' => app()->environment(),
            'memory_usage' => $this->formatBytes(memory_get_usage(true)),
            'execution_time' => round((microtime(true) - LARAVEL_START) * 1000, 2).'ms',
        ];

        return $debug;
    }

    /**
     * Formatea bytes en unidad legible.
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($bytes > 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, $precision).' '.$units[$i];
    }

    /**
     * Infiere el tipo de recurso desde el nombre de la ruta.
     */
    private function guessResourceTypeFromRoute(): ?string
    {
        $routeName = Route::currentRouteName();

        if (! $routeName) {
            return null;
        }

        if (preg_match('/\.([^.]+)\.(?:show|index|create|update|destroy)/', $routeName, $matches)) {
            return Str::singular(Str::studly($matches[1]));
        }

        return null;
    }

    /**
     * Infiere el alcance de la ruta actual.
     */
    private function getScopeFromRoute(): ?string
    {
        $routeName = Route::currentRouteName();

        if (! $routeName) {
            return null;
        }

        return match (true) {
            str_starts_with($routeName, 'global.') => 'global scope',
            str_starts_with($routeName, 'tenant.') => 'tenant scope',
            default => 'application scope',
        };
    }

    // =========================================================================
    // MÉTODOS AUXILIARES — HATEOAS
    // =========================================================================

    private function generateHateoasLinks(mixed $data, int $status): array
    {
        $links = [
            'self' => [
                'href' => url()->current(),
                'method' => request()->method(),
            ],
        ];

        if ($status === Response::HTTP_CREATED && $data) {
            $resourceId = null;
            if (is_object($data) && isset($data->uuid)) {
                $resourceId = $data->uuid;
            } elseif (is_array($data) && isset($data['uuid'])) {
                $resourceId = $data['uuid'];
            } elseif (is_object($data) && isset($data->id)) {
                $resourceId = $data->id;
            } elseif (is_array($data) && isset($data['id'])) {
                $resourceId = $data['id'];
            }

            if ($resourceId !== null) {
                $links = array_merge($links, $this->generateCrudLinks($resourceId));
            }
        }

        return $links;
    }

    private function generateCrudLinks(mixed $resourceId): array
    {
        $links = [];
        $routeName = Route::currentRouteName();

        if (! $routeName) {
            return $links;
        }

        $crudActions = [
            'show' => 'GET',
            'update' => 'PUT',
            'destroy' => 'DELETE',
        ];

        foreach ($crudActions as $action => $method) {
            try {
                $route = str_replace('.store', ".{$action}", $routeName);

                if (Route::has($route)) {
                    $routeInstance = Route::getRoutes()->getByName($route);
                    $paramName = 'id';

                    if ($routeInstance) {
                        $paramNames = $routeInstance->parameterNames();
                        $paramName = $paramNames[0] ?? 'id';
                    }

                    $linkKey = $action === 'destroy' ? 'delete' : $action;
                    $links[$linkKey] = [
                        'href' => route($route, [$paramName => $resourceId]),
                        'method' => $method,
                    ];
                }
            } catch (\Throwable $e) {
                // Silently fail for HATEOAS
            }
        }

        return $links;
    }

    private function generatePaginationLinks(LengthAwarePaginator $paginatedData): array
    {
        $links = [
            'self' => ['href' => $paginatedData->url($paginatedData->currentPage()), 'method' => 'GET'],
            'first' => ['href' => $paginatedData->url(1), 'method' => 'GET'],
            'last' => ['href' => $paginatedData->url($paginatedData->lastPage()), 'method' => 'GET'],
        ];

        if ($paginatedData->previousPageUrl()) {
            $links['prev'] = ['href' => $paginatedData->previousPageUrl(), 'method' => 'GET'];
        }

        if ($paginatedData->nextPageUrl()) {
            $links['next'] = ['href' => $paginatedData->nextPageUrl(), 'method' => 'GET'];
        }

        return $links;
    }
}
