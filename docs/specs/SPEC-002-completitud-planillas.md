# SPEC-002 — Regla de completitud de planillas PCP

- **Estado:** PROPUESTO
- **Fecha:** 2026-09-25
- **Contexto:** `PLAN-ARQUITECTURA-RENDIMIENTO-NOA.md` (ARQ-004R/005R), ADR-001
- **Decisiones tomadas:** bloquear la edición al cerrar (backend) · avisar —no bloquear— si faltan
  firmas · el coordinador certifica, no cierra operativamente

## 1. Propósito

Definir qué significa que una planilla de control de prestación de servicios esté **completa**, separar el
**cierre operativo** de la **certificación administrativa**, y congelar la evidencia al cerrar. Resuelve que
hoy `is_active` solo distingue abierta/cerrada, que el frontend exige firmas que el backend no exige, y que
las firmas se interpretan por posición en vez de por rol.

## 2. Alcance

**Dentro:** modelo de estados, reglas por unidad operativa, rol de firmante, idempotencia de firmas, banda
de aviso en PDF, badges en listado y ficha de proyecto, congelamiento backend con 4 guardas, pruebas.
**Fuera:** archivado del PDF con hash (descartado en ADR-001 salvo requisito legal futuro), retención GPS,
cola local móvil, rediseño del flujo de captura diaria.

## 3. Glosario

| Término | Definición |
|---|---|
| Unidad operativa | Un recorrido ejecutado, o un día-hijo, o una jornada de disponibilidad |
| Cerrada operativamente | Todas sus unidades operativas cumplen su regla (§5); admite firma tardía del coordinador |
| Certificada | Cerrada operativamente + firma vigente del coordinador |
| Firma propia / heredada | Propia: `entity_type` + `entity_id` + `signer_role` corresponden a la unidad; heredada: tomada de la planilla padre por respaldo (`PdfService.php:972-975`) |
| Evidencia incompleta | Cerrada operativamente pero con `firmas_pendientes` no vacío |

## 4. Modelo de estados

`BORRADOR → EN_CURSO → PARCIAL → CERRADA_OPERATIVAMENTE → CERTIFICADA`, más `CERRADA_CON_EXCEPCION`
(ausencia justificada + rol autorizado) como rama lateral. Transiciones permitidas: solo hacia adelante,
excepto `CERRADA_CON_EXCEPCION` que requiere permiso explícito. El campo actual `is_active` se conserva
como bandera de compatibilidad (`false` = `CERRADA_OPERATIVAMENTE` o superior) para no romper reportes
ni el PDF.

## 5. Reglas de completitud por unidad operativa

**5.1 Recorrido ejecutado.** Completo si y solo si: funcionario con nombre + CC · hora final ·
kilometraje final · firma del funcionario · firma del conductor. Si falta un dato operativo, exige
novedad tipificada; sin ella queda **incompleto**.
**5.2 Disponibilidad / día sin recorrido.** No se inventa ruta. Exige: inicio operativo · responsable ·
motivo · firma del conductor. La firma del coordinador la eleva a certificada.
**5.3 Planilla multi-ruta.** Completa cuando todos sus recorridos cumplen §5.1. El cierre global no
re-pide firmas ya guardadas por ruta.
**5.4 Planilla multi-día.** Cada hijo se evalúa independiente (§5.1/§5.2). El padre muestra días
programados / operados / cerrados / pendientes / cancelados con motivo. Un hijo cancelado con motivo y
aprobación no bloquea al padre.

## 6. Modelo de datos

Migración sobre `signatures`: agregar `signer_role` (`conductor|funcionario|coordinador`), `scope`
(`planilla|recorrido`), `status` (`vigente|reemplazada|revocada`), `signed_at`, `signer_uuid` (nullable,
para subcontratados en texto libre). Backfill: `funcionario`/`conductor` por orden de inserción donde sea
inequívoco; el resto queda `NULL` = "rol heredado, interpretar por posición solo en lectura legacy".
Unicidad lógica: una sola `vigente` por (entidad + rol); el reemplazo marca la anterior como
`reemplazada`, nunca borra.

## 7. Backend

**7.1 `firmasPendientes($record): array`.** Devuelve etiquetas (`conductor`, `funcionario`,
`coordinador`, `ruta:{uuid}`) calculadas por rol vigente, no por posición ni `exists()` simple. Incluye
rutas cerradas sin firma propia y marca las heredadas como tales.
**7.2 Congelamiento.** `asegurarEditable()` con 422 en `update…(ServiceDeliveryControlSheetService.php:680)`,
`delete…(:708)`, `close…(:740)` y `attachRouteMapByDriverDate()` filtrando solo abiertas (`:284-330`).
Siguen abiertos: firma del coordinador (`ServiceDeliveryControlSheetController.php:621`) y firmas por
ruta en `closeRoute`.
**7.3 Idempotencia.** La firma pública del coordinador reemplaza (una vigente). `closeRoute` ya no borra
las firmas omitidas en un envío parcial (`:522-525`).
**7.4 PDF.** `generateDailyServiceControlSheetPdf()` pasa `firmas_pendientes`; la vista
(`resources/views/pdf/service-control-sheet.blade.php`) renderiza la banda *"EVIDENCIA INCOMPLETA —
faltan N firma(s)"*. La descarga nunca se bloquea.
**7.5 Listado.** Reemplazar el `exists()` por fila (`:107`, N+1) por `withCount` por rol; exponer
`firmas_pendientes` por fila para la UI.

## 8. Frontend

Matriz visible por planilla (días × recorridos × firmas) en listado y ficha de proyecto; badge `2/3`
warning por día; botón PDF siempre activo; `title`/`aria-label` descriptivos; errores con `role="alert"`.
Sin imports nuevos de `sweetalert2` (usar `utils/toast.js`).

## 9. Pruebas (criterios de aceptación)

- Cerrada + `PUT`/`DELETE`/re-`close` → 422 con clave de error (`update`/`delete`/`close`); abierta + `PUT` → 200.
- `attachRouteMap` en cerrada → 422; en abierta → 200.
- Firma de coordinador sobre cerrada → 200.
- Envío parcial de firmas por ruta no borra la firma existente.
- PDF incompleto → 200 y contiene la banda; completo → sin banda.
- `npm run test` en verde; `php -l` en archivos tocados; recorrido manual UI abierta→cerrada.

## 10. Fases

1. Migración + backfill + `firmasPendientes`. 2. Congelamiento + pruebas §9. 3. Idempotencia +
no-borrado parcial. 4. PDF + listado + badges. 5. ADR-001 a ACEPTADO, plan §7 (`ARQ-004R/005R`)
actualizado.

## 11. Riesgos

Backfill ambiguo en firmas antiguas (mitigación: `NULL` explícito) · algún flujo operativo que hoy edite
cerradas empezará a fallar 422 —intencionado, pero probar antes de desplegar · subcontratados sin UUID
(mitigación: `signer_uuid` nullable + texto libre conservado).

## 12. Decisiones abiertas (confirmar antes de implementar)

1. ¿La regla §5 incluye firmas por ruta (recomendado) o solo las 3 fijas?
2. ¿`CERRADA_CON_EXCEPCION` requiere qué rol: `ADMIN_EMPRESA`, `SUPERADMIN`, o ambos?
