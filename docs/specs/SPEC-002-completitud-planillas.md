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
como rama lateral. Transiciones permitidas: solo hacia adelante. El campo actual `is_active` se conserva
como bandera de compatibilidad (`false` = `CERRADA_OPERATIVAMENTE` o superior) para no romper reportes
ni el PDF.

**Qué significa `CERRADA_CON_EXCEPCION`:** es el estado para una unidad operativa (recorrido, día-hijo o
planilla) que **no pudo completarse por la vía normal pero tiene un motivo justificado y aprobado**. Sin
este estado solo hay dos salidas malas: dejarla `EN_CURSO` para siempre (contamina la evidencia con
pendientes eternos) o inventar datos para poder cerrarla (falsifica la evidencia).

Ejemplos: ruta no ejecutada por vía cerrada · funcionario ausente que impidió recoger la firma · día-hijo
no operado por mantenimiento del vehículo · recorrido interrumpido por fuerza mayor.

Requisitos: motivo/novedad tipificada obligatorio + aprobación de rol autorizado (§12.2) + fecha de la
decisión. Efectos: cuenta como cerrada para los totales del padre y del proyecto, pero se muestra con
distintivo propio en UI y PDF (no pasa por "cerrada normal"); una vez marcada, aplica el mismo
congelamiento backend que al cierre operativo.

## 5. Reglas de completitud por unidad operativa

**5.1 Recorrido ejecutado.** Completo si y solo si: funcionario con nombre + CC · hora final ·
kilometraje final · firma del funcionario · firma del conductor. Si falta un dato operativo, exige
novedad tipificada; sin ella queda **incompleto**.
**5.2 Disponibilidad / día sin recorrido.** No se inventa ruta. Exige: inicio operativo · responsable ·
**motivo declarado** (`availability_reason`) · firma del conductor. La firma del coordinador la eleva a
certificada. La modalidad se declara explícitamente con `day_kind` (`operacion|disponibilidad`): declararla
exige motivo y prohíbe recorridos. Para el histórico sin `day_kind`, se conserva la inferencia anterior
("sin recorridos = disponibilidad") para no exigir datos que nunca se capturaron, pero **sin exigir
motivo** en esos casos.
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
**7.2 Congelamiento.** `asegurarEditable()` con 422 en **cinco** rutas que mutan la planilla:
`update…(ServiceDeliveryControlSheetService.php:888)`, `delete…(:938)`, `start…(:951)`,
`close…(:974)` y `attachRouteMapByDriverDate()` filtrando solo abiertas (`:516`; si solo había
cerradas, explica por qué). `start` se añadió en la revisión posterior: reescribía hora, km y FUEC
de una planilla cerrada. Siguen abiertos después del cierre: firma del coordinador
(`ServiceDeliveryControlSheetController.php:621`) y firmas por recorrido en `closeRoute`.
**7.3 Idempotencia.** La firma pública del coordinador reemplaza (una vigente). `closeRoute` ya no
borra las firmas omitidas (`:738`) y un envío parcial tampoco borra los datos de cierre ya
persistidos del recorrido (`:711`: cada campo se escribe solo si llega en el payload).
**7.4 PDF.** `generateDailyServiceControlSheetPdf()` pasa `firmas_pendientes` y `dias_con_excepcion`;
la vista (`resources/views/pdf/service-control-sheet.blade.php`) renderiza dos bandas: *"EVIDENCIA
INCOMPLETA"* (lista día a día qué falta) y *"CERRADA CON EXCEPCIÓN"* (motivo aprobado). La descarga nunca
se bloquea. Los demás PDF (mensual, reporte) y el Excel no llevan banda: solo el diario es evidencia
certificable día a día.
**7.5 Listado.** Reemplazado el `exists()` por fila por 3 consultas en lote (`mapaFirmas`) para padres **y**
días hijos: 12 filas → 9 consultas constantes (antes ~21 y creciendo). Cada fila y cada día exponen `estado`,
`firmas_pendientes` y `dias_pendientes`.
**7.6 Permiso `close_exception`.** `POST /{uuid}/close-exception` con
`permission:service_delivery_control_sheets.close_exception`, asignado a `ADMIN_EMPRESA` (SUPERADMIN recibe
todos). Reutiliza la guarda de congelamiento: no es puerta trasera a una evidencia ya cerrada.

## 8. Frontend

Badge de estado administrativo (`estado` del backend) en la fila del listado y en cada día de la tabla
expandible y de la ficha de proyecto, con `title` que **enumera qué falta** (no depende solo del color).
En el listado, contador de días con evidencia incompleta; en la ficha del proyecto, resumen
*"N con evidencia incompleta"* o *"Evidencia completa"*, que es la lectura rápida para un proyecto de meses.
Botón PDF siempre activo; `title`/`aria-label` descriptivos; iconos con `aria-hidden`. Sin imports nuevos
de `sweetalert2` (usar `utils/toast.js`).

## 9. Pruebas (criterios de aceptación)

- Cerrada + `PUT`/`DELETE`/re-`close`/`start` → 422 con clave de error (`update`/`delete`/`close`/`start`); abierta + `PUT` → 200.
- `close-exception` sin permiso → 403; sin sesión → 401; sin motivo → 422; sobre planilla ya cerrada → 422.
- `attachRouteMap` en cerrada → 422; en abierta → 200.
- Firma de coordinador sobre cerrada → 200.
- Envío parcial de firmas por ruta no borra la firma existente.
- PDF incompleto → 200 y contiene la banda; completo → sin banda.
- `npm run test` en verde; `php -l` en archivos tocados; recorrido manual UI abierta→cerrada.

### 9.1 Cobertura ejecutada (2026-09-25)

**Unitarias sin base de datos** (`tests/Unit/PlanillaCerradaTest.php`, `tests/Unit/DisponibilidadDiaTest.php`):
congelamiento por campo, rechazo de doble cierre, motivo obligatorio, estados excluyentes, no invención de
motivo y compatibilidad del histórico.

**Feature contra MySQL** (`tests/Feature/CongelamientoPlanillaTest.php`): el registro existe de verdad y el
rechazo atraviesa Eloquent — `update`, `delete`, `close` y `start` sobre planilla cerrada lanzan 422 con la
clave correcta y la fila queda intacta; una planilla abierta sí admite edición y cierre.

**Infraestructura de pruebas:** `phpunit.xml` apunta a **MySQL** con la base `noa_test` (no SQLite). El
esquema usa ~36 sentencias MySQL (`ALTER TABLE ... COMMENT`, `MODIFY COLUMN`, `DROP INDEX`) en ~20
migraciones ya aplicadas en producción, así que SQLite no es viable y no se alteraron migraciones
aplicadas. La base de pruebas se recrea en cada ejecución (`RefreshDatabase`).

**Backfill de firmas verificado con datos sintéticos** sobre `noa_medicion` (rollback + re-migración):
disponibilidad legada con 1 firma → `conductor` vigente; con 2 → `conductor` + sin rol reemplazada;
planilla con recorridos → orden `funcionario`/`conductor` conservado; coordinador → `coordinador`.
Filas de prueba eliminadas después.

`php artisan test`: **34 tests verdes** (antes 27). Frontend: `npm run test` en verde (9 tests + a11y + perf + lint).

**Tests nuevos de esta fase:**
- `tests/Feature/CierreExcepcionTest.php` — cobertura HTTP real con **Sanctum + Spatie**: 403 sin permiso,
  401 sin sesión, 200 con permiso (y comprueba motivo/aprobación/estado), 422 sin motivo y 422 sobre cerrada.
  Nota: la API envuelve la validación en `error.details[].field`, no en `errors`.
- `tests/Feature/FirmasRecorridoTest.php` — §7.3 E2E: el reenvío parcial **conserva** la firma omitida y el
  reenvío de un rol marca la anterior como `reemplazada` sin borrarla.

**Pendiente de cobertura:** el endpoint HTTP (autenticación Sanctum + permisos Spatie) y la descarga real
del PDF con la banda de evidencia incompleta (§7.4), todavía no implementada.

## 10. Fases

1. Migración + backfill + `firmasPendientes`. 2. Congelamiento + pruebas §9. 3. Idempotencia +
no-borrado parcial. 4. PDF + listado + badges. 5. ADR-001 a ACEPTADO, plan §7 (`ARQ-004R/005R`)
actualizado.

## 11. Riesgos

Backfill ambiguo en firmas antiguas (mitigación: `NULL` explícito) · algún flujo operativo que hoy edite
cerradas empezará a fallar 422 —intencionado, pero probar antes de desplegar · subcontratados sin UUID
(mitigación: `signer_uuid` nullable + texto libre conservado) · el motivo de disponibilidad solo aplica a
días declarados a partir de la migración; el histórico queda sin motivo y así se muestra.

## 12. Decisiones (estado 2026-09-25)

1. **DECIDIDA — firmas por ruta incluidas:** la regla §5 evalúa cada recorrido con sus dos firmas
   (funcionario + conductor), además de las 3 de planilla/coordinador.
2. **DECIDIDA — `CERRADA_CON_EXCEPCION` la aprueban ambos roles:** `ADMIN_EMPRESA` y `SUPERADMIN`
   (permiso dedicado `service_delivery_control_sheets.close_exception`, §7.6).
