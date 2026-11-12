# SPEC-012: Optimización de Planillas de Control de Entrega de Servicio y Eliminación de `$with` Global

## Problem Statement

El modelo Eloquent principal de planillas de control de entrega de servicio (`ServiceDeliveryControlSheet`) declara una propiedad global protegida `$with` con seis relaciones obligatorias (`vehicle`, `thirdParty`, `project`, `signatures`, `routes`, `items`). Esto obliga al framework a ejecutar seis consultas SQL adicionales en absolutamente toda consulta que involucre a este modelo en cualquier parte del sistema (incluyendo conteos rápidos, monitoreo de tracking, resúmenes del dashboard y comprobaciones de existencia).
Además, el método de creación masiva por proyecto (`createBulkForProject`), utilizado para generar planillas para múltiples vehículos y días del mes (por ejemplo, 14 vehículos por 30 días = 420 días operativos), ejecuta consultas reiteradas para resolver las rutas del padre e inserta cada fila y sus registros hijos de manera individual en bucles anidados. Esto produce más de 1,800 a 2,000 sentencias SQL individuales, tardando más de 3.3 segundos por operación y saturando la conexión a base de datos.
Por último, el cálculo de evidencias (`PcpEvidence::resumenProyectosEnLote`) hidrata miles de modelos en memoria junto con sus seis relaciones obligatorias, generando un consumo desmedido de RAM.

## Solution

Retirar la propiedad global `$with` del modelo de planillas de control de entrega de servicio, moviendo la carga de relaciones a un ámbito local explícito (`scopeConRelacionesCompletas` o carga `with()` explícita en los controladores y servicios que verdaderamente requieran el detalle exhaustivo).
Rediseñar el flujo de creación masiva (`createBulkForProject`) para extraer las consultas de rutas y catálogos fuera de los bucles iterativos, resolviendo las claves foráneas en memoria y realizando inserciones en bloque (`insert`) para las planillas diarias, los puntos de control y las rutas asociadas.
Optimizar la consolidación de evidencias en lote (`resumenProyectosEnLote`) para realizar agregaciones y consultas directas sin disparar la carga masiva de relaciones innecesarias.

## User Stories

1. Como Coordinador de Operaciones, quiero generar las hojas de control mensuales para todos los vehículos asignados a un proyecto en una sola acción rápida (menos de 300 ms), para que la planeación operativa no bloquee la interfaz ni agote los recursos del servidor.
2. Como Administrador de Empresa, quiero consultar el listado general de planillas de control con paginación ágil y fluida, para supervisar el cumplimiento de rutas sin esperar a que el servidor consulte relaciones que no se visualizan en la tabla.
3. Como Conductor, quiero que la verificación de mi planilla de control activa en el móvil responda de forma instantánea, para iniciar mi jornada de transporte sin retrasos.
4. Como Auditor del Sistema, quiero que las comprobaciones de evidencias y firmas digitales operen de forma eficiente y consistente, sin riesgo de errores por agotamiento de memoria (`memory_limit`) o desconexión de MySQL.
5. Como Desarrollador de la Plataforma, quiero que el modelo de planillas de control sea ligero por defecto, de modo que nuevos módulos o consultas analíticas puedan interactuar con él sin pagar un sobrecosto automático de seis consultas por registro.

## Implementation Decisions

- **Eliminación del Eager Loading forzado en el Modelo:**
  - Se suprimirá el arreglo `$with = [...]` del modelo `ServiceDeliveryControlSheet`.
  - Las vistas de detalle, generación de PDF y firma digital que requieran las relaciones completas las declararán explícitamente mediante `with(...)` en su respectivo servicio.
- **Inserción Masiva en Bloque para Generación de Proyectos:**
  - Se desacoplará la preparación de datos de la persistencia: primero se compondrá el conjunto de filas en memoria y luego se ejecutarán inserciones por lotes de 100 a 500 registros utilizando `DB::table('service_delivery_control_sheets')->insert(...)` o transacciones masivas.
  - Las rutas estándar del proyecto se consultarán una única vez antes de iniciar el bucle de fechas y vehículos.
- **Consultas Escalares para Evidencias de PCP:**
  - El servicio de evidencias procesará los estados de cumplimiento mediante consultas agrupadas en SQL, sin hidratar colecciones de modelos completos con objetos anidados en PHP.
- **Integridad de Eventos y Auditoría:**
  - Dado que las inserciones directas por lote omiten eventos de Eloquent, se asegurará la generación de UUIDs y marcas de tiempo (`created_at`, `updated_at`) de manera determinista en el arreglo antes de la persistencia.

## Testing Decisions

- **Costura única y más alta posible:**
  - Pruebas HTTP contra los endpoints de creación masiva (`POST /api/v1/service-delivery-control-sheets/bulk-project`) y listado paginado (`GET /api/v1/service-delivery-control-sheets`).
- **Verificación de presupuesto de consultas:**
  - Extender la prueba existente `CreacionMasivaHojasTest` para verificar que la generación masiva de 14 vehículos por 30 días reduzca drásticamente su número de consultas (de ~2,000 consultas a menos de 30 consultas SQL).
- **Aserción de ausencia de `$with` en consultas ligeras:**
  - Verificar que una consulta de conteo (`ServiceDeliveryControlSheet::where(...)->count()`) o de búsqueda por clave primaria ejecute exactamente una sola sentencia SQL sin subconsultas a relaciones no solicitadas.
- **Antecedentes de prueba en el repositorio:**
  - `CreacionMasivaHojasTest`, `EdicionHojaControlTest`, `ServiceControlSheetRowsTest`.

## Out of Scope

- Cambios en las firmas digitales o en el almacenamiento de evidencias en medios externos.
- Modificación de la estructura de tablas o eliminación de campos en `service_delivery_control_sheets`.
- Rediseño de las pantallas de gestión de planillas en Vue 3.

## Further Notes

- Esta optimización desbloquea un rendimiento notable en el dashboard del conductor y en el monitor de rastreo GPS, que con frecuencia consultan la planilla del día.

## Implementation & Performance Results (2026-11-05)

- **Estado:** IMPLEMENTADO y validado con suite de pruebas completa en commit `db46c3a` (`spec-012-optimizacion-planillas-control`).
- **Cambios Principales:**
  1. **Eliminación de `$with` global en `ServiceDeliveryControlSheet`:**
     - Se suprimió la precarga automática obligatoria de relaciones (`project`, `routes`, `internalControl.vehicle`, `internalControl.thirdParty`, `subcontractedControl.vehicleClass`).
     - Se crearon las constantes `RELACIONES_BASICAS` y `RELACIONES_DETALLE`, expuestas mediante `scopeConRelacionesCompletas($query)` y `scopeConRelacionesDetalle($query)`.
     - Los accesores `$appends` (`vehicle_license_plate` y `driver_name`) evalúan `relationLoaded` estricto (retornando `null` si no están cargadas o delegando en el respaldo plano de `official_name_and_surname`), imposibilitando la ejecución inadvertida de consultas N+1 en segundo plano.
  2. **Inserción Masiva en `createBulkForProject`:**
     - Validación en lote de vehículos y terceros pertenecientes a la empresa y sede activa mediante consultas agrupadas (`whereIn`), fallando cerrado ante cualquier inconsistencia.
     - Extracción previa de plantillas de recorridos del proyecto con `order_index` 1-based consistente con `syncRoutes`.
     - Ensamblado determinista en memoria de identificadores UUID, marcas de tiempo (`created_at`, `updated_at`), planillas padre, hijas diarias y controles internos.
     - Persistencia en bloque mediante `DB::table(...)->insert($chunk)` en fragmentos de 200 registros dentro de una única transacción de base de datos.
  3. **Precarga Explícita en Servicios, Controladores y Exportaciones:**
     - Actualizados `getAllServiceDeliveryControlSheetsWithPagination`, `getAllServiceDeliveryControlSheets` (`/list`), `getServiceDeliveryControlSheetByUuid`, `persistirHoja`, `updateServiceDeliveryControlSheet`, `startServiceDeliveryControlSheet`, `closeServiceDeliveryControlSheet`, `cerrarConExcepcion` y `closeRoute`.
     - `ServiceControlSheetExport::collection` y `PdfService` (`generateDailyServiceControlSheetPdf`, `generateMonthlyServiceControlSheetPdf`, `generateFilteredServiceControlSheetPdf`) aplican carga explícita de relaciones requeridas.
     - `PcpEvidence::resumenProyectosEnLote` precarga relaciones en bloque para padres e hijos, garantizando que las jornadas en disponibilidad identifiquen correctamente al conductor responsable sin disparar N+1 ni falsos faltantes.

- **Métricas Cuantitativas de Rendimiento:**
  | Escenario / Operación | Antes de SPEC-012 | Después de SPEC-012 | Ganancia / Impacto |
  | :--- | :--- | :--- | :--- |
  | **Creación masiva de 14 vehículos × 30 días** (434 planillas + 434 controles) | 3.65 segundos | **0.12 segundos** | **~96.7% más rápido** (29x) |
  | **Consultas SQL en creación masiva** | >1,800 sentencias SQL individuales | **44 consultas HTTP** (20 en el servicio) | **~97.5% menos consultas** |
  | **Consultas ligeras del modelo** (`count()`, `first()`, `exists()`) | 6 consultas (1 conteo + 5 precargas `$with`) | **1 consulta SQL exacta** | **83.3% menos consultas** |
  | **Listado paginado general** (15 registros por página) | Consultas variables con N+1 latente | **26 consultas controladas en lote** | Sin N+1 por fila |
  | **Generación de Reportes / PDF / Excel** | N+1 por cada fila iterada | **1 consulta `whereIn` por relación** | Operación constante en memoria |

- **Cobertura de Pruebas Automatizadas:**
  - `CreacionMasivaHojasTest`: 14 casos (110 aserciones) en verde.
  - `EdicionHojaControlTest`: 14 casos (159 aserciones) en verde.
  - `EvidenceStatusPdfTest`: 3 casos (10 aserciones) en verde.
  - `PcpAislamientoTest`: 9 casos (35 aserciones) en verde.
  - `ServiceControlSheetPdfLayoutSmokeTest`: 1 caso (15 aserciones) en verde.
  - `ServiceControlSheetRowsTest`: 16 casos (54 aserciones) en verde.
  - `ServiceControlSheetSignaturesTest`: 5 casos (7 aserciones) en verde.
  - `FirmasDeClasesTest`: 1 caso (6 aserciones) en verde.

