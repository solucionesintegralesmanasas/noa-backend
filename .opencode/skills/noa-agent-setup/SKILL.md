---
name: noa-agent-setup
description: Setup agnóstico de entorno, flujos de trabajo (workflows) y generación de skills locales para desarrolladores y agentes en el proyecto NOA (noa-backend y noa-frontend-tsb). Úsala al configurar un entorno nuevo, al diseñar o ajustar el flujo de trabajo de un desarrollador con su agente, o cuando el usuario pida crear/optimizar sus skills locales siguiendo las directrices de AGENTS.md.
---

# Setup Agnóstico de Agente y Flujos de Trabajo — NOA (`noa-agent-setup`)

## 1. Propósito y Filosofía (Equipo Multi-Desarrollador)

En el proyecto **NOA** (`noa-backend` + `noa-frontend-tsb`) colaboran varios desarrolladores con distintos equipos, sistemas operativos, shells, editores y agentes de IA.

Por diseño, **el repositorio compartido NO impone skills acopladas a rutas absolutas ni a binarios específicos de una sola máquina**. En su lugar, esta skill sirve como guía de arranque para que cada desarrollador y su propio agente:
1. Diagnostiquen su máquina real.
2. Acuerden el flujo de trabajo más cómodo y rápido para su estilo de operación.
3. Generen y evolucionen sus propias **skills y workflows locales fuera del repositorio Git** (recomendado: `<workspace-root>/.agents/skills/` en la carpeta padre que agrupa `noa-backend` y `noa-frontend-tsb`, o el directorio personal de skills del agente), garantizando que todo lo que produzcan cumpla de raíz las directrices arquitectónicas de NOA.

> [!IMPORTANT]
> Nunca escribas rutas absolutas de una estación de trabajo ni automatizaciones locales dentro de `.opencode/skills/` ni en los repositorios Git compartidos. Las skills personales o dependientes del entorno viven únicamente en tu carpeta local no versionada.

---

## 2. Fase 1: Diagnóstico Automático del Entorno del Desarrollador (Agnóstico)

Antes de proponer flujos o generar skills locales, inspecciona la máquina actual sin asumir rutas ni herramientas fijas:

1. **Sistema operativo, shell y topología del workspace:**
   - Detecta el SO (Windows, macOS, Linux) y la shell activa (`pwsh`/`powershell`, `bash`, `zsh`).
   - Ubica dónde residen `noa-backend` y `noa-frontend-tsb` y verifica si el directorio padre tiene `.git` o si cada repositorio es independiente (en cuyo caso los comandos Git deben indicar la ruta con `git -C <repo>`).
2. **Binarios y ejecutores reales disponibles:**
   - Verifica qué comandos responden directamente y cuáles requieren extensión o ruta alternativa en esa máquina:
     - Backend: `php -v`, `composer --version`, conexión de BD de pruebas (`noa_test`), `php artisan test --filter=FirmasDeClasesTest`.
     - Frontend: `node -v`, `npm` vs `npm.cmd`, `npx` vs ejecutor local (`./node_modules/.bin/vitest` o `.cmd`).
     - Búsqueda y utilidades: `git --version`, `rg` vs `git grep`, `python` / `python3`.
3. **Ubicación local para skills no versionadas:**
   - Verifica dónde descubre skills locales el agente del desarrollador sin contaminar `git status` de `noa-backend` ni `noa-frontend-tsb` (por ejemplo `../.agents/skills/` respecto a cada repo, o la ruta global de configuración del agente).

---

## 3. Fase 2: Alineación Rápida con el Desarrollador (3 Preguntas Clave)

Con el diagnóstico listo, haz una entrevista corta (máximo 1 turno, 3 preguntas concretas) para adaptar el flujo a su forma de trabajar:

1. **Foco habitual:**
   ¿Trabajas principalmente full-stack (`noa-backend` + `noa-frontend-tsb`), solo backend Laravel, solo frontend Vue 3 / móvil Capacitor, o también tareas operativas locales?
2. **Estilo de flujo preferido:**
   - **Modo estructurado por fases:** ideal para cambios grandes o arquitectónicos (`entrevista corta -> spec SPEC-0xx -> división en rondas -> implementación TDD -> revisión en dos ejes -> propuesta de commit`).
   - **Modo directo / ágil:** ideal para iteraciones rápidas o tickets acotados (`análisis breve -> implementación + pruebas de costura -> revisión en dos ejes -> propuesta de commit`), reservando `SPEC-0xx` solo cuando cambien contratos globales o rendimiento.
3. **Fricciones actuales:**
   ¿Qué comandos te suelen fallar, qué validaciones olvida tu agente o qué tareas repetitivas quieres automatizar primero en tu flujo local?

---

## 4. Fase 3: Directrices Innegociables de NOA que Todo Workflow DEBE Incorporar

Sin importar el estilo del desarrollador ni su sistema operativo, **todo workflow o skill local que generes debe incrustar como reglas duras** los contratos vigentes de `noa-backend/AGENTS.md`, `noa-frontend-tsb/AGENTS.md`, `noa-backend/CONTEXT.md` y las `SPEC-002` a `SPEC-018`:

### A. Idioma (`solo-espanol`)
- Toda comunicación humana, análisis, explicaciones, specs, comentarios en código y mensajes de commit deben redactarse estrictamente en **ESPAÑOL**.

### B. Invariantes de Backend (`noa-backend/AGENTS.md`)
1. **Cero caché de aplicación en el camino HTTP (`SPEC-010`, `SPEC-014`):** Prohibido `Cache::remember`, `Cache::put`, `Cache::has` o `Cache::add` en peticiones HTTP ordinarias, y prohibido estado estático (`static $cache`) entre peticiones (`SPEC-011`).
2. **`GET` de solo lectura:** Cero `INSERT`, `UPDATE`, `DELETE` o `$model->save()` durante peticiones `GET`.
3. **Agregar en SQL, no hidratar en PHP:** Usa `COUNT(CASE...)`, subconsultas indexadas y `whereIn` por lote; prohíbe traer colecciones masivas para filtrar o contar en memoria.
4. **Modelos ligeros sin `$with` global (`SPEC-012`):** Cada servicio declara `with(...)` explícito; los accesores que dependan de relaciones deben comprobar `relationLoaded(...)` y degradar sin disparar N+1.
5. **Alcance del actor único (`SPEC-013`):** Todo aislamiento multi-tenant, por sucursal, afiliado o conductor se aplica mediante `App\Services\ActorScope\AlcanceDelActor` (`aplicarAVehiculos`, `aplicarAConductores`, `aplicarANotificaciones`, `aplicarARastreo`). Falla cerrada (`WHERE 1 = 0`) sin contexto; nunca reimplementes reglas por rol a mano.
6. **Conservación de firmas de `BaseService`:** Todo método sobrescrito (`create(array): Model`, `update(string, array): bool`, `delete(string): bool`) debe conservar la firma exacta. Verifica siempre con `php artisan test --filter=FirmasDeClasesTest` (un error de firma mata el worker PHP).
7. **Presupuestos de consultas (`#[Group('perf')]`):** Usa `tests/Support/PresupuestoConsultas.php` y `InsertaFilas.php`. Ningún cambio que altere consultas está completo sin validar o definir su presupuesto.
8. **Estándar neutro PDF (`SPEC-018`):** Toda plantilla nueva o modificada es HTML autónomo sobre `pdf.standard.styles` (`Inter v4.1` local, paleta neutra B/N `#d8d8d8`/`#f0f0f0`/`#f7f7f7`, solo tablas sin `flexbox`/`grid`, exclusividad membrete-logo y `0` avisos en `$_dompdf_warnings`). **Prohibición absoluta: `resources/views/pdf/fuec.blade.php` no se toca.** Sincroniza `PdfService::PDF_CLAVES` con el frontend cuando agregues claves PDF.

### C. Invariantes de Frontend (`noa-frontend-tsb/AGENTS.md`)
1. **Un solo cliente HTTP (`SPEC-016`):** Usa exclusivamente `apiClient.global` con cabecera `X-Company-UUID` dinámica por petición. Prohibido reintroducir `forTenant()` o múltiples instancias Axios.
2. **Persistencia y alertas en memoria (`SPEC-016`):** No escribas `secureStorage` directo desde vistas ni uses `Preferences.keys()` por alerta de vencimiento.
3. **Permisos primero:** Condiciona toda acción o llamada protegida con `permissionsStore.can('<permiso>')` (un `403` en consola es un error de la vista).
4. **Anatomía de listados (`SPEC-008`), formularios y UI:** Usa `BasePageHeader`, `BaseFormActions`, `.noa-list-card`, `.noa-search`, `.noa-table`, `RowActions`, `ListPagination`, `PrimeSelect` (cero `<select>` nativos), `DateInput`, iconos decorativos con `aria-hidden="true"` y `utils/toast.js` / `utils/confirm.js` (prohibido importar `sweetalert2` directamente).
5. **Contratos y calidad:** Respeta el envelope `{ success, message, data }` (listado paginado en `r.data.data`) y verifica con `lint`, `test:unit`, `test:a11y` y `test:perf`.

### D. Trazabilidad, Revisión y Política de Commits
1. **Ubicación de docs:** Specs en `noa-backend/docs/specs/SPEC-0xx-<slug>.md`, ADRs en `noa-backend/docs/adr/` y métricas en `docs/metrics/` (sin `gh` CLI; ver `docs/agents/issue-tracker.md` y `docs/agents/domain.md`).
2. **Revisión en dos ejes antes de commitear:** Contrasta siempre los cambios contra:
   - **Eje 1 (Estándares y Seguridad):** cumplimiento estricto de ambos `AGENTS.md` y detección de *code smells*.
   - **Eje 2 (Especificación):** cumplimiento exacto de la `SPEC-0xx` o requerimiento, sin omisiones ni *scope creep*.
3. **Propuesta previa obligatoria y prohibición de commitear sin permiso:**
   - Al terminar una implementación o revisión, presenta la propuesta exacta de commits (`tipo(scope): descripción corta en español`, una línea, minúsculas, sin punto final) y **espera confirmación explícita del desarrollador antes de ejecutar `git commit`**.
   - Cero commits de relleno: actualiza `AGENTS.md` (tabla de specs o documentación relevante) dentro del **mismo commit** funcional.

---

## 5. Fase 4: Plantilla Modular de Workflows y Skills Locales a Generar

Con base en la Fase 1 (entorno real) y la Fase 2 (preferencias del desarrollador), genera en su carpeta local no versionada un juego modular de skills que pueda ir afinando con su agente:

1. **Enrutador maestro local (ej. `noa-workflows/SKILL.md`):**
   - Documenta los comandos exactos que funcionan en esa máquina (shell, `git -C` si aplica, ejecutor PHPUnit/Vitest, etc.) para eliminar errores de arranque en cada sesión.
   - Mapea qué flujo seguir según la tarea (nueva feature, sincronización de rama remota, PDF `SPEC-018`, vista Vue 3 `SPEC-008`/`SPEC-016`, bug/seguridad).
2. **Skills de ciclo de ingeniería (si elige flujo estructurado o híbrido):**
   - Entrevista / análisis breve → Spec `SPEC-0xx` → División en rondas cohesivas → Implementación con pruebas en costuras (`InsertaFilas` + `PresupuestoConsultas` / Vitest) → Revisión en dos ejes → Propuesta de commits.
3. **Skills de dominio según su foco (Fase 2):**
   - *Backend / PDF:* verificación de `AlcanceDelActor`, `FirmasDeClasesTest` y checklist `SPEC-018` (paleta, fuentes locales, smoke tests).
   - *Frontend / Stitch:* traducción de diseños a Bootstrap 5 / Falcon + PrimeVue 4 local, accesibilidad (`test:a11y`) y presupuesto de bundle (`test:perf`).
   - *Sincronización Git:* procedimiento seguro de `fetch`/`pull` desde `origin/feature` y auditoría de cumplimiento para no arrastrar regresiones de otros commits.
   - *Calidad y Diagnóstico:* reproducción rápida en rojo antes de parchear y pruebas adversariales multi-tenant / estados.

> [!TIP]
> Este setup es un punto de partida vivo: tras las primeras sesiones reales, anima al desarrollador a pedirle a su agente que ajuste sus skills locales cada vez que detecte una fricción nueva en su entorno o en su ritmo de trabajo.
