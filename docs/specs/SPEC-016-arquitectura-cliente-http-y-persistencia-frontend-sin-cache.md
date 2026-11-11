# SPEC-016: Simplificación del Cliente HTTP y Persistencia Reactiva sin Caché en Frontend

**Repositorio afectado:** `noa-frontend-tsb` (solo frontend; el backend no cambia).
**Relación con otras specs:** complementa SPEC-010/012/014 (backend sin caché). SPEC-013 (`AlcanceDelActor`) hace que el
backend resuelva el alcance a partir del token + `X-Company-UUID`; por eso la cabecera de empresa debe ser siempre correcta.
SPEC-011, 012, 014 y 015 no requieren cambios en el frontend y no entran en conflicto.

## Problem Statement

Diagnóstico verificado contra el código actual (corrige imprecisiones de la primera versión de esta spec):

1. **Instancias Axios por tenant.** `src/services/api/client.js` mantiene `instances.tenants = new Map()` y `forTenant(id)`.
   - `BaseService._getInstance()` **sí** usa `forTenant` cuando el servicio recibe `tenantId`; los servicios sin `tenantId` usan `global`.
   - Usos directos de `forTenant`: `notifications.store.js` (8), `MaintenanceForecastView.vue` (2), `VehicleInspectionListView.vue` (3), mock en `notifications.store.test.js`.
   - **Inconsistencia detectada:** `notifications.store.js` toma el tenant de `userStore.company_uuid`, mientras el resto usa `authStore.currentTenant.id`. Dos fuentes de verdad para la misma cabecera.
   - `signature.service.js` **no** cachea instancias Axios: su `Map` cachea instancias de `SignatureService` por tenant. No es el mismo problema; ver Fase 1, paso 6.
2. **Persistencia sin debounce.** `src/store/plugins/persistence.js` hace `$subscribe` → `secureStorage.setItem` (AES-GCM) en **cada** mutación de `auth`, `permissions`, `ui` y `user_profile`; solo excluye `isHydrated`.
3. **Alertas de vencimiento leídas del almacenamiento nativo.** `notifications.store.js` (`checkAndTriggerExpiryAlerts`, `triggerExpiryToast`) llama a `Preferences.keys()`, un `Preferences.get` por cada `expiry_alert_shown_*` y otro `get`/`set` por cada toast.

## Solution

1. Una única instancia Axios; el interceptor de solicitudes resuelve `X-Company-UUID` en cada petición.
2. Persistencia con debounce por store, lista de exclusión explícita y vaciado inmediato en eventos críticos.
3. Control de alertas mostradas en memoria (`Map<uuid, timestamp>`), con una única clave de persistencia ligera opcional.

## Decisiones previas (resolver ANTES de programar)

| # | Decisión | Recomendación | Por qué importa |
|---|----------|---------------|-----------------|
| D1 | ¿Fuente única del tenant? | `authStore.currentTenant.id`; `userStore.company_uuid` solo como respaldo si el anterior es nulo | Notificaciones hoy usa otra fuente que el resto |
| D2 | ¿Qué pasa con una cabecera explícita en la llamada? | Prevalece sobre la del interceptor (casos `fuec.service.js` con `ctxCompanyUuid` y superadmin con varias empresas) | Evita enviar la empresa equivocada |
| D3 | ¿Control de alertas solo en memoria o persistido? | `Map<uuid, ts>` en memoria + **una** clave JSON en Preferences (lectura única al arrancar, escritura con debounce) | Con solo memoria, cada recarga/apertura repite los toasts del día (hoy hay una regla de 55 min = 3 300 000 ms) |
| D4 | ¿Qué claves se excluyen de la persistencia? | Lista por store, revisando el estado real (no basta con `isLoading`/`isOpen`/`activeTab`, que son ejemplos) | Evita persistir estado transitorio sin perder datos de sesión |
| D5 | ¿Qué eventos fuerzan vaciado inmediato? | login, logout, `auth:unauthorized`, `pagehide`, `visibilitychange: hidden` | El debounce no debe perder token/sesión al cerrar la pestaña |
| D6 | ¿`forTenant` se elimina o queda como alias? | Eliminarlo y migrar todos los usos en el mismo cambio | Un alias dejaría dos caminos y confundiría a futuros desarrolladores |

## Implementación paso a paso

### Fase 0 — Línea base (antes de tocar código)
1. Ejecutar `npm run lint`, `npm run test:unit` y `npm run build`; anotar resultados (lint y unit deben estar en verde).
2. Registrar `inicialJS` y `vendor-primevue` con `npm run test:perf` para comparar al final (límites: 750 kB y 250 kB).
3. Crear las pruebas que hoy no existen y que protegen el cambio: `src/services/api/__tests__/client.test.js` y `src/store/plugins/__tests__/persistence.test.js`. Escribirlas primero contra el comportamiento actual donde aplique.

### Fase 1 — Cliente HTTP único (correctitud multi-tenant, prioridad alta)
1. `client.js`: eliminar `instances.tenants` y `forTenant`; dejar una sola instancia `global` creada de forma perezosa.
2. `interceptors.js` (`requestInterceptor`): si la petición no trae `X-Company-UUID`, tomar `storeRegistry.authStore.currentTenant.id` (D1, D2). `storeRegistry` ya se registra en `utils/plugins.js`, sin dependencia circular nueva.
3. `base.service.js` (`_getInstance`): cuando el servicio tiene `tenantId`, enviarlo como cabecera **por petición** (envoltorio de la instancia global) en lugar de crear instancia aparte. Cubre `_request`, `_multipart` y las descargas blob (`.get`).
4. Migrar usos directos: `notifications.store.js`, `MaintenanceForecastView.vue`, `VehicleInspectionListView.vue` → `apiClient.global`; eliminar las variables `tenantId` que queden sin uso (lint) y el import de `useAuthStore` si queda huérfano. En notificaciones, aplicar D1.
5. Actualizar el mock de `notifications.store.test.js` (sin `forTenant`).
6. `signature.service.js`: mantener el `Map` de instancias de `SignatureService` o convertirlo en una única instancia que reciba el tenant por llamada. No es una caché de Axios; decidirlo aparte y no bloquear esta fase.
7. Pruebas (`client.test.js`): (a) la cabecera cambia al cambiar `currentTenant.id` sin recrear la instancia; (b) una cabecera explícita prevalece; (c) sin tenant no se envía cabecera; (d) peticiones concurrentes tras un cambio de empresa llevan la nueva.

### Fase 2 — Alertas en memoria (mayor beneficio en móvil, bajo riesgo)
1. `notifications.store.js`: reemplazar `Preferences.keys()` + `get` encadenados por un `Map<uuid, timestamp>` a nivel de módulo (fuera del estado, como `expiryToastTimers`).
2. `triggerExpiryToast`: consultar el `Map` con la misma regla de 55 min; sin `await` contra almacenamiento nativo.
3. `checkAndTriggerExpiryAlerts`: purgar del `Map` las entradas con más de 24 h (misma regla actual de 86 400 000 ms), en memoria.
4. Persistencia ligera (D3): una sola clave (p. ej. `expiry_alerts_shown`) con JSON `{uuid: ts}`; leerla una vez al iniciar y escribirla con debounce tras cada toast.
5. Migración única: borrar las claves antiguas `expiry_alert_shown_*` la primera vez (en segundo plano, sin bloquear el arranque) y marcar la migración hecha.
6. Reemplazar `console.error` por `logger` en este bloque (regla del proyecto).
7. Pruebas en `notifications.store.test.js`: (a) tras N toasts no se llama a `Preferences.keys` ni a `Preferences.get` repetidamente; (b) no repite un toast antes de 55 min; (c) sí lo repite después; (d) purga de entradas de más de 24 h.

### Fase 3 — Persistencia con debounce (riesgo más alto, ir al final de código)
1. `persistence.js`: en `configurePersistence`, reemplazar el `$subscribe` directo por escritura diferida (≈300 ms) **por store**, con una función `flush()` que escribe de inmediato.
2. Exclusión por store (D4): construir la lista revisando el `state()` de `auth`, `permissions`, `ui` y `user`; siempre excluir `isHydrated`.
3. Vaciado inmediato (D5): exponer `flushAllPersistence()` y llamarlo en login, logout, `auth:unauthorized` y en `pagehide`/`visibilitychange`.
4. Comparar con la última cadena serializada y omitir la escritura si no cambió.
5. Pruebas (`persistence.test.js`, jsdom, temporizadores simulados): (a) varias mutaciones rápidas → una sola escritura; (b) `flush()` escribe de inmediato; (c) las claves excluidas no se cifran; (d) tras logout no queda token persistido.

### Fase 4 — Cierre
1. `npm run lint`, `npm run test:unit`, `npm run build` y `npm run test:perf`; comparar con la línea base de la Fase 0 (el peso no debe subir de forma apreciable).
2. Prueba manual: cambiar de empresa y revisar en DevTools que la cabecera `X-Company-UUID` cambia en la siguiente petición; abrir y recargar la app móvil/web y comprobar que los toasts no se repiten antes de 55 min; login/logout y recarga con sesión.
3. Actualizar `AGENTS.md` del frontend: sección Multi-tenant (ya no existe `forTenant`), Auth/persistencia (debounce + flush) y notificaciones (alertas en memoria).

## Orden recomendado y dependencias

```
Decisiones D1–D6 → Fase 0 → Fase 1 → Fase 2 → Fase 3 → Fase 4
```

- Fase 1 va primero porque es correctitud (qué empresa ve el usuario) y afecta a todas las llamadas.
- Fase 2 es independiente de la 1 salvo el archivo `notifications.store.js`; se hace después para evitar conflictos.
- Fase 3 va al final por ser la de mayor riesgo (pérdida de sesión si el vaciado falla).

## Riesgos y mitigaciones

| Riesgo | Mitigación |
|--------|------------|
| Enviar la empresa equivocada tras eliminar `forTenant` | D1/D2 + pruebas (a)–(d) de la Fase 1 |
| Pérdida de la última escritura al cerrar la pestaña | `flush` en `pagehide`/`visibilitychange` y en login/logout |
| Toasts repetidos en cada recarga | Persistencia ligera de una clave (D3) |
| Claves antiguas `expiry_alert_shown_*` huérfanas | Migración única en segundo plano |
| Excluir una clave que sí era necesaria | Revisar el estado real de cada store (D4) y probar hidratación |

## Testing Decisions

- Vitest (jsdom), una prueba de comportamiento por contrato público: interceptor, plugin de persistencia, store de notificaciones.
- Archivos: `src/services/api/__tests__/client.test.js` (nuevo), `src/store/plugins/__tests__/persistence.test.js` (nuevo), `src/features/notifications/__tests__/notifications.store.test.js` (existente, actualizar).

## Out of Scope

- Rediseño de las vistas de autenticación o del panel de notificaciones.
- Cambiar el algoritmo AES-GCM o reemplazar Capacitor Preferences.
- Alterar los contratos de API del backend Laravel.
- Reestructurar `signature.service.js` más allá del paso 6 de la Fase 1.

## Criterios de aceptación

- No existe `forTenant` ni `instances.tenants` en el código.
- Toda petición con tenant activo lleva `X-Company-UUID`; un cambio de empresa se refleja en la petición siguiente.
- Varias mutaciones seguidas de un store producen una única escritura cifrada.
- El arranque no ejecuta `Preferences.keys()` ni lecturas repetidas por alerta.
- `lint`, `test:unit` y `build` en verde; pesos dentro de los límites de `AGENTS.md`.
