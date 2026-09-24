# ADR-001 — PDF diario como evidencia archivada

- **Estado:** PROPUESTO (en espera de decisión de negocio §5)
- **Fecha:** 2026-09-24
- **Contexto:** `PLAN-ARQUITECTURA-RENDIMIENTO-NOA.md`, tarea ARQ-005 (bloqueada: hosting sin cola)
- **Afecta:** `PdfService::generateDailyServiceControlSheetPdf()`, `ServiceDeliveryControlSheetController::downloadDailyPdf()`, colección `ROUTE_MAP`, `ProjectsDetailView.vue` (E implementado en `a451ea5`)

## 1. Problema

El PDF diario de la planilla se **regenera en cada descarga** (`ServiceDeliveryControlSheetController.php:424-430`) y
nunca se conserva. Editar una planilla o eliminar una ruta de hace meses cambia la "evidencia" sin dejar rastro.
Para proyectos de varios meses (p. ej. 4 meses) esto invalida el valor administrativo del documento.

## 2. Decisión técnica (aprobada en diseño)

Archivar el PDF en la primera generación y servir el archivo archivado en las descargas siguientes, con una
acción explícita de "regenerar". Sin workers es viable: el costo pesado (consultas + OSM + DomPDF) se paga una
sola vez y el resto son descargas de un archivo estático.

Diseño previsto:

1. `downloadDailyPdf`: si existe el PDF archivado de la planilla, lo sirve; si no, lo genera, lo guarda
   (disco local + registro en MediaLibrary asociado a la planilla) y lo sirve.
2. Cada archivo lleva fecha de generación y hash SHA-256 del contenido; el hash se guarda en la planilla
   (nueva columna `pdf_archivado_hash` + `pdf_archivado_at`) para detectar manipulación posterior.
3. Acción "Regenerar" disponible solo con permiso `service_delivery_control_sheets.update`; regenerar
   **versiona** (conserva el anterior) o **reemplaza** según la decisión de negocio §5.
4. Estimación de almacenamiento: ~200 KB por PDF; 500 planillas ≈ 100 MB por proyecto. Despreciable.

## 3. Consecuencias

- Positivas: evidencia inmutable, descargas rápidas, mismo archivo para todos los usuarios, base para ZIP
  mensual por proyecto y para política de retención (conservar PDF, purgar datos).
- Negativas: necesita migración (2 columnas) y definir qué ocurre al corregir una planilla con PDF archivado.

## 4. Alternativas descartadas

- **Solo hash sin archivar:** detecta cambios pero obliga a regenerar (lento) y no conserva el original.
- **Cola + Job (ARQ-005 original):** imposible en el hosting actual (sin workers ni Cron).

## 5. DECISIÓN DE NEGOCIO PENDIENTE (elige una)

- **Opción 1 — Versionar (recomendada):** al corregir una planilla con PDF archivado, el archivo anterior se
  conserva y el nuevo lleva versión +1. Historial completo, auditoría total. Costo: más archivos.
- **Opción 2 — Invalidar y regenerar:** al corregir, el archivado se marca obsoleto y la próxima descarga
  regenera. Simple, pero se pierde el documento que ya se pudo entregar al cliente.
- **Opción 3 — Congelar:** una planilla con PDF archivado ya no se puede editar (solo con rol superior y
  dejando registro). Máxima rigidez, solo si la norma lo exige.

Sin esta elección no se implementa: el comportamiento ante correcciones es regla de negocio, no técnica.
