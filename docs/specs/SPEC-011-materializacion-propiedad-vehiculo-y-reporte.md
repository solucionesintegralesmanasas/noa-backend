# SPEC-011: Materialización de la Propiedad del Vehículo y Optimización del Reporte de Vehículos

## Problem Statement

Actualmente, determinar si un vehículo es propio de la empresa o pertenece a un afiliado/tercero requiere evaluar expresiones regulares y comparaciones de cadenas de texto en memoria (`OwnCompany::esVehiculoPropio`), apoyadas en un arreglo estático `OwnCompany::$cache`. En SQL, esto se traduce en subconsultas correlacionadas complejas con múltiples cláusulas `OR LIKE` para buscar nombres normalizados de la empresa en la tarjeta de operación.
Asimismo, el servicio de reportes de vehículos (`VehicleReportService::baseQuery`) precarga hasta diez relaciones completas sin filtrar por vigencia (cargando en memoria todo el historial acumulado de años de revisiones tecnicomecánicas, pólizas SOAT, tarjetas de operación y órdenes de mantenimiento) únicamente para que PHP ejecute `sortByDesc()->first()`.
Finalmente, el calendario mensual de vencimientos reusa indebidamente la consulta masiva de exportación (`allForExport`), lo que recorta incorrectamente la ventana temporal de ±14 días de meses vecinos y distorsiona el cálculo de los indicadores globales de la flota.

## Solution

Eliminar por completo el arreglo en memoria `OwnCompany::$cache` y materializar o simplificar la evaluación de la propiedad del vehículo mediante una relación directa con la tarjeta de operación activa y vigente, normalizando los nombres propios de la empresa desde la configuración del sistema.
Rediseñar la consulta base del reporte vehicular para precargar exclusivamente los documentos y tarjetas en estado vigente o activo, evitando la hidratación de registros históricos inactivos.
Desacoplar el calendario de vencimientos del flujo de exportación masiva, implementando una consulta acotada a la ventana de fechas visible (`$windowStart..$windowEnd`) que consulte directamente las fechas de expiración sin hidratar la flota completa.

## User Stories

1. Como Administrador de Empresa, quiero consultar el reporte de vehículos sin demoras al escalar la flota, para auditar el estado documental y operativo de todos los vehículos de mi empresa en tiempo real.
2. Como Administrador de Empresa, quiero ver de forma confiable e inmediata si un vehículo es propio o de un tercero, para aplicar las políticas de cobro y asignación correspondientes sin depender de cálculos de texto en cada consulta.
3. Como Oficial de Operaciones, quiero navegar por el calendario mensual de vencimientos observando los documentos que expiran a principios y finales de mes (incluyendo días colindantes), para planificar renovaciones sin que se recorten eventos por filtros de mes estricto.
4. Como Auditor de Flota, quiero exportar a Excel y PDF el consolidado documental de vehículos sin que el servidor consuma memoria excesiva por cargar historiales obsoletos, para obtener informes limpios de la documentación actualmente vigente.
5. Como Desarrollador del Backend, quiero ejecutar pruebas automatizadas de vehículos y reportes sin tener que invocar métodos de limpieza de estado estático (`OwnCompany::limpiarCache()`), para asegurar pruebas herméticas y deterministas.
6. Como Coordinador de Mantenimiento, quiero que los indicadores clave (KPIs) del reporte vehicular reflejen la totalidad de la flota activa independientemente de los filtros de paginación o mes seleccionados en pantalla.
7. Como Conductor, quiero que la plataforma identifique con certeza a qué empresa o tercero está vinculado mi vehículo asignado, para evitar rechazos en inspecciones preoperacionales o generación de FUEC.

## Implementation Decisions

- **Eliminación del almacenamiento estático en memoria:**
  - Se retirará la propiedad estática de caché de nombres y empresas evaluadas, garantizando que ninguna clase conserve estado transitorio entre peticiones HTTP.
- **Normalización centralizada de nombres propios:**
  - Los nombres propios de la empresa se normalizarán al registrar o actualizar la configuración de la empresa (`SystemConfiguration`), guardando la forma canónica sin signos de puntuación, mayúsculas y sufijos societarios unificados (`SAS`).
- **Acotamiento de relaciones en el reporte vehicular:**
  - La consulta base del reporte de flota precargará las relaciones documentales acotadas por ámbito vigente (por ejemplo, documentos con estado vigente o la tarjeta de operación no cancelada más reciente), evitando descargar colecciones de registros vencidos de años anteriores.
- **Consulta especializada para el calendario mensual:**
  - Se creará un método de consulta dedicado para el calendario que filtre directamente en las tablas documentales por rango de fechas (`expiration_date BETWEEN $windowStart AND $windowEnd`), retornando proyecciones planas y ligeras.
- **Preservación estricta de contratos de respuesta:**
  - Las respuestas JSON para el listado paginado, los catálogos de sedes, los eventos del calendario y las exportaciones a Excel/PDF mantendrán exactamente las mismas claves y formatos esperados por la interfaz de usuario.

## Testing Decisions

- **Costura única y más alta posible:**
  - Pruebas HTTP contra los endpoints del reporte vehicular (`GET /api/v1/reports/vehicles`, `/calendar-events`, `/branches`).
- **Verificación de presupuesto y conteo constante:**
  - Extender las pruebas existentes de presupuesto del reporte (`ReporteVehiculosConsultasTest`) para comprobar que el número de consultas y la memoria permanezcan constantes sin importar el historial documental previo de los vehículos.
- **Comprobación de la ventana del calendario:**
  - Probar que eventos ubicados en los días limítrofes (últimos días del mes anterior y primeros días del mes siguiente) se incluyan en el payload del calendario sin truncamiento.
- **Antecedentes de prueba en el repositorio:**
  - `ReporteVehiculosConsultasTest`, `ReporteVehiculosFiltrosTest`, `OwnCompanyNormalizacionTest`.

## Out of Scope

- Modificación de los modelos visuales o componentes de Vue en el frontend (`VehicleReportView.vue`, `VehicleCalendar.vue`).
- Creación de nuevas columnas en la tabla `vehicles` que alteren el esquema físico sin migración previa de datos.
- Modificación de los formatos de impresión PDF o plantillas DomPDF existentes.

## Further Notes

- Esta optimización impacta positivamente a `EmailLogService` y `NotificationsService`, quienes también consumen la determinación de vehículos propios para el envío de alertas y digests matutinos.

