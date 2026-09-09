<?php

namespace App\Enum;

/**
 * Enum ApiErrorCode
 *
 * Define códigos de error semánticos, acciones de recuperación y descripciones
 * para estandarizar las respuestas de error en la API.
 *
 * @author Sistema
 *
 * @version 1.0.0
 */
enum ApiErrorCode
{
    // =========================================================================
    // CÓDIGOS DE ERROR POR CATEGORÍA
    // =========================================================================

    /**
     * Errores de validación (4000-4099)
     */
    public const VALIDATION_ERROR = 4001;      // Error general de validación de los datos de entrada.

    public const INVALID_INPUT = 4002;         // La entrada proporcionada no es válida.

    public const MISSING_FIELD = 4003;          // Falta un campo requerido en la solicitud.

    public const INVALID_FORMAT = 4004;         // El formato de un campo es incorrecto.

    public const INVALID_VALUE = 4005;          // El valor de un campo no es aceptable.

    public const QUERY_ARGUMENT_ERROR = 4006;   // Argumento inválido en consulta Eloquent/Query Builder.

    /**
     * Errores de autenticación (4100-4199)
     */
    public const UNAUTHENTICATED = 4101;        // El usuario no está autenticado.

    public const INVALID_CREDENTIALS = 4102;    // Las credenciales de autenticación son inválidas.

    public const TOKEN_EXPIRED = 4103;          // El token de autenticación ha expirado.

    public const TOKEN_INVALID = 4104;          // El token de autenticación es inválido o está corrupto.

    public const SESSION_EXPIRED = 4105;        // La sesión del usuario ha expirado.

    /**
     * Errores de autorización (4200-4299)
     */
    public const UNAUTHORIZED = 4201;           // El usuario no tiene autorización general.

    public const FORBIDDEN = 4202;              // El acceso al recurso está prohibido.

    public const INSUFFICIENT_PERMISSIONS = 4203; // El usuario no tiene los permisos necesarios.

    public const ACCESS_DENIED = 4204;          // Acceso denegado a un recurso específico.

    /**
     * Errores de recursos (4300-4399)
     */
    public const RESOURCE_NOT_FOUND = 4301;     // El recurso solicitado no fue encontrado.

    public const USER_NOT_FOUND = 4302;         // El usuario especificado no fue encontrado.

    public const MODEL_NOT_FOUND = 4303;        // Una instancia del modelo no fue encontrada.

    public const ENDPOINT_NOT_FOUND = 4304;     // El endpoint de la API solicitado no existe.

    public const MASS_ASSIGNMENT_ERROR = 4305;  // Asignación masiva no permitida.

    public const RELATION_NOT_FOUND = 4306;     // Relación Eloquent no encontrada en el modelo.

    public const MISSING_ATTRIBUTE = 4307;      // Atributo requerido no existe en el modelo.

    /**
     * Errores de conflicto (4400-4499)
     */
    public const CONFLICT = 4401;               // Se ha producido un conflicto con el estado actual del recurso.

    public const DUPLICATE_ENTRY = 4402;        // Intento de crear un recurso duplicado.

    public const RESOURCE_ALREADY_EXISTS = 4403; // El recurso ya existe.

    public const CONCURRENT_MODIFICATION = 4404; // Conflicto por modificación concurrente.

    public const DEADLOCK_ERROR = 4405;         // Deadlock en base de datos.

    /**
     * Errores de límites (4500-4599)
     */
    public const TOO_MANY_REQUESTS = 4501;      // Demasiadas solicitudes en un período corto.

    public const RATE_LIMIT_EXCEEDED = 4502;    // Se ha excedido el límite de tasa de la API.

    public const QUOTA_EXCEEDED = 4503;         // Se ha excedido la cuota de uso.

    public const FILE_TOO_LARGE = 4504;         // El archivo subido excede el tamaño máximo permitido.

    /**
     * Errores del servidor (5000-5099)
     */
    public const INTERNAL_SERVER_ERROR = 5001;  // Error interno genérico del servidor.

    public const DATABASE_ERROR = 5002;         // Error durante una operación de base de datos.

    public const EXTERNAL_SERVICE_ERROR = 5003; // Error al interactuar con un servicio externo.

    public const CONFIGURATION_ERROR = 5004;    // Error de configuración del sistema.

    public const MAINTENANCE_MODE = 5005;       // El servicio está en modo mantenimiento.

    public const PDO_ERROR = 5006;              // Error PDO de conexión/ejecución.

    public const CONNECTION_ERROR = 5007;       // Error de conexión a base de datos.

    public const TRANSACTION_ERROR = 5008;      // Error en transacción de base de datos.

    public const RUNTIME_ERROR = 5009;          // Error en tiempo de ejecución de la aplicación.

    public const LOGIC_ERROR = 5010;            // Error lógico en la ejecución del código.

    /**
     * Errores de servicios específicos (5100-5199)
     */
    public const EMAIL_SERVICE_ERROR = 5101;    // Error con el servicio de correo electrónico.

    public const SMS_SERVICE_ERROR = 5102;      // Error con el servicio de SMS.

    public const PAYMENT_SERVICE_ERROR = 5103;  // Error con el servicio de pagos.

    public const STORAGE_SERVICE_ERROR = 5104;  // Error con el servicio de almacenamiento.

    /**
     * Errores de operaciones CRUD (5200-5299)
     */
    public const RESOURCE_CREATE_FAILED = 5201; // Falló la creación del recurso.

    public const RESOURCE_UPDATE_FAILED = 5202; // Falló la actualización del recurso.

    public const RESOURCE_DELETE_FAILED = 5203; // Falló la eliminación del recurso.

    public const DEPENDENCY_CONSTRAINT_VIOLATION = 5204; // Violación de una restricción de dependencia.

    /**
     * Errores de negocio específicos (6000-6099)
     */
    public const BUSINESS_RULE_VIOLATION = 6001;   // Se ha violado una regla de negocio.

    public const INSUFFICIENT_BALANCE = 6002;      // Saldo insuficiente para realizar una operación.

    public const OPERATION_NOT_ALLOWED = 6003;     // La operación no está permitida para el estado o contexto actual.

    public const EXPIRED_RESOURCE = 6004;          // El recurso ha expirado y no se puede usar.

    public const INVALID_STATE_TRANSITION = 6005;  // Transición de estado no válida para un recurso.

    // =========================================================================
    // MAPA DE CÓDIGOS DE ERROR
    // =========================================================================

    public const ERROR_CODES = [
        // Errores de validación
        'VALIDATION_ERROR' => 4001,
        'INVALID_INPUT' => 4002,
        'MISSING_FIELD' => 4003,
        'INVALID_FORMAT' => 4004,
        'INVALID_VALUE' => 4005,
        'QUERY_ARGUMENT_ERROR' => 4006,

        // Errores de autenticación
        'UNAUTHENTICATED' => 4101,
        'INVALID_CREDENTIALS' => 4102,
        'TOKEN_EXPIRED' => 4103,
        'TOKEN_INVALID' => 4104,
        'SESSION_EXPIRED' => 4105,

        // Errores de autorización
        'UNAUTHORIZED' => 4201,
        'FORBIDDEN' => 4202,
        'INSUFFICIENT_PERMISSIONS' => 4203,
        'ACCESS_DENIED' => 4204,

        // Errores de recursos
        'RESOURCE_NOT_FOUND' => 4301,
        'USER_NOT_FOUND' => 4302,
        'MODEL_NOT_FOUND' => 4303,
        'ENDPOINT_NOT_FOUND' => 4304,
        'MASS_ASSIGNMENT_ERROR' => 4305,
        'RELATION_NOT_FOUND' => 4306,
        'MISSING_ATTRIBUTE' => 4307,

        // Errores de conflicto
        'CONFLICT' => 4401,
        'DUPLICATE_ENTRY' => 4402,
        'RESOURCE_ALREADY_EXISTS' => 4403,
        'CONCURRENT_MODIFICATION' => 4404,
        'DEADLOCK_ERROR' => 4405,

        // Errores de límites
        'TOO_MANY_REQUESTS' => 4501,
        'RATE_LIMIT_EXCEEDED' => 4502,
        'QUOTA_EXCEEDED' => 4503,
        'FILE_TOO_LARGE' => 4504,

        // Errores del servidor
        'INTERNAL_SERVER_ERROR' => 5001,
        'DATABASE_ERROR' => 5002,
        'EXTERNAL_SERVICE_ERROR' => 5003,
        'CONFIGURATION_ERROR' => 5004,
        'MAINTENANCE_MODE' => 5005,
        'PDO_ERROR' => 5006,
        'CONNECTION_ERROR' => 5007,
        'TRANSACTION_ERROR' => 5008,
        'RUNTIME_ERROR' => 5009,
        'LOGIC_ERROR' => 5010,

        // Errores de servicios
        'EMAIL_SERVICE_ERROR' => 5101,
        'SMS_SERVICE_ERROR' => 5102,
        'PAYMENT_SERVICE_ERROR' => 5103,
        'STORAGE_SERVICE_ERROR' => 5104,

        // Errores de operaciones CRUD
        'RESOURCE_CREATE_FAILED' => 5201,
        'RESOURCE_UPDATE_FAILED' => 5202,
        'RESOURCE_DELETE_FAILED' => 5203,
        'DEPENDENCY_CONSTRAINT_VIOLATION' => 5204,

        // Errores de negocio
        'BUSINESS_RULE_VIOLATION' => 6001,
        'INSUFFICIENT_BALANCE' => 6002,
        'OPERATION_NOT_ALLOWED' => 6003,
        'EXPIRED_RESOURCE' => 6004,
        'INVALID_STATE_TRANSITION' => 6005,
    ];

    // =========================================================================
    // ACCIONES DE RECUPERACIÓN SUGERIDAS
    // =========================================================================

    public const ACTIONS = [
        'VALIDATION_ERROR' => 'Revisar los datos enviados y corregir los errores',
        'INVALID_INPUT' => 'Verificar el formato y valores de los datos enviados',
        'MISSING_FIELD' => 'Completar todos los campos requeridos',
        'QUERY_ARGUMENT_ERROR' => 'Revisar los argumentos de la consulta',
        'UNAUTHENTICATED' => 'Iniciar sesión o renovar el token',
        'INVALID_CREDENTIALS' => 'Verificar usuario y contraseña',
        'TOKEN_EXPIRED' => 'Renovar el token de autenticación',
        'FORBIDDEN' => 'Contactar al administrador para obtener permisos',
        'INSUFFICIENT_PERMISSIONS' => 'Solicitar permisos al administrador',
        'RESOURCE_NOT_FOUND' => 'Verificar que el recurso existe',
        'MASS_ASSIGNMENT_ERROR' => 'Verificar los campos fillable en el modelo',
        'RELATION_NOT_FOUND' => 'Verificar que la relación existe en el modelo',
        'MISSING_ATTRIBUTE' => 'Verificar que el atributo existe en la tabla',
        'CONFLICT' => 'Refrescar los datos y reintentar',
        'DUPLICATE_ENTRY' => 'Verificar que el registro no exista previamente',
        'DEADLOCK_ERROR' => 'Reintentar la operación con backoff exponencial',
        'TOO_MANY_REQUESTS' => 'Esperar antes de realizar más solicitudes',
        'INTERNAL_SERVER_ERROR' => 'Reintentar más tarde o contactar soporte',
        'DATABASE_ERROR' => 'Verificar la integridad de los datos',
        'PDO_ERROR' => 'Verificar conexión con la base de datos',
        'CONNECTION_ERROR' => 'Verificar configuración de conexión en .env',
        'TRANSACTION_ERROR' => 'Verificar la integridad de la transacción',
        'RUNTIME_ERROR' => 'Contactar soporte técnico',
        'LOGIC_ERROR' => 'Revisar la lógica de negocio implementada',
        'RESOURCE_CREATE_FAILED' => 'Revisar los datos e intentar nuevamente',
        'RESOURCE_UPDATE_FAILED' => 'Refrescar el recurso y reintentar la actualización',
        'RESOURCE_DELETE_FAILED' => 'Verificar dependencias antes de eliminar',
        'DEPENDENCY_CONSTRAINT_VIOLATION' => 'Resolver las dependencias antes de eliminar',
        'OPERATION_NOT_ALLOWED' => 'Revisar las reglas de negocio',
        'INVALID_STATE_TRANSITION' => 'Verificar el estado actual del recurso',
    ];

    // =========================================================================
    // DESCRIPCIONES DE ERRORES
    // =========================================================================

    public const DESCRIPTIONS = [
        'VALIDATION_ERROR' => 'Error en la validación de datos de entrada',
        'INVALID_INPUT' => 'Los datos proporcionados no son válidos',
        'MISSING_FIELD' => 'Falta un campo requerido',
        'QUERY_ARGUMENT_ERROR' => 'Argumento inválido en la consulta',
        'UNAUTHENTICATED' => 'El usuario no está autenticado',
        'FORBIDDEN' => 'El usuario no tiene permisos para esta acción',
        'INSUFFICIENT_PERMISSIONS' => 'Permisos insuficientes para realizar la operación',
        'RESOURCE_NOT_FOUND' => 'El recurso solicitado no existe',
        'MASS_ASSIGNMENT_ERROR' => 'Intento de asignación masiva no permitida',
        'RELATION_NOT_FOUND' => 'Relación Eloquent no encontrada en el modelo',
        'MISSING_ATTRIBUTE' => 'Atributo requerido no existe en el modelo',
        'CONFLICT' => 'Conflicto con el estado actual del recurso',
        'DUPLICATE_ENTRY' => 'El registro ya existe en el sistema',
        'DEADLOCK_ERROR' => 'Conflicto de concurrencia en la base de datos',
        'TOO_MANY_REQUESTS' => 'Se ha excedido el límite de solicitudes',
        'INTERNAL_SERVER_ERROR' => 'Error interno del servidor',
        'DATABASE_ERROR' => 'Error en la operación de base de datos',
        'PDO_ERROR' => 'Error en la capa PDO de base de datos',
        'CONNECTION_ERROR' => 'Error al conectar con la base de datos',
        'TRANSACTION_ERROR' => 'Error durante la ejecución de la transacción',
        'RUNTIME_ERROR' => 'Error en tiempo de ejecución de la aplicación',
        'LOGIC_ERROR' => 'Error lógico en la ejecución del código',
        'RESOURCE_CREATE_FAILED' => 'Falló la creación del recurso',
        'RESOURCE_UPDATE_FAILED' => 'Falló la actualización del recurso',
        'RESOURCE_DELETE_FAILED' => 'Falló la eliminación del recurso',
        'DEPENDENCY_CONSTRAINT_VIOLATION' => 'Violación de restricción de dependencia',
        'OPERATION_NOT_ALLOWED' => 'La operación solicitada no está permitida',
        'INVALID_STATE_TRANSITION' => 'Transición de estado no válida',
    ];

    // =========================================================================
    // MAPEO DE EXCEPCIONES A CÓDIGOS SEMÁNTICOS
    // =========================================================================

    public const EXCEPTION_CODE_ERROR = [
        404 => 'RESOURCE_NOT_FOUND',
        401 => 'UNAUTHENTICATED',
        403 => 'FORBIDDEN',
        400 => 'INVALID_INPUT',
        409 => 'CONFLICT',
        422 => 'VALIDATION_ERROR',
        429 => 'TOO_MANY_REQUESTS',
        500 => 'INTERNAL_SERVER_ERROR',
        503 => 'CONNECTION_ERROR',
    ];

    // =========================================================================
    // ACCIONES DE RECUPERACIÓN DETALLADAS
    // =========================================================================

    public const RECOVERY_ACTIONS = [
        'VALIDATION_ERROR' => [
            'fix_input' => 'Corregir los datos de entrada según los mensajes de error',
            'validate_schema' => 'Validar que los datos cumplen con el esquema requerido',
        ],
        'RESOURCE_NOT_FOUND' => [
            'retry_request' => 'Reintentar la solicitud después de verificar que el recurso existe',
            'check_resource_existence' => 'Verificar que el recurso existe en la base de datos',
        ],
        'UNAUTHENTICATED' => [
            'login' => 'Iniciar sesión para obtener un nuevo token',
            'refresh_token' => 'Renovar el token de autenticación si está expirado',
        ],
        'FORBIDDEN' => [
            'contact_admin' => 'Contactar al administrador para solicitar permisos',
            'check_permissions' => 'Verificar los permisos del usuario',
        ],
        'MASS_ASSIGNMENT_ERROR' => [
            'check_fillable' => 'Verificar que el campo está en $fillable del modelo',
            'use_force_fill' => 'Usar forceFill() si es necesario (con precaución)',
        ],
        'RELATION_NOT_FOUND' => [
            'verify_relation' => 'Verificar que el método de relación existe en el modelo',
            'check_import' => 'Verificar que el modelo relacionado está importado',
        ],
        'DUPLICATE_ENTRY' => [
            'check_existing' => 'Verificar si el registro ya existe antes de crear',
            'use_update_or_create' => 'Considerar usar updateOrCreate() para upserts',
        ],
        'DEADLOCK_ERROR' => [
            'retry_with_backoff' => 'Reintentar con backoff exponencial (3 intentos)',
            'check_long_transactions' => 'Revisar transacciones largas que puedan causar deadlock',
            'optimize_queries' => 'Optimizar consultas que afectan múltiples tablas',
        ],
        'CONFLICT' => [
            'refresh_data' => 'Refrescar los datos y reintentar la operación',
            'check_resource_state' => 'Verificar el estado actual del recurso',
        ],
        'TOO_MANY_REQUESTS' => [
            'implement_rate_limiting' => 'Implementar límites de velocidad en el cliente',
            'use_exponential_backoff' => 'Usar backoff exponencial para reintentos',
        ],
        'DATABASE_ERROR' => [
            'check_integrity' => 'Verificar la integridad de los datos',
            'retry_operation' => 'Reintentar la operación',
        ],
        'PDO_ERROR' => [
            'check_connection' => 'Verificar credenciales en .env',
            'verify_database' => 'Verificar que la base de datos está en ejecución',
            'check_network' => 'Verificar conectividad de red',
        ],
        'CONNECTION_ERROR' => [
            'check_env' => 'Verificar variables de entorno DB_*',
            'check_database_status' => 'Verificar que el servicio de BD está activo',
            'check_firewall' => 'Verificar reglas de firewall',
        ],
        'TRANSACTION_ERROR' => [
            'verify_transaction_scope' => 'Verificar que la transacción está correctamente anidada',
            'check_db_savepoints' => 'Revisar que los savepoints son compatibles con el motor',
        ],
        'DEPENDENCY_CONSTRAINT_VIOLATION' => [
            'resolve_dependencies' => 'Resolver las dependencias antes de eliminar el recurso',
            'check_related_resources' => 'Verificar los recursos relacionados',
        ],
        'OPERATION_NOT_ALLOWED' => [
            'review_business_rules' => 'Revisar las reglas de negocio que impiden la operación',
            'check_resource_state' => 'Verificar el estado actual del recurso',
        ],
        'INVALID_STATE_TRANSITION' => [
            'check_current_state' => 'Verificar el estado actual del recurso',
            'review_state_machine' => 'Revisar la máquina de estados del recurso',
        ],
        'INTERNAL_SERVER_ERROR' => [
            'retry_request' => 'Reintentar la solicitud después de unos minutos',
            'check_service_status' => 'Verificar el estado del servicio',
            'contact_support' => 'Contactar soporte técnico si persiste',
        ],
    ];
}
