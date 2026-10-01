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

### Arquitectura Backend
- **Capas:** Controller (`app/Http/Controllers/Api/V1/<Módulo>`) → FormRequest (`Store*`/`Update*`) → Service (hereda de `App\Services\BaseService`: CRUD por UUID, paginación, búsqueda, `toggleStatus`, transacciones, soft delete) → Model.
- **Traits** (`app/Traits`): `BelongsToCompany`, `HasUuid`, `HandlesApiResponse` (respuestas con HATEOAS), `HasFiles`, `LogsActivity`, `ExcelExportable`, `PdfGeneratable`, `FormatsDates`.
- **No existen** `Policies`, `Http/Resources`, `Observers` ni `Jobs`. La autorización va por el middleware `permission:` de Spatie en `routes/api.php`; el hosting compartido no tiene colas ni workers, por lo que PDF, Excel y GPS son síncronos (ver `PLAN-ARQUITECTURA-RENDIMIENTO-NOA.md`).
- **Rutas:** todo bajo `/api/v1` (`routes/api.php`, nombres `api.v1.<módulo>.<recurso>.<acción>`). Públicas: `login`, `refresh-token`, `health`, rutas firmadas (`signed:relative`) de inspección y planilla, validación de FUEC y `public/cron/*` (exigen `CRON_SECRET`, ver abajo; `WebCronMiddleware` NO las protege: solo lanza `webcron:run` en segundo plano). Las rutas del chat/asistente IA están comentadas.
- **Auth:** access token de 8 h (`ACCESS_TOKEN_HOURS`) y refresh de 30 días en cookie; 2FA con `laragear/two-factor`; Socialite; rate limiting y bloqueo de cuenta en `AuthenticationService`.
- **Roles (guard `api`):** `SUPERADMIN`, `ADMIN_EMPRESA`, `EMPLEADO`, `AFILIADO`, `CONDUCTOR`. Ojo: el frontend verifica `hasRole('administrador')`; la equivalencia con `ADMIN_EMPRESA` debe confirmarse antes de tocar RBAC. Permisos con formato `recurso.acción`.
- **Multi-tenant:** una BD con columna `company_uuid`. `SetCompanyContext` resuelve la empresa (`X-Company-UUID`, `X-Tenant-ID`, `company_uuid`, sesión o primera empresa del usuario) y fija `current_company_uuid`; `BelongsToCompany` aplica el global scope `company`.
- **Tests:** `tests/Feature` y `tests/Unit` (MySQL `noa_test`, `php artisan test`, 110 casos): planillas, tracking, seguridad de administración, scope multi-tenant, correo de vencimientos y normalización de nombres propios. Sin tests del login/2FA ni del CRUD de flota.
- **Docs:** `docs/adr/ADR-001-pdf-diario-archivado.md` y `docs/specs/SPEC-002-completitud-planillas.md` (ambos PROPUESTOS), y `PLAN-ARQUITECTURA-RENDIMIENTO-NOA.md` (backlog ARQ-xxx).

### Correo de vencimientos (cron) — NO modificar sin correr `DigestVencimientosTest`
Envía a `system_configuration.notification_email` un digest con los documentos y tarjetas de operación por vencer o vencidos de los vehículos **propios** de la empresa. Caso productivo: **TRANSPORTES ESPECIALES SIN BARRERA S.A.S** → `transespecialessinbarreras@gmail.com`.
- **Quién lo dispara (tres vías, todas llaman a `EmailLogService::notifyExpiringDocuments()`):** el schedule `fleet:notify-expiring-documents` a las 08:00 y 15:00 (`routes/console.php`); `WebCronMiddleware`, que en cada petición lanza el comando `webcron:run` en segundo plano (candado de 60 min; es lo que funciona en el hosting compartido sin cron real); y los endpoints `GET /api/v1/public/cron/*` (`run-all`, `trigger-notifications`, `sync-notifications`, `sync-social-security`), que exigen `?token=<CRON_SECRET>` o la cabecera `X-Cron-Token`. Sin `CRON_SECRET` en el `.env` esos endpoints responden 403; el schedule y el webcron no dependen de él.
- **A quién:** se procesa cada fila de `system_configuration` con `activate_notifications = 1`, `notify_by_email = 1` y `notification_email` no nulo. Un fallo en una empresa no impide las demás.
- **Qué vehículos:** activos de la empresa y *propios*: `OwnCompany::esVehiculoPropio` toma la tarjeta de operación activa más reciente y compara `operation_cards.affiliated_company` con los nombres propios (`system_configuration.own_company_names` + `business_name`/`trade_name` de la empresa), normalizados (mayúsculas, sin acentos ni signos, y siglas unificadas: `S.A.S`, `S. A. S.`, `S A S` y `SAS` son equivalentes; ver `OwnCompanyNormalizacionTest`). Los vehículos de afiliados/terceros no reciben correo (solo notificaciones de la plataforma).
- **Qué documentos:** `vehicle_documents` con `expiry_date` y estado distinto de `INACTIVA` (los reemplazados no reviven) y la tarjeta de operación más reciente, más cualquier otra aún vigente.
- **Cadencia:** recordatorio único a 15 días (`recordatorio_15`, uno por documento); de 5 a 1 día, un digest diario (`digest_diario`, a cualquier hora); hoy y vencidos, dos al día (`digest_manana` 06–12 h y `digest_tarde` 12–20 h; fuera de franja no se envía). La idempotencia vive en `email_notification_logs` (reintentos del webcron no duplican).
- **Correo:** `VehicleExpiryDigestMail` (vista `emails.vehicle-documents.digest`); el asunto lleva el nombre de la empresa.
- **Pruebas:** `php artisan test --filter=DigestVencimientosTest` (16 casos: destinatario, variantes del nombre propio incl. `S.A.S`/`SAS`, exclusión de afiliados/inactivos/reemplazados, franjas, idempotencia y los dos comandos). Para probar a mano sin enviar: `MAIL_MAILER=log php artisan fleet:notify-expiring-documents` y revisar `storage/logs/laravel.log`.
- **Bug corregido (2026-10-01):** `SystemConfiguration::company()` no declaraba las llaves (`company_uuid` → `uuid`) y devolvía siempre `null`, por lo que el asunto y el saludo decían "la empresa" en vez del nombre.
- **Incidencia SMTP (2026-10-01, SIN RESOLVER):** al probar el envío real desde local, el servidor `mail.solucionesintegralesmana.com` rechazó los dos digest con `550 Outgoing mail from "notificaciones@solucionesintegralesmana.com" has been suspended`: el hosting suspendió el envío saliente de esa cuenta. No es un fallo del código (el flujo armó los correos y registró el error sin romperse). Si producción usa el mismo remitente, el correo de vencimientos NO está llegando. Pasos: pedir al hosting que levante la suspensión y diga la causa (límite por hora, sospecha de spam, credenciales); buscar `Error enviando digest de vencimientos` en `storage/logs/laravel.log` de producción; mientras tanto se puede configurar otro SMTP/remitente en el `.env`. Los envíos fallidos quedan como `failed` en `email_notification_logs` y se reintentan en el siguiente ciclo. Prueba manual repetible (revierte los registros): script `send.php` del scratchpad con SMTP real, o `MAIL_MAILER=log` para solo ver el correo.
- **Cuidado al probar en local:** el `.env` local usa el SMTP real y la base local tiene `notification_email = selhius04@gmail.com`; ejecutar `fleet:notify-expiring-documents` o `webcron:run` aquí envía correos reales. Usar `MAIL_MAILER=log` o la transacción con rollback.

### Vehículos particulares (SPEC-003, 2026-10-01)
Un vehículo con `type_of_service = PARTICULAR` **no tiene pólizas RCC/RCE ni tarjeta de operación** (sí SOAT y RTM). La regla vive en `Vehicle::esParticular()`, `requiereTarjetaOperacion()` y `admiteTipoDocumento($tipo)`; ningún flujo debe comparar el texto a mano.
- **Rechazo al registrar:** `StoreVehicleDocumentRequest`/`UpdateVehicleDocumentRequest` y `StoreOperationCardRequest`/`UpdateOperationCardRequest` (trait `RechazaDocumentosDeParticulares`) devuelven 422; los servicios `VehicleDocumentService` y `OperationCardService` tienen la misma defensa. El historial anterior de un vehículo que pasa a particular se conserva y se puede editar mientras no cambie de vehículo ni de tipo.
- **Alertas:** `NotificationsService` no genera "tarjeta faltante"/vencimientos ni RCC/RCE para particulares. **Correo de vencimientos:** los particulares entran como de la empresa dueña del registro, solo por SOAT y RTM (`EmailLogService::recolectarItems`). **Reporte de vehículos:** `es_particular` en la fila; Excel/PDF/pantalla muestran "No aplica"; los filtros de tarjeta/póliza RCC-RCE excluyen particulares.
- **Frontend:** `useDocumentWizard` (`soloPublico`, `pasosAplicables`, `esVehiculoParticular`) salta pólizas y tarjeta; los selectores de esos formularios excluyen particulares; `vehicles/list` devuelve `type_of_service`. Pruebas: `VehiculoParticularDocumentosTest`, `useDocumentWizard.test.js`.

### Seguridad: estado tras las fases A y B (2026-10-01)
- **Hecho (fase A):** las escrituras de `/auth/roles|permissions|model-has-*|role-has-permissions` exigen rol `SUPERADMIN` y las de `/auth/users` `SUPERADMIN` o `ADMIN_EMPRESA` (las lecturas siguen abiertas: el frontend las usa en formularios); un `ADMIN_EMPRESA` no puede conceder el rol `SUPERADMIN` ni modificar/desactivar/eliminar a un `SUPERADMIN` (`UserService::asegurarPuedeGestionar`); `SetCompanyContext` exige pertenencia estricta a la empresa del header `X-Company-UUID`/`X-Tenant-ID` (403 si no es suya; `SUPERADMIN` opera en cualquiera); `/public/cron/*` exige `CRON_SECRET`. Pruebas: `SeguridadAdministracionTest`.
- **Hecho (fase B):** `composer update` de guzzle, league/commonmark, phpspreadsheet, dompdf, phpseclib, flysystem y laravel/framework 12.69; `composer audit` sin avisos (volver a correrlo en cada release). Humo de Excel/PDF del reporte de vehículos verificado tras actualizar. Pruebas de auth/permisos/empresa/cron: `SeguridadAdministracionTest`, `TenantScopeTest`, `DigestVencimientosTest`.
- **Multi-tenant (`BelongsToCompany`) sigue fallando abierto a propósito:** sin contexto de empresa el scope no filtra. NO se activó el `throw` porque lo necesitan rutas públicas (inspección y planilla firmadas, `GET public/fuecs/{code}`), el cron/webcron y 39 usos de `withoutGlobalScope`; bloquearlo hoy rompería esas funciones. Ahora cada consulta sin contexto deja un `warning` en el log ("Consulta sin contexto de empresa", una vez por modelo y ruta por proceso). Plan para cerrarlo: tras una semana en producción, listar esos avisos, marcar los flujos legítimos con una excepción explícita y entonces activar el bloqueo (`TenantScopeTest` ya cubre el comportamiento actual).
- **Fase C — autorización por recurso (middleware `authz`, 2026-10-01):** `AuthorizeByResource` va en el grupo `auth:sanctum` de `routes/api.php`. Deriva el permiso del nombre de la ruta (`api.v1.<módulo>.<recurso>.<acción>`) y del método HTTP: GET → `<recurso>.index|profile|view|show` (cualquiera), POST → `.create`, PUT/PATCH → `.update`, DELETE → `.delete` (p. ej. `bank-details` → `bank_details`, `fuecs` → `fuec`; excepciones en `config/authorization.php` → `resource_overrides`). SUPERADMIN pasa siempre; las rutas que ya llevan `permission:`/`role:` propios no se reevalúan; catálogos (`api.v1.catalogs.*`): lectura abierta y escritura solo `SUPERADMIN`; `auth/*`, `2fa`, `dashboard`, `notifications`, `signatures`, `tracking`, `integrations` quedan exentas. Hoy evalúa ~391 de 489 rutas (`php artisan authz:report [--sin-mapa]`).
  - **Arranca en MODO AUDITORÍA** (`AUTHZ_ENFORCE=false`, por defecto): no bloquea; escribe `Autorización (auditoría): se habría rechazado este acceso` con usuario, roles, ruta, método y permiso exigido. **Para activarlo** poner `AUTHZ_ENFORCE=true` en el `.env` de producción SOLO después de revisar ese log una semana (`grep "se habría rechazado" storage/logs/laravel.log`).
  - **Antes de activar hay una decisión de negocio:** con los roles locales, `EMPLEADO` (2 usuarios) y `HSEQ` (1) tienen **0 permisos** (el seeder deja `$empleadoPerms` vacío a propósito), así que perderían ~300 rutas; `AFILIADO` (33 permisos) perdería ~230, sobre todo lecturas administrativas (empresa, contactos, sucursales…) y las escrituras de flota; `ADMIN_EMPRESA` solo perdería crear/eliminar empresas. Hay que decidir qué permisos recibe cada rol (o crear roles nuevos) antes de activar; el log de auditoría muestra qué se usa realmente. Simulación repetible: ver `AutorizacionPorRecursoTest` y `authz:report`.
  - Sin evaluar todavía (~98 rutas): `fleet-management/vehicles/{…}` (historial, ficha técnica, entrega), `third-parties/technical-sheet`, `control-sheets/…` sueltas y utilidades de sesión. Pruebas: `AutorizacionPorRecursoTest`.
- **Pendiente:** roles inconsistentes entre backend (`ADMIN_EMPRESA`) y frontend/`DashboardController` (`ADMINISTRADOR`, `admin`...): normalizar.
- **Pendiente:** `VITE_ENCRYPTION_KEY` del frontend está expuesta en el bundle y los `.env` versionados.
- **Pendiente operativo:** cuenta SMTP suspendida (ver "Incidencia SMTP") y definir `CRON_SECRET` en producción si algo externo llama a `/public/cron/*`.

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
- **Plan de rendimiento:** `PLAN-ARQUITECTURA-RENDIMIENTO-NOA.md` en la raíz del backend (backlog ARQ-xxx). Pendientes ejecutables sin cola: ARQ-004R, ARQ-006, ARQ-007, ARQ-008 (falta migración de índices), ARQ-014 y ARQ-015. Medir siempre en producción o `vite preview`, nunca contra `localhost:5173`. Protocolo oficial en `docs/metrics/lighthouse.md` del frontend.
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
