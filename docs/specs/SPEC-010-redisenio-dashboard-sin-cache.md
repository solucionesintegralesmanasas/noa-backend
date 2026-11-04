# SPEC-010 — Rediseño del Dashboard sin Caché y Optimización de Rendimiento

**Estado:** IMPLEMENTADO (2026-11-04)
**Fecha:** 2026-11-04
**Relacionado:** `SPEC-004-pruebas-de-rendimiento.md`, `DashboardConsultasTest`, `DashboardService`.

## Declaración del Problema

El panel principal (Dashboard) es la primera pantalla que cargan tanto los administradores de empresa como los conductores al ingresar a la plataforma. Actualmente, para sostener tiempos de respuesta aceptables, el módulo oculta un diseño subóptimo detrás de tres directivas de almacenamiento en caché (`Cache::remember` con expiración entre 60 y 180 segundos).

Esta estrategia de caché temporal ("cope") presenta graves deficiencias estructurales a medida que aumenta el volumen de vehículos, conductores e inspecciones:

1. **Explosión de memoria (RAM) por hidratación masiva en PHP:**
   - En el cálculo de kilometraje recorrido, el sistema descarga a la memoria del proceso PHP todas las filas históricas de inspecciones preoperacionales de los últimos 60 días para agruparlas y restar kilometrajes en bucles de código de aplicación, en lugar de realizar la agregación directamente en el motor de base de datos.
   - En el resumen del conductor, el sistema descarga todas las inspecciones históricas de toda la vida útil de los vehículos asignados (`->get()->unique('vehicle_uuid')`) únicamente para determinar la fecha y odómetro de la última inspección de cada vehículo.
2. **Escrituras y mutaciones en base de datos durante operaciones de solo lectura (`GET`):**
   - El endpoint de alertas del dashboard invoca una recolección en vivo de ocho fuentes distintas sobre toda la flota y ejecuta sentencias `UPDATE` (`$model->save()`) en documentos, licencias y convenios vencidos en medio de una petición de lectura.
3. **Múltiples consultas separadas y desacopladas:**
   - Cada carga sin caché ejecuta entre 12 y 24 consultas SQL fragmentadas (conteos individuales por tabla y múltiples lecturas repetitivas de pertenencia de empresa), degradando el tiempo de respuesta del servidor y consumiendo recursos innecesarios de MySQL/MariaDB.
4. **Desfase de información en tiempo real:**
   - Si un conductor registra una inspección, tanqueo o planilla, o si un administrador asocia un nuevo vehículo o actualiza un documento, los indicadores del dashboard reflejan datos obsoletos hasta que expira el temporizador de la memoria caché.

## Solución

Rediseñar en profundidad el módulo del Dashboard eliminando por completo cualquier capa de caché (`Cache::remember`), reemplazando la lógica superficial en PHP por consultas SQL agregadas de alto rendimiento ejecutadas en tiempo real.

1. **Cálculo de kilometraje en SQL agregado:**
   - Reemplazar la hidratación en memoria de miles de inspecciones por una consulta agregada con subconsultas condicionales (`MAX`, `MIN` agrupados por vehículo) que calcule los deltas de kilometraje del período actual y previo en un solo paso, devolviendo únicamente la suma escalar sin transferir colecciones de modelos a PHP.
2. **Última inspección y odómetro por vehículo en SQL:**
   - Resolver la última inspección preoperacional de cada vehículo asignado mediante una consulta acotada por vehículo usando pares `(vehicle_uuid, MAX(inspection_date))` o subconsulta correlacionada indexada, trayendo estrictamente un registro por vehículo.
3. **Conteos consolidados en una única pasada:**
   - Unificar los conteos de vehículos activos, planillas FUEC y conductores vinculados mediante agregación condicional (`COUNT(*)`, `SUM(CASE WHEN ...)`) para resolver totales actuales y comparativas previas en una sola consulta por entidad.
4. **Alertas en tiempo real desde la tabla materializada:**
   - Rediseñar la obtención de alertas para que consulte directamente la tabla indexada de notificaciones existente en lugar de reevaluar y mutar en vivo todas las entidades de la flota durante el `GET`.
5. **Presupuesto estricto de consultas sin caché:**
   - Proteger el nuevo diseño mediante pruebas automatizadas de presupuesto que aseguren un número constante y bajo de consultas (≤ 4 consultas para resumen global, ≤ 24 consultas para resumen de conductor y ≤ 12 consultas para alertas), garantizando tiempos de respuesta inferiores a 20 ms en datos en vivo.

## Historias de Usuario

1. Como administrador de empresa, quiero ver el resumen del dashboard actualizado inmediatamente después de cualquier cambio en la flota, para no tomar decisiones operativas con información desactualizada por caché.
2. Como administrador de empresa, quiero que el cálculo de kilómetros recorridos responda en milisegundos sin importar que la empresa tenga cientos de vehículos y miles de inspecciones, para que la plataforma sea ágil.
3. Como administrador de empresa, quiero que los conteos de vehículos, conductores y FUEC del período actual y anterior se calculen de manera consistente y sin disparar múltiples consultas lentas, para no sobrecargar el servidor de base de datos.
4. Como conductor, quiero ingresar a mi dashboard y ver inmediatamente el último odómetro y fecha de inspección de mis vehículos asignados sin que la plataforma descargue historiales antiguos, para iniciar mi jornada sin demoras.
5. Como conductor, quiero que la verificación de disponibilidad vehicular y turno no realice consultas redundantes, para disponer de una interfaz rápida en conexiones móviles.
6. Como administrador de empresa, quiero consultar las alertas críticas del dashboard sin que el sistema realice escrituras en base de datos durante la lectura, para preservar la integridad transaccional y el rendimiento del servidor.
7. Como desarrollador, quiero que el módulo del dashboard no utilice `Cache::remember` ni cachés en memoria, para que el diseño sea inherentemente rápido por arquitectura y no requiera mecanismos artificiales de invalidación.
8. Como desarrollador, quiero que las pruebas automatizadas verifiquen que el número de consultas del dashboard no crezca con el volumen de datos históricos, para evitar regresiones de rendimiento al escalar la operación.
9. Como administrador de sistemas en hosting compartido, quiero que las peticiones al dashboard consuman una cantidad mínima y predecible de memoria RAM en PHP, para evitar que los procesos de Apache agoten los recursos del servidor.
10. Como auditor de seguridad y datos, quiero que las operaciones de consulta `GET` sean estrictamente idempotentes y de solo lectura, evitando efectos secundarios ocultos en los registros de la empresa.

## Decisiones de Implementación

- **Eliminación total de directivas de caché:**
  - Se retirarán todas las llamadas a `Cache::remember`, `Cache::put` o equivalentes en el módulo de Dashboard. Las respuestas se computarán siempre contra la base de datos de manera determinista y en tiempo real.
- **Agregación analítica en base de datos:**
  - El cálculo de kilometraje (`getKilometersTraveledSummary`) pasará de hidratar colecciones Eloquent en PHP a ejecutar una consulta SQL agregada agrupada por vehículo que extraiga el odómetro máximo y mínimo del rango en una sola operación.
  - La diferencia de odómetros se acumulará directamente en la base de datos o sobre una estructura escalar mínima devuelta por el motor relacional.
- **Acotación estricta de la última inspección por vehículo:**
  - La determinación del último odómetro e inspección en el resumen de conductor se resolverá filtrando exclusivamente la fila más reciente por cada `vehicle_uuid` mediante un índice compuesto sobre `(vehicle_uuid, MAX(id))`, eliminando la llamada a `unique('vehicle_uuid')` sobre colecciones completas en PHP.
- **Desacoplamiento entre Alertas del Dashboard y el Recolector de Sincronización:**
  - La consulta de alertas del dashboard leerá directamente con `persistirCambios = false`, garantizando que ninguna lectura ejecute sentencias `UPDATE` (`$model->save()`) en medio de una petición `GET`.
- **Consolidación de consultas de conteo:**
  - Los conteos de entidades (vehículos activos, FUEC generados, asignaciones) combinarán el total general y el total del período usando expresiones `COUNT(CASE WHEN ...)` o agrupaciones directas por empresa.
- **Conservación estricta del contrato de salida:**
  - La estructura del JSON retornado por los endpoints de la API (`getSummary`, `getConductorSummary`, `getAlerts`) permanecerá 100% idéntica en nombres de propiedades, anidación y tipos de datos, garantizando compatibilidad total y transparente con las vistas del frontend.

## Decisiones de Pruebas

- **Costura única y más alta posible (*highest seam*):**
  - Las pruebas se ejecutarán a nivel de petición HTTP contra los endpoints públicos/autenticados:
    - `GET /api/v1/dashboard/summary`
    - `GET /api/v1/dashboard/conductor-summary`
    - `GET /api/v1/dashboard/alerts`
  - Se comprobará el comportamiento externo observable (código de respuesta HTTP, estructura exacta del payload JSON y precisión matemática de los indicadores).
- **Pruebas de presupuesto de consultas (sin caché):**
  - Se utilizará el auxiliar de pruebas de rendimiento del repositorio (`PresupuestoConsultas`) para medir el número exacto de consultas ejecutadas con `DB::enableQueryLog()`.
  - Las aserciones verificarán que el conteo de consultas sea determinista y no dependa de si la caché está fría o caliente (debe ser el mismo conteo mínimo en la primera y en sucesivas peticiones).
  - Los topes bloqueantes de consultas se reducirán sustancialmente frente a la línea base anterior:
    - Resumen general: ≤ 4 consultas (anteriormente 12).
    - Resumen del conductor: ≤ 24 consultas constantes (anteriormente descargaba todo el historial de inspecciones a RAM).
    - Alertas del dashboard: ≤ 12 consultas (anteriormente ejecutaba escrituras `UPDATE`).
- **Verificación de ausencia de escrituras en lecturas:**
  - Se comprobará que durante la ejecución de las peticiones `GET` del dashboard el registro de consultas SQL contenga 0 sentencias `INSERT`, `UPDATE` o `DELETE`.
- **Antecedentes de prueba en el repositorio:**
  - `DashboardConsultasTest` (`tests/Feature/DashboardConsultasTest.php`), `PresupuestoConsultas` (`tests/Support/PresupuestoConsultas.php`).

## Fuera de Alcance

- Modificación de contratos de API o rediseño de las pantallas y componentes visuales en el frontend (`DashboardView.vue`, `ConductorDashboardView.vue`).
- Creación de colas de procesamiento en segundo plano o workers (incompatibles con el entorno de hosting compartido).
- Modificaciones en los algoritmos de generación de FUEC o en la ingesta en vivo de coordenadas GPS.
- Cambios en el motor de sincronización de fondo de notificaciones periódicas (cron).

## Notas Adicionales

- El rediseño aprovecha los índices existentes en las tablas `vehicle_inspections`, `vehicles`, `notifications` y `fuec`.
- Al no depender de la expiración de caché de 60/180 segundos, se eliminan anomalías donde diferentes usuarios de una misma empresa veían estadísticas discordantes.
