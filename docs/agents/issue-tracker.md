# Especificaciones y Seguimiento de Trabajo — NOA

En el proyecto NOA no se usa `gh` CLI ni un rastreador externo en la nube para las especificaciones técnicas. Toda la trazabilidad técnica vive versionada en los repositorios.

## Ubicaciones Canónicas (Rutas Relativas al Workspace)

1. **Especificaciones Técnicas (`SPEC-0xx`):**
   - Directorio: `noa-backend/docs/specs/`
   - Formato de nombre: `SPEC-0xx-<slug-descriptivo>.md` (tanto para specs de backend, frontend o transversales, ej. `SPEC-016-arquitectura-cliente-http-y-persistencia-frontend-sin-cache.md`, `SPEC-018-estandar-neutro-diseno-pdf-y-membrete.md`).
2. **Decisiones de Arquitectura (`ADR`):**
   - Directorio: `noa-backend/docs/adr/`
3. **Métricas de Rendimiento y Accesibilidad:**
   - Backend y consolidado: `noa-backend/docs/metrics/` (`cifras-vigentes.md`, `bench-*.json`).
   - Frontend: `noa-frontend-tsb/docs/metrics/` (`perf-*.json`, `a11y-*.json`, `lighthouse-*.json`).
4. **Tickets / Cortes Verticales de Trabajo:**
   - Para planes multi-sesión: archivos Markdown locales temporales en `.scratch/<slug>/issues/<NN>-<slug>.md` (fuera del repositorio) o tabla de rondas de ejecución dentro de la propia `SPEC-0xx`.

## Reglas de Sincronización

- Cuando una `SPEC-0xx` pasa a estado `IMPLEMENTADO`, se actualiza la tabla **"Specs 010 → 018 de un vistazo"** (o vigente) en el **Mapa de Contexto Vigente** de `noa-backend/AGENTS.md` (y `noa-frontend-tsb/AGENTS.md` si afecta al frontend) en el **mismo commit** del cambio (prohibido hacer commits `docs` de relleno separados).
