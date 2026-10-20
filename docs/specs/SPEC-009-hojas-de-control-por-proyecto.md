# SPEC-009 — Hojas de control creadas por proyecto (una por coche)

**Estado:** PROPUESTO (2026-10-20). Pendiente de implementar.
**Fecha:** 2026-10-20
**Predecesor:** primer paso ya integrado: el formulario de PCP trae vehículos y conductores de las asignaciones activas del proyecto (commit frontend `719b0ff`, helper `projectAssignments.js`).

## Decisiones acordadas (2026-10-20)

1. **Al guardar en «Control Directo con la Empresa» se crean N hojas, una por cada coche activo del proyecto.** No una hoja con varios coches: el modelo 1 hoja = 1 vehículo se conserva intacto (así no cambian PDF, firmas, cierres, rastreo ni reportes).
2. **Si se retira un coche del proyecto, sus hojas ya creadas se conservan** como histórico; solo deja de ofrecerse para hojas nuevas. El módulo refleja siempre la asignación vigente del proyecto.
3. **Se elimina «Mostrar todos»** en Control Directo: la creación por proyecto solo admite coches con asignación activa. Los tipos externos (subcontratado, contratado, plataforma) conservan su flujo actual de una sola hoja manual.
4. **La tabla de coches es sobria:** filas simples con checkbox, placa, número interno, conductor y documento. Sin avatares, iconos, badges ni colores (propuesta explícita del usuario: «sin los iconos ni colores, simplemente los selects»).
5. **La fuente de verdad es el proyecto:** el backend resuelve las asignaciones activas y no confía en los pares conductor↔vehículo que envíe el frontend. El frontend solo indica qué coches incluir.
6. **La edición sigue siendo individual:** una hoja se edita con su vehículo y conductor; si el vehículo ya no está en el proyecto, sigue apareciendo para no romper el histórico.
7. **El bloque directo no lleva FUEC** (ya retirado en `719b0ff`); el payload directo no envía `fuec_uuid` y el backend conserva el valor histórico.

## Problem Statement

Hoy el formulario de Hoja de Control de Entrega de Servicios (PCP) crea **una sola hoja** con un vehículo y un conductor elegidos con dos selects. Un proyecto con 14 coches exige repetir el formulario 14 veces, eligiendo cada par a mano, aunque el proyecto ya define esos pares en sus asignaciones. El también repetido error es quedar desfasado: el formulario ofrece pares que el proyecto ya retiró.

Consecuencias:

- Registrar la hoja de control de un proyecto completo es lento y propenso a errores (un par mal elegido crea la hoja del coche equivocado).
- El formulario se gobierna por dos selects sueltos en vez de por el proyecto, contradiciendo el rediseño de asignaciones del detalle del proyecto.
- «Mostrar todos» permite elegir coches fuera del proyecto, rompiendo la trazabilidad del trámite.

## Solution

En «Control Directo con la Empresa» la creación queda completamente gobernada por el proyecto:

- El formulario muestra **todas las asignaciones activas del proyecto en filas** (placa, interno, conductor, documento) con **checkbox, todas marcadas por defecto**.
- Al guardar se crea **una hoja por coche seleccionado**, y cada hoja genera sus planillas diarias por el rango de fechas común (comportamiento existente por hoja, reutilizado).
- El **responsable de servicio** de cada hoja es el conductor de su asignación (el formulario deja de pedir el nombre en modo masivo).
- El listado existente ya distingue vehículo/conductor por fila (`ServiceDeliveryControlSheetListView.vue`, columna «Vehículo / Conductor»), así que las N hojas aparecen distinguibles sin cambios de listado.
- Los coches que entran o salen del proyecto se reflejan al recargar el detalle; los históricos de coches retirados no se tocan.

## Backend

### Endpoint

- `POST /api/v1/control-sheets/service-delivery-control-sheets/bulk`, colocada **antes** de `/{uuid}` en `routes/api.php`.
- Nombre: `api.v1.fleet.service-delivery-control-sheets.bulk`.
- Middleware: `permission:service_delivery_control_sheets.create` (permiso ya existe en `PermissionSeeder`, línea 326). Con `permission:` explícito el middleware `authz` no reevalúa.
- Súper admin pasa por sus reglas habituales; el resto exige el permiso.

### Request (`BulkStoreServiceDeliveryControlSheetRequest`)

Reglas comunes replicadas de `StoreServiceDeliveryControlSheetRequest` (fechas, `is_active`, hora/km/peajes, `daily_route`), más:

- `company_uuid` requerido, `project_uuid` requerido.
- `type_of_control_sheet`: forzado a `DIRECTO_CON_LA_EMPRESA` (in: único valor; el bulk no admite externos).
- `vehicle_uuids`: `required|array|min:1`, cada valor `distinct|uuid|exists:vehicles,uuid`.
- `official_name_and_surname`, `vehicle_uuid`, `third_party_uuid`, `fuec_uuid`: **aceptados pero ignorados** (se documentan) para que nadie pueda inyectar pares o FUEC por esta vía.

### Servicio (`createBulkForProject`)

- Carga el proyecto una vez con sus asignaciones **activas** (`ProjectDriverVehicle` con tercero y vehículo, orden por `id` de ingreso).
- Valida las fechas contra la vigencia del proyecto **una sola vez** (`validateProjectDates`), no por hoja.
- Valida que cada `vehicle_uuid` solicitado tenga asignación activa en el proyecto; si alguno no, **422** con mensaje por vehículo (todo o nada, antes de crear nada).
- Deduplica por `vehicle_uuid` (la práctica actual es una activa por vehículo: verificado en local, 0 duplicados; si apareciera otra con más de una activa, se toma la de `id` menor).
- Envuelve **toda la creación en una transacción única** y, por cada asignación, reutiliza la creación actual:
  - Extraer de `createServiceDeliveryControlSheet` la parte transaccional a un método interno que **no** abra su propia transacción (o parametrizarla) para evitar N transacciones anidadas y N validaciones de fechas redundantes.
  - La hoja y sus hijos llevan `official_name_and_surname` del **conductor de la asignación** y el vehículo/tercero que pasan por `createControlForSheet` (rama interno, sin FUEC).
- Devuelve:

```json
{
  "created": 14,
  "daily_sheets": 420,
  "sheets": [ /* hojas padre creadas */ ]
}
```

- El `store` individual queda intacto (tipos externos y compatibilidad).

### Salvaguardas

- Un coche retirado del proyecto (asignación `is_active=false`) **no** entra al bulk: el detalle de proyecto lo descarta y el servicio valida pertenencia activa ⇒ 422.
- Proyecto sin asignaciones activas: 422 con mensaje claro.
- Sin contexto de empresa o con proyecto de otra empresa: el scope multi-tenant y `ValidaEmpresaDelContexto`-style falla cerrado.

## Frontend (`ServiceDeliveryControlSheetFormView.vue`)

### Sección de creación (Control Directo)

- Sustituir los dos selects por **tabla de filas del proyecto** (una por asignación activa, helper `projectAssignments.js`):
  - Columnas: checkbox «Incluir», placa, número interno, conductor, documento.
  - Checkbox de cabecera: marcar/desmarcar todas; accesible con `label` (sin `role="button"` ni `aria-label` en inglés).
  - Sin iconos ni colores; filas sobrias, `table-responsive` para móvil.
- Aviso si el proyecto no tiene asignaciones (bloquea guardar).
- **Resumen vivo:** «Se crearán N hojas (una por coche) y M planillas diarias» con `M = N × diasServicio` (un solo día: sin planillas hijas, la hoja es la planilla diaria).
- **Quitar** el switch «Mostrar todos», los watchers `completarParDelProyecto`/par del proyecto y el autocompletado de responsable en modo masivo; ocultar el campo «Responsable de servicio» cuando tipo directo + creación.
- Validación: al menos un coche y las fechas vigentes (las comprobaciones de contencia del proyecto se conservan).
- `handleSubmit` en creación directa llama a `store.createBulk(payload)` (nueva acción) en vez de `createItem`; el payload envía `vehicle_uuids` (los marcados) y **no** envía `vehicle_uuid`, `third_party_uuid`, `official_name_and_surname` ni `fuec_uuid`.
- Toast final: «14 hojas creadas (420 planillas diarias)».

### Edición (sin cambios de modelo)

- La edición mantiene su select de vehículo y de conductor, con las opciones del proyecto **más el vehículo/conductor actual de la hoja aunque ya no estén activos** (histórico editable).
- Sin «Mostrar todos» tampoco en edición.

### Volumen y confirmación

- Hoy al elegir proyecto el formulario autorrellena `start_date`/`end_date` con **toda la vigencia** del proyecto. Con bulk, proyecto de 14 coches y 30 días ⇒ 14 hojas y 420 planillas hijas de una vez.
- Antes de crear, fase de confirmación (SweetAlert2 vía `utils/toast.js`/`Swal`) mostrando el conteo exacto. Sin límite duro de días; se medirá el costo real (ver pruebas) y si el hosting no aguanta se define tope.

## Pruebas

### Backend (`CreacionMasivaHojasTest`, `tests/Feature`)

1. Proyecto con 3 asignaciones activas × 2 días ⇒ 3 hojas padre + 6 hijos; cada hijo con su propio internal control (vehículo/conductor de su asignación) y `official_name_and_surname` del conductor.
2. Coche de otro proyecto en `vehicle_uuids` ⇒ 422 y **0 hojas** creadas.
3. Coche con asignación inactiva en el proyecto ⇒ 422 (no entra al bulk).
4. Proyecto sin asignaciones ⇒ 422.
5. Un solo día ⇒ 1 hoja por coche **sin hijos**.
6. Tipo externo en el bulk ⇒ 422.
7. Permiso: usuario sin `service_delivery_control_sheets.create` ⇒ 403.
8. Rollback a mitad: fallo forzado en la segunda hoja ⇒ ninguna hoja persistente (transacción única).

### Frontend

- `projectAssignments.test.js` ampliado: filas del proyecto (dedupe por vehículo, inactivos excluidos), resumen `N hojas × D planillas`, payload `vehicle_uuids` sin pares ni FUEC.
- Nota del lección SPEC-007: las pruebas que simulen respuestas API deben usar la forma real del body (`r.data` para listados), o pase en verde con pantalla vacía.

### Rendimiento

- Medir bulk con el proyecto más grande local (14 coches) × 30 días (420 planillas hijas + 420 controles + rutas copiadas) en `noa_test` **con `migrate:status` al día** antes de medir.
- Si el tiempo rompe el presupuesto del worker (Apache/mod_php `max_execution_time`), optimizar a inserción en bloque de hijos/controles (patrón `upsert` en bloque de `syncNotifications`) y ajustar la prueba.
- Toda mejora de rendimiento incluye su presupuesto de consultas o medición de tiempo (regla del proyecto).

## Riesgos y abiertos

- **Duplicados:** la tabla `service_delivery_control_sheet` no tiene unicidad; guardar dos veces el mismo rango crea hojas repetidas. Existente ya hoy; mitiga el anti-doble-envío de `useAccessibleForm`. Si molesta, segunda iteración: aviso si ya hay hojas activas para el proyecto/vehículo solapando el rango.
- **Asignación sin conductor o sin vehículo:** se excluye de la tabla (fila inservible); el proyecto debería tener el par completo.
- **Cliente móvil/coordinador:** usa los mismos endpoints (`store` intacto); el bulk es opt-in del PCP web.

## Despliegue

1. `php artisan migrate:status` (no hay migraciones nuevas en este spec).
2. Rutas y permisos: el permiso `service_delivery_control_sheets.create` ya existe; no correr seeders en producción.
3. Subir backend y frontend juntos; el frontend nuevo llama por defecto a `/bulk` solo en creación directa.
