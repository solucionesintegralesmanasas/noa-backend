# Plan de arquitectura, rendimiento y escalabilidad de NOA

**Estado:** Propuesto  
**Fecha:** 2026-09-22  
**Alcance:** Backend Laravel y frontend Vue/Capacitor  
**Prioridad principal:** Separar la recepción GPS del procesamiento pesado

## 1. Objetivos

- Reducir la latencia de las peticiones críticas.
- Evitar que el crecimiento de conductores y puntos GPS aumente el coste de forma multiplicativa.
- Liberar los trabajadores HTTP de tareas pesadas.
- Mantener respuestas acotadas y predecibles.
- Mejorar la localidad del código y la capacidad de prueba.
- Reducir la carga inicial del frontend.
- Unificar los mecanismos de tiempo real.
- Crear seams y adapters que permitan sustituir infraestructura sin reescribir la lógica de negocio.

## 2. Diagnóstico ejecutivo

Los riesgos principales se concentran en:

1. Tracking GPS síncrono.
2. Historiales GPS sin paginación ni retención definida.
3. Generación síncrona de PDF y Excel.
4. Consultas N+1 en el monitor de flota.
5. Polling duplicado o solapado en el frontend.
6. Dependencias globales y un bloque inicial grande de PrimeVue.
7. Bootstrap de Vue acoplado a la hidratación completa de stores.
8. Ausencia de pruebas específicas y de typecheck/lint automatizados.

La arquitectura actual contiene módulos shallow en los que la interface está muy cerca de la implementation. El primer deepening recomendado es el intake de tracking GPS.

## 3. Priorización

| Prioridad | Área | Impacto | Riesgo | Resultado esperado |
|---|---|---:|---:|---|
| P0 | Ingesta y procesamiento GPS | Muy alto | Crítico | Escrituras rápidas y procesamiento desacoplado |
| P0 | Historial y estadísticas GPS | Muy alto | Alto | Memoria y respuestas acotadas |
| P0 | PDF y Excel | Muy alto | Alto | Exportaciones asíncronas y reintentables |
| P1 | Monitor de flota | Alto | Alto | Menos consultas y menor latencia |
| P1 | Tiempo real frontend | Alto | Alto | Un único canal con fallback controlado |
| P1 | Dependencias y carga inicial | Medio-alto | Medio | Menor JavaScript/CSS inicial |
| P1 | Índices y filtros de fecha | Alto | Medio-alto | Consultas indexables |
| P1 | Pruebas y controles de calidad | Alto | Alto | Refactors seguros |
| P2 | Dashboard | Medio-alto | Medio | Métricas cacheadas por bloque |
| P2 | Cola local GPS móvil | Alto | Medio | Resiliencia sin red y menor consumo |

## 4. Hallazgos detallados

### 4.1 Tracking GPS síncrono

**Archivos principales:**

- `app/Services/Tracking/LocationTrackingService.php:54-83`
- `app/Services/Tracking/LocationTrackingService.php:364-388`
- `app/Services/Tracking/LocationTrackingService.php:394-453`
- `app/Services/Tracking/LocationTrackingService.php:482-494`

Cada ubicación recibida persiste el punto, actualiza la sesión, carga todas las geocercas activas, calcula geometría, busca el estado anterior por geocerca y genera alertas dentro de una misma transacción.

**Problemas:**

- El tiempo de respuesta depende del número de geocercas.
- Una geocerca puede producir consultas repetidas al historial.
- El procesamiento de alertas bloquea la recepción de nuevos puntos.
- Los errores de procesamiento pueden afectar la transacción de ingestión.

**Plan:**

- Mantener la recepción HTTP limitada a validación y persistencia del punto.
- Publicar un Job `ProcessDriverLocation` después del commit.
- Actualizar distancia y contadores de sesión en el Job.
- Cargar las geocercas una sola vez por empresa.
- Guardar el estado actual por combinación conductor/geocerca.
- Añadir idempotencia mediante identificador del dispositivo o hash del punto.
- Separar alertas, métricas y notificaciones en Jobs independientes si el volumen lo requiere.

**Evidencia medida (benchmark local 2026-09-22, base vacía = suelo optimista):**

`consultas_por_punto = 5 + 5 × N`, donde N = geocercas activas de la empresa.

| Geocercas (N) | Consultas/punto | App/punto | Alertas/20 pts |
|---|---|---|---|
| 0 | 5 | 4 ms | 0 |
| 1 | 10 | 5,6 ms | 20 |
| 5 | 30 | 19,5 ms | 100 |
| 10 | 55 | 34 ms | 200 |
| 25 | 130 | 74 ms | 500 |
| 50 | 255 | 148 ms | 1000 |

Descomposición verificada con query log: 5 base (insert + refresh + activity_log + lookup de sesión + carga de geocercas) y +5 por geocerca (estado anterior ×2 + insert de alerta + refresh + activity_log).

Hallazgos asociados:

- El trait `LogsActivity` duplica la escritura en tablas de alta frecuencia: cada ubicación y cada alerta generan su `insert into activity_log`. Decidir si se excluyen estas tablas o se mueve a cola.
- Una ruta que cruza varias geocercas genera N alertas por punto (con sus inserts y activity asociados): se necesita agregación o debounce.
- FK reales no declaradas en la migración original: `driver_locations` referencia a `companies` (CASCADE), `third_parties` (CASCADE) y `vehicles` (SET NULL); `driver_location_alerts.driver_location_uuid` referencia a `driver_locations.uuid` con `ON DELETE SET NULL` (alertas huérfanas posibles); `geofences.company_uuid` referencia a `companies.uuid` (CASCADE).

### 4.2 Historial y estadísticas GPS

**Archivos principales:**

- `app/Services/Tracking/LocationHistoryService.php:45-64`
- `app/Services/Tracking/LocationHistoryService.php:77-117`
- `app/Services/Tracking/LocationHistoryService.php:181-205`

Los historiales usan `get()` completo. Las estadísticas cargan por separado los rangos diario, semanal y mensual, y calculan Haversine en memoria.

**Plan:**

- Exigir paginación para historiales.
- Definir un rango máximo por consulta.
- Aplicar simplificación de puntos para mapas.
- Procesar grandes rangos con `chunkById()` o cursor.
- Calcular conteos, máximos y duración mediante agregaciones SQL cuando sea posible.
- Persistir distancia acumulada por sesión.
- Evaluar agregados horarios o diarios para reportes históricos.
- Definir una política de retención y archivado GPS.

### 4.3 PDF, Excel y mapas

**Archivos principales:**

- `app/Services/Pdf/PdfService.php`
- `app/Services/Pdf/RouteMapService.php:132-190`
- `app/Exports/ServiceControlSheetExport.php:30-98`
- `app/Http/Controllers/Api/V1/ServiceDeliveryControlSheet/ServiceDeliveryControlSheetController.php`

Las exportaciones y mapas se generan durante la petición HTTP. Excel materializa hasta 1.000 planillas con relaciones y PDF puede bloquearse esperando un proveedor externo de mapas.

**Plan:**

- Crear un módulo de reportes asíncronos.
- Definir Jobs para PDF, Excel y generación de mapas.
- Guardar resultados en almacenamiento temporal.
- Exponer estado: `pending`, `processing`, `completed`, `failed`.
- Añadir reintentos y registro de errores.
- Descargar mediante URL firmada con expiración.
- Migrar Excel a consulta por bloques y lectura progresiva.
- Reemplazar `whereDate`, `whereYear` y `whereMonth` por rangos de fecha.
- Cachear mapas por hash de coordenadas, tamaño y proveedor.
- Mantener el mapa offline como fallback controlado.

### 4.4 Monitor de flota y consultas N+1

**Archivo principal:**

- `app/Services/Tracking/LocationTrackingService.php:194-326`

Cuando una ubicación no tiene planilla compatible, se ejecuta una búsqueda histórica por ubicación. Esto aumenta las consultas proporcionalmente al número de conductores.

**Plan:**

- Cargar las planillas candidatas de todos los vehículos en una consulta.
- Indexarlas en memoria por vehículo, placa y proyecto.
- Evitar relaciones no necesarias en la respuesta.
- Añadir una representación específica para el monitor.
- Evaluar un read model de flota activa actualizado al recibir puntos GPS.

### 4.5 Índices y filtros de fecha

**Archivos principales:**

- `database/migrations/2026_09_11_201000_create_driver_locations_table.php`
- `database/migrations/2026_09_11_202000_create_driver_location_sessions_table.php`
- `app/Exports/ServiceControlSheetExport.php`
- `app/Services/Pdf/PdfService.php`

Los índices actuales de `driver_locations` cubren empresa/fecha, conductor/fecha, vehículo y fecha individual. Las consultas reales combinan más columnas.

**Plan:**

- Ejecutar `EXPLAIN` con datos representativos.
- Validar `company_uuid, vehicle_uuid, project_uuid, recorded_at`.
- Validar `third_party_uuid, recorded_at, id`.
- Validar `company_uuid, service_date, is_active`.
- Revisar índices de sesiones por empresa, conductor y estado.
- No añadir índices sin comprobar selectividad y coste de escritura.

### 4.6 Tiempo real en frontend

**Archivos principales:**

- `src/features/tracking/views/TrackingMapView.vue:47-88`
- `src/hooks/useNotifications.js:8-10`
- `src/features/notifications/store/notifications.store.js:197-323`

El mapa usa `setInterval` cada diez segundos sin controlar peticiones solapadas. Notificaciones mantienen polling y SSE simultáneamente.

**Plan:**

- Crear un composable `useRealtimeChannel`.
- Preferir SSE como canal principal para notificaciones.
- Mantener polling como fallback después de una desconexión.
- Reemplazar `setInterval` por un ciclo `setTimeout` posterior a la respuesta.
- Cancelar solicitudes mediante `AbortController`.
- Pausar actualizaciones cuando `document.visibilityState` sea `hidden`.
- Aplicar backoff exponencial y límite de reconexiones.
- Garantizar una única conexión por feature.

### 4.7 Geolocalización móvil y navegador

**Archivo principal:**

- `src/hooks/useGeolocation.js:127-252`

La geolocalización usa alta precisión, `maximumAge: 0`, conserva todos los puntos en `routeHistory` y descarta puntos cuando el envío falla.

**Plan:**

- Filtrar por tiempo y distancia mínima.
- Limitar el tamaño de `routeHistory`.
- Añadir una cola persistente local.
- Reintentar con backoff.
- Marcar puntos enviados y pendientes.
- Pausar o degradar precisión según batería y visibilidad.
- Evitar duplicados por coordenadas y timestamp.

### 4.8 Carga inicial y dependencias globales

**Archivos principales:**

- `index.html:12-23`
- `index.html:136-142`
- `src/components/app/primevue.js:6-52`
- `src/utils/plugins.js:24-70`

La métrica del 2026-09-15 registra `2565,5 kB` de JavaScript, `241,4 kB` de CSS y un bloque `vendor-primevue` de `806,3 kB`.

También se cargan globalmente SimpleBar, Bootstrap, Popper, AnchorJS, Is.js, Lodash y `theme.js`.

**Plan:**

- Medir uso de cada dependencia por ruta.
- Reducir componentes PrimeVue globales.
- Cargar módulos heredados solo donde sean necesarios.
- Revisar CSS completo de Falcon y FontAwesome.
- Mantener Lodash mientras `theme.js` lo requiera.
- Separar CSS crítico del CSS de administración.
- Definir presupuestos por ruta, no solo globales.

### 4.9 Bootstrap de Vue

**Archivo principal:**

- `src/utils/plugins.js:24-70`

El montaje espera la hidratación de autenticación, permisos y usuario, y después ejecuta `fetchProfile()`.

**Plan:**

- Montar la carcasa mínima después de resolver autenticación.
- Cargar información no crítica después del primer pintado.
- Evitar `fetchProfile()` si el perfil persistido sigue siendo válido.
- Mostrar estados de carga específicos por módulo.
- Medir el tiempo real de hidratación y montaje.

### 4.10 Dashboard

**Archivo principal:**

- `app/Services/Dashboard/DashboardService.php:97-419`

El resumen del conductor agrupa numerosas consultas de vehículos, inspecciones, mantenimientos, planillas, FUEC y tracking. La caché dura 60 segundos.

**Plan:**

- Separar el dashboard en bloques de lectura.
- Cachear identidad, vehículos, métricas y actividad por separado.
- Invalidar bloques mediante eventos de dominio.
- Precalcular kilometraje y conteos frecuentes.
- Medir consultas y tiempo de generación por bloque.

### 4.11 Calidad y pruebas

Actualmente solo se encontraron pruebas de ejemplo en backend y no se encontraron pruebas unitarias o de integración específicas en frontend. El frontend no tiene lint ni typecheck.

**Plan:**

- Añadir pruebas de contrato para ingestión GPS.
- Probar transiciones de entrada y salida de geocercas.
- Probar idempotencia y aislamiento multiempresa.
- Probar exportaciones grandes.
- Probar Jobs, fallos y reintentos.
- Probar stores, composables y reconexión SSE.
- Incorporar ESLint.
- Incorporar TypeScript o `checkJs` progresivo.
- Convertir los presupuestos de rendimiento en validaciones de CI.

## 5. Arquitectura objetivo

### 5.1 Flujo GPS

```text
Dispositivo
    -> Endpoint de ingestión
    -> Validación e idempotencia
    -> Persistencia rápida
    -> Evento después del commit
    -> Job de procesamiento GPS
        -> Sesión y distancia
        -> Estado de geocercas
        -> Alertas
        -> Métricas
        -> Actualización de read model
```

La interface de ingestión debe ser pequeña: aceptar el punto, garantizar su persistencia y devolver un identificador. El procesamiento pesado queda detrás de una seam sustituible.

### 5.2 Flujo de reportes

```text
Solicitud de reporte
    -> Crear exportación pendiente
    -> Job de generación
    -> Almacenamiento temporal
    -> Estado completado o fallido
    -> URL firmada de descarga
```

La interface común de reportes debe ocultar si la implementation usa DomPDF, Excel, almacenamiento local o almacenamiento externo.

### 5.3 Tiempo real frontend

```text
Feature realtime
    -> Canal SSE
    -> Reconexión con backoff
    -> Polling de recuperación
    -> Store normalizado
    -> Vistas y componentes
```

Las vistas no deben crear directamente temporizadores, conexiones SSE ni reglas de reconexión.

## 6. Plan de ejecución por fases

### Fase 0: Medición y línea base

**Objetivo:** medir antes de cambiar.

**Tareas:**

- Registrar latencia p50, p95 y p99 de endpoints GPS, dashboard y reportes.
- Registrar consultas por petición.
- Registrar memoria máxima de PDF, Excel e historial GPS.
- Ejecutar `EXPLAIN` en las consultas críticas.
- Medir el frontend contra `dist` servido por `preview` o producción.
- Separar métricas de carga inicial, tracking y rutas administrativas.

**Salida:** tablero de métricas y presupuestos iniciales.

### Fase 1: Protección inmediata

**Objetivo:** evitar degradación mientras se realizan refactors.

**Tareas:**

- Añadir límites de rango y paginación GPS.
- Evitar peticiones solapadas del mapa.
- Desactivar polling redundante de notificaciones.
- Pausar polling con pestaña oculta.
- Convertir filtros de fecha a rangos.
- Añadir índices validados mediante `EXPLAIN`.
- Limitar `routeHistory`.
- Añadir alertas de memoria y timeout.

**Criterio de salida:** ninguna petición crítica crece sin límite por volumen de datos.

### Fase 2: Desacoplamiento backend

**Objetivo:** extraer trabajo pesado del ciclo HTTP.

**Tareas:**

- Crear Jobs GPS.
- Crear estado conductor/geocerca.
- Crear Jobs de PDF y Excel.
- Implementar almacenamiento temporal de reportes.
- Cachear mapas.
- Añadir pruebas de los módulos extraídos.

**Criterio de salida:** la recepción GPS y la creación de reportes no bloquean procesamiento pesado.

### Fase 3: Read models y agregados

**Objetivo:** reducir reconstrucción repetida de datos.

**Tareas:**

- Crear read model de flota activa.
- Crear métricas acumuladas por sesión.
- Crear agregados históricos diarios u horarios.
- Reducir consultas del dashboard.
- Definir retención y archivado GPS.

**Criterio de salida:** monitor y dashboard no necesitan recorrer grandes historiales para mostrar métricas actuales.

### Fase 4: Optimización frontend

**Objetivo:** reducir carga inicial y mejorar resiliencia.

**Tareas:**

- Unificar realtime en composables.
- Introducir cancelación de solicitudes.
- Reducir registro global de PrimeVue.
- Auditar dependencias heredadas.
- Dividir módulos grandes de tracking.
- Añadir typecheck y pruebas de stores.

**Criterio de salida:** cada feature administra su estado, efectos y transporte mediante una interface explícita.

### Fase 5: Operación y calidad continua

**Objetivo:** evitar regresiones.

**Tareas:**

- Integrar métricas de aplicación.
- Monitorizar Jobs fallidos.
- Monitorizar colas y tiempos de espera.
- Automatizar presupuestos de bundle.
- Ejecutar pruebas de carga periódicas.
- Documentar decisiones arquitectónicas en ADR.

**Criterio de salida:** las regresiones de rendimiento son detectadas en CI o en observabilidad, no por usuarios.

## 7. Backlog priorizado

| ID | Tarea | Prioridad | Dependencias |
|---|---|---|---|
| ARQ-001 | Medir endpoints GPS, dashboard y reportes | P0 | Ninguna |
| ARQ-002 | Limitar y paginar historiales GPS | P0 | ARQ-001 |
| ARQ-003 | Crear Job de procesamiento GPS | P0 | ARQ-001 |
| ARQ-004 | Persistir estado conductor/geocerca | P0 | ARQ-003 |
| ARQ-005 | Convertir PDF y Excel a Jobs | P0 | ARQ-001 |
| ARQ-006 | Cachear mapas por hash | P1 | ARQ-005 |
| ARQ-007 | Optimizar monitor GPS y eliminar N+1 | P1 | ARQ-001 |
| ARQ-008 | Validar índices con `EXPLAIN` | P1 | ARQ-001 |
| ARQ-009 | Unificar SSE y polling | P1 | Ninguna |
| ARQ-010 | Añadir cancelación y pausa de polling | P1 | ARQ-009 |
| ARQ-011 | Crear cola local de puntos GPS | P2 | ARQ-003 |
| ARQ-012 | Reducir dependencias globales | P1 | Nueva medición frontend |
| ARQ-013 | Reducir registro global de PrimeVue | P1 | ARQ-012 |
| ARQ-014 | Separar bloques del dashboard | P2 | ARQ-001 |
| ARQ-015 | Añadir pruebas de dominio | P0 | ARQ-003 |
| ARQ-016 | Incorporar lint y typecheck | P1 | Ninguna |

## 8. Métricas de aceptación

### Backend

- p95 de ingestión GPS inferior al objetivo definido en la Fase 0.
- La ingestión no espera a geocercas, mapas ni reportes.
- Cero consultas N+1 en el endpoint del monitor.
- Historiales siempre paginados o limitados.
- Exportaciones grandes no bloquean trabajadores HTTP.
- Jobs fallidos visibles y reintentables.
- Consultas críticas utilizan índices comprobados con `EXPLAIN`.

### Frontend

- Una sola conexión SSE por feature.
- Cero solicitudes de mapa solapadas.
- Polling pausado con pestaña oculta.
- Puntos GPS pendientes conservados cuando no hay red.
- Presupuesto JavaScript por ruta definido y validado.
- Reducción medible del bloque inicial PrimeVue.
- Typecheck y lint ejecutados en CI.

### Calidad

- Pruebas para ingestión, geocercas, exportaciones y multiempresa.
- Pruebas de reconexión SSE y cancelación.
- Pruebas de carga para puntos GPS y reportes.
- ADR para las decisiones de colas, retención GPS y read models.

## 9. Primera entrega recomendada

La primera entrega debe incluir `ARQ-001` a `ARQ-005`:

1. Medir la situación actual.
2. Limitar historiales.
3. Extraer el procesamiento GPS a cola.
4. Convertir PDF y Excel a generación asíncrona.
5. Añadir pruebas de contrato para proteger el cambio.

Esta secuencia tiene el mayor leverage porque ataca simultáneamente latencia, memoria, disponibilidad y capacidad de crecimiento.

## 10. Riesgos y decisiones pendientes

- Definir Redis, base de datos u otro backend para producción de colas.
- Definir almacenamiento final de reportes y tiempo de expiración.
- Confirmar volumen esperado de puntos GPS por empresa y por día.
- Confirmar política legal y operativa de retención GPS.
- Confirmar si SSE seguirá siendo válido para todos los clientes móviles.
- Confirmar proveedor de mapas y límites de uso.
- Definir si el read model será persistente o cacheado.
- Definir presupuestos de latencia con datos de producción.

## 11. Limitaciones del análisis

- No se ejecutaron pruebas de carga contra producción.
- No se ejecutó `EXPLAIN` con volúmenes productivos.
- Las métricas frontend disponibles corresponden al 2026-09-15.
- La recomendación de índices debe validarse con cardinalidad real.
- El análisis fue estático y no sustituye observabilidad en producción.
