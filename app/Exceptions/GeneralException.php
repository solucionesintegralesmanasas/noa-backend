<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Clase GeneralException
 *
 * Excepción personalizada para estandarizar respuestas de error en la aplicación.
 * Proporciona control detallado sobre códigos de error, mensajes, estados HTTP y datos adicionales.
 *
 * @author Darwin Montes Lopez
 *
 * @version 3.0.0
 *
 * @creationDate 2025-08-27
 *
 * @lastUpdate 2026-03-22
 *
 * CHANGELOG v3.0.0:
 * - [ADD] Soporte para códigos de error semánticos
 * - [ADD] Método setErrorCode() para personalizar código interno
 * - [IMPROVE] buildResponseData() ahora incluye error_code cuando está definido
 * - [IMPROVE] Métodos estáticos ahora aceptan errorCode personalizado
 */
class GeneralException extends Exception
{
    /**
     * @var int Código de estado HTTP
     */
    protected int $httpStatus;

    /**
     * @var string|null Código de error semántico (ej: 'VALIDATION_ERROR')
     */
    protected ?string $errorCode = null;

    /**
     * @param  string  $mensaje  Mensaje de error
     * @param  int  $httpStatus  Código HTTP
     * @param  int  $codigo  Código interno de error
     * @param  mixed  $data  Datos adicionales
     * @param  string|null  $errorCode  Código semántico de error
     */
    public function __construct(
        string $mensaje = 'Error general',
        int $httpStatus = SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR,
        int $codigo = 0,
        protected mixed $data = null,
        ?string $errorCode = null
    ) {
        // Si no se proporciona código interno, usa el httpStatus
        $codigo = $codigo ?: $httpStatus;

        parent::__construct($mensaje, $codigo);

        $this->httpStatus = $httpStatus;
        $this->errorCode = $errorCode;
    }

    /**
     * Renderiza la respuesta HTTP para la excepción
     *
     * @param  Request  $request  Instancia de la solicitud HTTP actual
     * @return JsonResponse|SymfonyResponse|null
     */
    public function render(Request $request): JsonResponse|Response|SymfonyResponse|null
    {
        // Delegar el manejo a los controladores API que usan HandlesApiResponse
        if ($request->is('api/*')) {
            return null;
        }

        $response = $this->buildResponseData();

        // Permite que el cliente "pregunte" (indique) el formato de respuesta:
        // - query param: ?response_format=json|view
        // - header: X-Response-Format: json|view
        $preferredFormat = strtolower((string) $request->query('response_format', $request->header('X-Response-Format', '')));

        // Si el cliente pidió explícitamente JSON, devolver JSON aunque no sea AJAX
        if ($preferredFormat === 'json') {
            return response()->json($response, $this->httpStatus);
        }

        // Si el cliente pidió explícitamente vista, devolver vista aunque espere JSON
        if ($preferredFormat === 'view') {
            return response()->view('errors.general', [
                'code' => $this->getCode(),
                'message' => $this->getMessage(),
                'data' => $this->data,
                'error_code' => $this->errorCode,
            ], $this->httpStatus);
        }

        // Respuesta JSON para peticiones AJAX o que esperan JSON
        if ($request->expectsJson()) {
            return response()->json($response, $this->httpStatus);
        }

        // Respuesta de vista para peticiones web tradicionales
        return response()->view('errors.general', [
            'code' => $this->getCode(),
            'message' => $this->getMessage(),
            'data' => $this->data,
            'error_code' => $this->errorCode,
        ], $this->httpStatus);
    }

    /**
     * Construye la estructura de datos de la respuesta
     */
    protected function buildResponseData(): array
    {
        $response = [
            'success' => false,
            'error' => true,
            'codigo' => $this->getCode(),
            'mensaje' => $this->getMessage(),
            'timestamp' => now()->toISOString(),
            'request_id' => request()->header('X-Request-ID') ?? uniqid('req_', true),
        ];

        if ($this->errorCode !== null) {
            $response['error_code'] = $this->errorCode;
        }

        if ($this->data !== null) {
            $response['data'] = $this->data;
        }

        return $response;
    }

    /**
     * Obtiene el código de estado HTTP
     */
    public function getStatusCode(): int
    {
        return $this->httpStatus;
    }

    /**
     * Obtiene el código de error semántico
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * Establece el código de error semántico
     */
    public function setErrorCode(string $errorCode): self
    {
        $this->errorCode = $errorCode;

        return $this;
    }

    /**
     * Obtiene los detalles adicionales como array
     */
    public function getDetails(): array
    {
        return is_array($this->data) ? $this->data : ($this->data ? [$this->data] : []);
    }

    /**
     * Obtiene los datos adicionales en su formato original
     */
    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * Establece los datos adicionales
     */
    public function setData(mixed $data): self
    {
        $this->data = $data;

        return $this;
    }

    // =========================================================================
    // MÉTODOS DE CONVENIENCIA PARA CÓDIGOS HTTP COMUNES
    // =========================================================================

    /**
     * Recurso no encontrado (HTTP 404)
     */
    public static function notFound(
        string $mensaje = 'Recurso no encontrado',
        mixed $data = null,
        ?string $errorCode = 'RESOURCE_NOT_FOUND'
    ): static {
        return new static($mensaje, SymfonyResponse::HTTP_NOT_FOUND, SymfonyResponse::HTTP_NOT_FOUND, $data, $errorCode);
    }

    /**
     * No autorizado (HTTP 401)
     */
    public static function unauthorized(
        string $mensaje = 'No autorizado',
        mixed $data = null,
        ?string $errorCode = 'UNAUTHENTICATED'
    ): static {
        return new static($mensaje, SymfonyResponse::HTTP_UNAUTHORIZED, SymfonyResponse::HTTP_UNAUTHORIZED, $data, $errorCode);
    }

    /**
     * Acceso prohibido (HTTP 403)
     */
    public static function forbidden(
        string $mensaje = 'Acceso prohibido',
        mixed $data = null,
        ?string $errorCode = 'FORBIDDEN'
    ): static {
        return new static($mensaje, SymfonyResponse::HTTP_FORBIDDEN, SymfonyResponse::HTTP_FORBIDDEN, $data, $errorCode);
    }

    /**
     * Solicitud inválida (HTTP 400)
     */
    public static function badRequest(
        string $mensaje = 'Solicitud inválida',
        mixed $data = null,
        ?string $errorCode = 'INVALID_INPUT'
    ): static {
        return new static($mensaje, SymfonyResponse::HTTP_BAD_REQUEST, SymfonyResponse::HTTP_BAD_REQUEST, $data, $errorCode);
    }

    /**
     * Conflicto (HTTP 409)
     */
    public static function conflict(
        string $mensaje = 'Conflicto en la solicitud',
        mixed $data = null,
        ?string $errorCode = 'CONFLICT'
    ): static {
        return new static($mensaje, SymfonyResponse::HTTP_CONFLICT, SymfonyResponse::HTTP_CONFLICT, $data, $errorCode);
    }

    /**
     * Error interno del servidor (HTTP 500)
     */
    public static function serverError(
        string $mensaje = 'Error interno del servidor',
        mixed $data = null,
        ?string $errorCode = 'INTERNAL_SERVER_ERROR'
    ): static {
        return new static($mensaje, SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR, SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR, $data, $errorCode);
    }

    /**
     * Servicio no disponible (HTTP 503)
     */
    public static function serviceUnavailable(
        string $mensaje = 'Servicio no disponible',
        mixed $data = null,
        ?string $errorCode = 'SERVICE_UNAVAILABLE'
    ): static {
        return new static($mensaje, SymfonyResponse::HTTP_SERVICE_UNAVAILABLE, SymfonyResponse::HTTP_SERVICE_UNAVAILABLE, $data, $errorCode);
    }

    /**
     * Entidad no procesable (HTTP 422)
     */
    public static function unprocessable(
        string $mensaje = 'Entidad no procesable',
        mixed $data = null,
        ?string $errorCode = 'VALIDATION_ERROR'
    ): static {
        return new static($mensaje, SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY, SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY, $data, $errorCode);
    }

    /**
     * Demasiadas solicitudes (HTTP 429)
     */
    public static function tooManyRequests(
        string $mensaje = 'Demasiadas solicitudes',
        mixed $data = null,
        ?string $errorCode = 'TOO_MANY_REQUESTS'
    ): static {
        return new static($mensaje, SymfonyResponse::HTTP_TOO_MANY_REQUESTS, SymfonyResponse::HTTP_TOO_MANY_REQUESTS, $data, $errorCode);
    }

    /**
     * Error de base de datos (HTTP 500) - CRUD específico
     */
    public static function databaseError(
        string $mensaje = 'Error en la base de datos',
        mixed $data = null,
        ?string $errorCode = 'DATABASE_ERROR'
    ): static {
        return new static($mensaje, SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR, SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR, $data, $errorCode);
    }

    /**
     * Duplicado (HTTP 409) - CRUD específico
     */
    public static function duplicateEntry(
        string $mensaje = 'El registro ya existe',
        mixed $data = null,
        ?string $errorCode = 'DUPLICATE_ENTRY'
    ): static {
        return new static($mensaje, SymfonyResponse::HTTP_CONFLICT, SymfonyResponse::HTTP_CONFLICT, $data, $errorCode);
    }

    /**
     * Violación de clave foránea (HTTP 409) - CRUD específico
     */
    public static function foreignKeyViolation(
        string $mensaje = 'No se puede eliminar porque tiene registros relacionados',
        mixed $data = null,
        ?string $errorCode = 'DEPENDENCY_CONSTRAINT_VIOLATION'
    ): static {
        return new static($mensaje, SymfonyResponse::HTTP_CONFLICT, SymfonyResponse::HTTP_CONFLICT, $data, $errorCode);
    }

    /**
     * Operación no permitida (HTTP 403) - CRUD específico
     */
    public static function operationNotAllowed(
        string $mensaje = 'Operación no permitida',
        mixed $data = null,
        ?string $errorCode = 'OPERATION_NOT_ALLOWED'
    ): static {
        return new static($mensaje, SymfonyResponse::HTTP_FORBIDDEN, SymfonyResponse::HTTP_FORBIDDEN, $data, $errorCode);
    }
}
