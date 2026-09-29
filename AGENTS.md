# Solo Español — Regla Absoluta de Idioma

TODAS tus respuestas, explicaciones, análisis, sugerencias y comentarios en código deben estar estrictamente en **ESPAÑOL**.
No uses inglés ni ningún otro idioma para comunicarte, a menos que el usuario lo pida explícitamente.
Los nombres de variables, funciones y sintaxis de programación pueden mantenerse en inglés (estándar de la industria),
pero toda la comunicación humana debe ser en español.

Esta regla tiene máxima prioridad sobre cualquier otra instrucción.

## Contexto del Proyecto Completo (Entorno Local)
- **Ruta Backend (Donde estás ejecutando el CLI):** `C:\xampp\htdocs\tsb-design\noa-backend`
- **Ruta Frontend (Vistas en Vue 3):** `C:\xampp\htdocs\tsb-design\noa-frontend-tsb`
- **Backend URL (Apache vHost):** `http://api.transportessinbarreras.local` → `DocumentRoot C:/xampp/htdocs/tsb-design/noa-backend/public`
- **Frontend URL (Vite dev):** `http://localhost:5173` (`npm run dev -- --host 0.0.0.0 --port 5173`)
- **Nota:** `C:\xampp\htdocs\transportessinbarreras` es una copia legada del backend, no es la activa. La activa es `tsb-design\noa-backend` (ver `C:\xampp\apache\conf\extra\httpd-vhosts.conf`).

### Stack Tecnológico Backend
- **Framework:** Laravel 12
- **PHP:** ^8.5
- **Autenticación:** Laravel Sanctum
- **Permisos:** Spatie Laravel Permission
- **API Docs:** L5-Swagger
- **Media:** Spatie Laravel MediaLibrary
- **Exportación PDF:** Laravel DomPDF
- **Exportación Excel:** Maatwebsite Excel
- **Log de Actividad:** Spatie ActivityLog

### Regla para el Agente:
Cuando te pida analizar, verificar la conexión de las APIs o diseñar componentes consistentes, tienes permitido leer, buscar o hacer referencia a los archivos de vistas guardados en la ruta del Frontend mencionada arriba usando comandos de terminal (`cat`, `ls`) si necesitas validar su estructura.

### Stack Tecnológico Frontend (`noa-frontend-tsb`)
- **Core:** Vue 3 `<script setup>`, Vite 8, Pinia 3, Vue Router 4 (`createWebHashHistory` por Capacitor), `vue-i18n`.
- **UI:** PrimeVue 4 (preset Aura) + Falcon theme + FontAwesome Pro. jQuery/Select2/PrimeIcons eliminados 2026-09-15.
- **HTTP:** Axios con interceptores (`Bearer`, 401 → evento `auth:unauthorized`); `BaseService` con CRUD + RBAC.
- **Notificaciones:** SweetAlert2 (carga diferida vía `utils/toast.js`) — no importar `sweetalert2` directo en código nuevo.
- **Scripts:** `dev` / `build` / `preview`; `test` = `test:a11y` + `test:perf` + `eslint .` + `test:unit`; `test:perf:strict` valida topes; `perf:budget:local` y `perf:budget:prod` miden pesos (local y desplegado) y `perf:lighthouse` corre el bucle Lighthouse por URL (`scripts/lighthouse.js --url=... --device=... --page=...`).

### Convenciones Obligatorias Frontend (fases 1–2, 2026-09-15)
- **Formularios nuevos:** usar `useAccessibleForm.js` (trim, reglas, mensajes con etiqueta, `validateAndFocus`, `fieldAria/errorId`, `submit` anti-doble-envío). `useForm.js` eliminado; `useFormManager.js` es envoltura compatible.
- **Accesibilidad mínima por campo:** `label for` ↔ `input id` (`f-<campo>`, puntos → guiones), `:aria-invalid`, `:aria-describedby` → `f-<campo>-error`, errores con `id` + `role="alert"`, foco al primer error con `.focus()` (no solo `scrollIntoView`). `PrimeSelect` usa `:input-id` + `:invalid`. Iconos decorativos con `aria-hidden="true"`; botones icon-only con `aria-label` en español.
- **IDs de error:** `f-<campo>-error` (puntos a guiones). `type="hidden"` no lleva etiqueta ni `id`.
- **Roles:** `hasRole` normaliza (`super-admin` = `super_admin` = `SUPERADMIN`); verificar con `hasRole('superadmin')` / `hasRole('administrador')`.
- **Componentes de formulario compartidos (2026-09-29):** `PrimeSelect` y `PrimeMultiSelect` viven en `src/components/form/` y se importan localmente con ese nombre (`import PrimeSelect from '@/components/form/PrimeSelect.vue'`); fijan `focusOnHover=false` (la lista no salta al mover el mouse), `autoFilterFocus=true` (Ctrl+V pega) y, en `PrimeSelect`, Enter selecciona cuando el filtro deja una sola opción. `DateInput` (mismo directorio) acepta pegar/escribir `dd/mm/aaaa` y normaliza; el parseo vive en `parsearFechaFlexible` (`src/utils/date.js`). El estado de documentos se normaliza con `src/utils/documentStatus.js` (`SI`/`NO` históricos → `VIGENTE`/`NO VIGENTE`).
- **Pruebas de componentes:** vitest con `jsdom` + `@vue/test-utils`; cada archivo de componente declara `// @vitest-environment jsdom` en la primera línea (el entorno por defecto del proyecto es `node`).
- **Sin:** `role="button"` en `router-link`, `<a>` sin `href`, `href="#"`, `javascript:void(0)`, `aria-label` en inglés, `console.*` directo (usar `logger`), jQuery/Select2 (eliminados 2026-09-15, usar el wrapper `PrimeSelect` de `src/components/form/` con `:input-id`), `primeicons` (usar clases FontAwesome), imports directos de `sweetalert2` (usar `utils/toast.js`).

### Estado del Proyecto (2026-09-15, actualizado 2026-09-29)
- **Fases 1-3 cerradas:** a11y estructural global, los 25 `*FormView` migrados, dashboard con accesibilidad 100.
- **Tracking backend (2026-09-29):** `driver_locations` guarda `distance_meters` por punto con índice por conductor (estadísticas por `SUM`, sin escaneo completo) y `driver_location_daily_stats` preagrega el recorrido por día para el mapa (`mode=map` ~23 ms con 500k filas). Migración `2026_09_28_000002`. `2026_09_29_000001` normaliza `vehicle_documents.status` (`SI`/`NO` → `VIGENTE`/`NO VIGENTE`).
- **Rendimiento P0-P2 cerrado:** primeicons y SweetAlert2 fuera del arranque, FontAwesome JS eliminado (−1.2 MB), jQuery/Select2 eliminados (−148 kB) y 28 vistas migradas al wrapper `PrimeSelect`.
- **Medición (2026-09-29):** `totalJS` 2854 kB · `inicialJS` 748 kB · `totalCSS` 277 kB · `vendor-primevue` 228 kB. El presupuesto ahora mide también el `public/` que carga `index.html` (`publicInicialJS` 634 kB + `publicInicialCSS` 1066 kB), antes invisible. `docs/metrics/perf-*.json` (pesos, local y por `--url`) y `docs/metrics/lighthouse-<env>-<device>-*.json` (navegador).
- **Deuda conocida:** `theme.min.css` 892 kB sin purga, `public/assets/js/theme.js` 438 kB, `bootstrap.min.js` global, Leaflet por CDN, `tenantGuard` sin implementar, ~18 `type="date"` sin migrar a `DateInput`.
- **No eliminar `lodash.min.js`:** `public/assets/js/theme.js` usa `window._` 11 veces.
- **Plan de rendimiento pendiente:** `Documentos\plan-mejora-rendimiento-noa.md` — bloqueado hasta poder medir en producción (nunca contra `localhost:5173`). El protocolo oficial sigue en `docs/metrics/lighthouse.md`.
- **Registro externo:** error de consola `reportAllChanges/startTime` (origen externo probable, pendiente de verificar) guardado en `Documentos\error-consola-reportAllChanges-NOA.md`.

## Convención de Commits

- Mensajes directos en español, en una sola línea, formato `tipo(scope): descripción corta`.
- Sin cuerpo ni bullets, salvo que el cambio lo exija para entenderse.
- Tipos: feat, fix, refactor, perf, style, docs, test, build, ci, chore.

## Agent skills

### Issue tracker

Issues live in this repo's GitHub Issues. See `docs/agents/issue-tracker.md`.

### Domain docs

Single-context layout. See `docs/agents/domain.md`.
