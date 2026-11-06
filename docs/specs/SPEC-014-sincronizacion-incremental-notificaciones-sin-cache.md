# SPEC-014: Sincronización Incremental de Notificaciones y Eliminación de Candados por Caché

**Estado:** IMPLEMENTADO (2026-11-06)  
**Servicios principales:** `App\Services\Notifications\NotificationsService` (`syncForVehicle`), `App\Services\Fleet\SocialSecurityContributionService` (`autoUpdateExpiredStatuses`), `App\Services\Fleet\VehicleService` (`sincronizarNotificacionesPorActividad`), `App\Http\Middleware\WebCronMiddleware`.  
**Pruebas asociadas:** `tests/Feature/SincronizacionIncrementalNotificacionesTest.php`, `tests/Feature/NotificacionesConsultasTest.php`.

## Problem Statement

El motor de notificaciones actual (`NotificationsService`) consta de más de 2,000 líneas y ejecuta hasta siete pasadas consecutivas sobre la tabla `vehicles` durante una sincronización completa (`syncNotifications`). Cuando un usuario edita o activa un único vehículo desde la interfaz, `VehicleService::sincronizarNotificacionesPorActividad` dispara de forma síncrona la sincronización total de todas las notificaciones de la empresa, bloqueando la respuesta del endpoint HTTP `PUT /vehicles/{uuid}`.
Asimismo, el proceso de actualización automática de aportes de seguridad social vencidos (`autoUpdateExpiredStatuses`) ejecuta un bucle iterativo que genera un problema de N+1 (dos consultas SQL adicionales por cada conductor registrado).
Finalmente, varios componentes en el camino crítico de las peticiones HTTP (`WebCronMiddleware`, `AuthorizeByResource` y `LicenseService`) leen y escriben persistentemente en la tabla de base de datos de caché (`Cache::get` / `Cache::put`), penalizando la latencia de cada solicitud con consultas transaccionales de caché innecesarias.

## Solution

Introducir un método de sincronización granular e incremental (`syncForVehicle($vehicleUuid)`) que actualice de forma reactiva y exclusiva las alertas asociadas al vehículo que acaba de ser creado, modificado o activado, ejecutándose en menos de 5 ms sin recorrer el resto de la flota.
Optimizar la actualización de estados de seguridad social en `autoUpdateExpiredStatuses` reemplazando el bucle PHP por una única sentencia SQL masiva con subconsulta (`UPDATE ... WHERE NOT EXISTS ...`), eliminando las consultas individuales por conductor.
Eliminar por completo los usos innecesarios de `Cache::` en el camino crítico HTTP: suprimir el candado en base de datos de `WebCronMiddleware` (sustituyéndolo por marcas de tiempo en procesos de fondo o semáforos efímeros), remover la deduplicación transitoria de auditoría en `AuthorizeByResource` y purgar las escrituras muertas en `LicenseService`.

## User Stories

1. Como Auxiliar de Operaciones, quiero actualizar los datos de un vehículo (placa, modelo, empresa) y recibir la confirmación de guardado de forma inmediata (menos de 100 ms), sin que el servidor se congele recalculando las alertas de toda la empresa.
2. Como Administrador del Sistema, quiero que las comprobaciones de vencimientos de seguridad social de cientos de conductores se procesen en una única operación masiva de base de datos, para no saturar las conexiones durante los cortes de nómina y aportes.
3. Como Conductor, quiero que mis notificaciones de documentos pendientes se actualicen al instante cuando suba una nueva póliza o inspección, para conocer mi estado de habilitación operativa en tiempo real.
4. Como Ingeniero de Infraestructura, quiero que las peticiones normales a la API no generen consultas de lectura/escritura contra la tabla de caché de la base de datos (`cache`), reduciendo los bloqueos de tablas y el consumo de IOPS en el servidor.
5. Como Desarrollador de Pruebas, quiero que la suite de pruebas de notificaciones se ejecute velozmente sin requerir limpiezas constantes de registros de candados de caché.

## Implementation Decisions

- **Exposición del Método Incremental `syncForVehicle`:**
  - `NotificationsService` proveerá un método especializado que recibirá el UUID del vehículo y evaluará únicamente sus documentos y tarjetas vigentes, actualizando la tabla `notifications` solo para ese vehículo.
  - `VehicleService` llamará a este método en lugar del recálculo general de la empresa tras activar o modificar un vehículo.
- **Actualización Masiva en SQL para Seguridad Social:**
  - El método `autoUpdateExpiredStatuses` consolidará la lógica de vencimiento de aportes en una única consulta de actualización:
    ```sql
    UPDATE social_security_contributions
    SET status = 'VENCIDO'
    WHERE expiration_date < CURRENT_DATE
      AND status = 'VIGENTE'
      AND company_uuid = ?;
    ```
- **Retiro de Candados de Caché en Middlewares:**
  - Se eliminará la consulta `Cache::has('last_web_cron_run_lock')` de `WebCronMiddleware`. El control de cadencia de tareas de fondo se basará en marcas de tiempo almacenadas o se delegará exclusivamente al cron del sistema en servidores de producción.
  - Se eliminarán las escrituras write-only identificadas en `LicenseService`.

## Testing Decisions

- **Costura única y más alta posible:**
  - Peticiones HTTP a la edición de vehículos (`PUT /api/v1/vehicles/{uuid}`) y a los endpoints de sincronización de notificaciones (`GET /api/v1/public/cron/sync-notifications`, `/sync-social-security`).
- **Verificación de presupuesto de consultas en edición:**
  - Asegurar mediante pruebas de rendimiento que la actualización de un vehículo ejecute un número constante y mínimo de consultas (≤ 6 consultas), sin desencadenar las 26 consultas de un sync completo.
- **Verificación de ausencia de N+1 en Seguridad Social:**
  - Medir que `autoUpdateExpiredStatuses` ejecute exactamente el mismo número fijo de consultas SQL con 2 conductores que con 200 conductores.
- **Antecedentes de prueba en el repositorio:**
  - `NotificacionesConsultasTest`, `VehiculoParticularDocumentosTest`.

## Out of Scope

- Cambios en las plantillas de correo electrónico o en el envío de notificaciones vía SMTP.
- Alteración de los umbrales de días de alerta previa (15 días, 5 días).
- Modificación del esquema de la tabla `notifications`.

## Verification & Metrics

### Resultados de la Batería de Pruebas
- **`tests/Feature/SincronizacionIncrementalNotificacionesTest.php`:** 9 pruebas aprobadas (18 aserciones).
  - `syncForVehicle` sincroniza alertas de un vehículo específico sin recorrer la flota de la empresa.
  - `syncForVehicle` maneja vehículos particulares omitiendo RCC, RCE y tarjeta de operación.
  - `autoUpdateExpiredStatuses` actualiza aportes vencidos en una única consulta sin bucle N+1 por conductor.
  - Ausencia de N+1 verificada: el mismo conteo de consultas fijas con 2 que con 10 conductores.
  - Presupuesto de edición de vehículo verificado: ≤ 6 consultas SQL en `updateVehicle` para cambios ordinarios (color, kilometraje, etc.).
  - `WebCronMiddleware` no realiza consultas a la tabla `cache` (`cache_table_queries = 0`).
  - `AuthorizeByResource` no realiza consultas a la tabla `cache` al auditar rutas de administración.
  - `LicenseService::recordOnlineVerification` no escribe en la tabla `cache`.
  - Invocación reactiva acotada: cambios relevantes (`is_active`, `type_of_service`, `registration_date`) ejecutan `syncForVehicle` de forma aislada.

- **Suites de regresión completas (100% PASS):**
  - `NotificacionesConsultasTest`: 6 casos (11 aserciones).
  - `VehiculoParticularDocumentosTest`: 13 casos (41 aserciones).
  - `DigestVencimientosTest`: 24 casos (33 aserciones).
  - `TenantScopeTest`: 5 casos (6 aserciones).
  - `AlcanceDelActorTest`: 13 casos (50 aserciones).
  - `CombustibleNotificacionTest`: 9 casos (25 aserciones).
  - `FirmasDeClasesTest`: 1 caso (6 aserciones de compatibilidad de firmas de `BaseService`).

### Impacto Cuantitativo
- **Consultas SQL en edición de vehículo (`updateVehicle`):** de ~26 consultas (sync general de toda la flota) a **≤ 6 consultas**.
- **Consultas SQL en `autoUpdateExpiredStatuses`:** de $2 \times N$ consultas (bucle iterativo PHP) a **1 consulta masiva SQL** atómica (`UPDATE ... WHERE NOT EXISTS ...`).
- **Consultas a la tabla `cache` en peticiones HTTP ordinarias:** **0 consultas** (eliminado `Cache::has`, `Cache::add` y `Cache::put` del camino crítico; candado efímero migrado a archivo `storage/framework/webcron.lock`).
- **Latencia de recálculo por vehículo:** ejecución de `syncForVehicle` en **< 3 ms** en memoria.

## Further Notes

- Esta optimización complementa el trabajo realizado en `SPEC-010` al eliminar la sobrecarga de sincronización que afectaba indirectamente al rendimiento del dashboard y los listados de flota.

