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
- **Scripts:** `dev` / `build` / `preview` / `test` (= `test:a11y` + `test:perf`, solo informan, exit 0).

### Convenciones Obligatorias Frontend (fases 1–2, 2026-09-15)
- **Formularios nuevos:** usar `useAccessibleForm.js` (trim, reglas, mensajes con etiqueta, `validateAndFocus`, `fieldAria/errorId`, `submit` anti-doble-envío). `useForm.js` eliminado; `useFormManager.js` es envoltura compatible.
- **Accesibilidad mínima por campo:** `label for` ↔ `input id` (`f-<campo>`, puntos → guiones), `:aria-invalid`, `:aria-describedby` → `f-<campo>-error`, errores con `id` + `role="alert"`, foco al primer error con `.focus()` (no solo `scrollIntoView`). `PrimeSelect` usa `:input-id` + `:invalid`. Iconos decorativos con `aria-hidden="true"`; botones icon-only con `aria-label` en español.
- **IDs de error:** `f-<campo>-error` (puntos a guiones). `type="hidden"` no lleva etiqueta ni `id`.
- **Roles:** `hasRole` normaliza (`super-admin` = `super_admin` = `SUPERADMIN`); verificar con `hasRole('superadmin')` / `hasRole('administrador')`.
- **Sin:** `role="button"` en `router-link`, `<a>` sin `href`, `href="#"`, `javascript:void(0)`, `aria-label` en inglés, `console.*` directo (usar `logger`), jQuery/Select2 (eliminados 2026-09-15, usar `PrimeSelect` global con `:input-id`), `primeicons` (usar clases FontAwesome), imports directos de `sweetalert2` (usar `utils/toast.js`).

### Estado del Proyecto (2026-09-15)
- **Fases 1-3 cerradas:** a11y estructural global, los 25 `*FormView` migrados, dashboard con accesibilidad 100.
- **Rendimiento P0-P2 cerrado:** primeicons y SweetAlert2 fuera del arranque, FontAwesome JS eliminado (−1.2 MB), jQuery/Select2 eliminados (−148 kB) y 22 vistas migradas a `PrimeSelect`.
- **Medición:** `docs/metrics/a11y-*.json` + `perf-*.json` (baseline 2026-09-15) y `docs/metrics/lighthouse.md` (manual). `totalJS` 2565 kB · `totalCSS` 241 kB.
- **Deuda conocida:** `vendor-primevue` ~806 kB, `theme.min.css` 892 kB sin purga, `bootstrap.min.js` global, Leaflet por CDN, `tenantGuard` sin implementar.
- **No eliminar `lodash.min.js`:** `public/assets/js/theme.js` usa `window._` 11 veces.
- **Plan de rendimiento pendiente:** `Documentos\plan-mejora-rendimiento-noa.md` — bloqueado hasta poder medir en producción (nunca contra `localhost:5173`).
- **Registro externo:** error de consola `reportAllChanges/startTime` (origen externo probable, pendiente de verificar) guardado en `Documentos\error-consola-reportAllChanges-NOA.md`.
