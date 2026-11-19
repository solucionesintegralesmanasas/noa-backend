# SPEC-018: Estándar Neutro de Diseño e Impresión PDF y Soporte Membrete-Logo

**Estado:** IMPLEMENTADO (2026-11-17 → 2026-11-19)
**Commits principales:** `7c9bbc0`, `b07e075`, `563e061`, `1a1515a`, `5c03abd`, `cd97258`.
**Servicios y parciales principales:** `App\Services\Pdf\PdfService`, `App\Services\Procedure\RadicacionDocumentoService`, `App\Services\Billing\NotaPdfService`, `resources/views/pdf/standard/{styles,header,vehicle-line}.blade.php`, `resources/views/pdf/radicacion/partials/{styles,header,signatures}.blade.php`.
**Pruebas asociadas:** `PdfBaseEstandarTest`, `PdfBrandingTest`, `TechSheetVehiclesPdfLayoutSmokeTest`, `ServiceControlSheetPdfLayoutSmokeTest`, `EvidenceStatusPdfTest`, `FleetPdfLayoutSmokeTest`, `InspectionPdfLayoutSmokeTest`, `MaintenancePdfLayoutSmokeTest`, `ContractsAndHrPdfLayoutSmokeTest`, `CommercialPdfLayoutSmokeTest`, `RadicacionPdfLayoutSmokeTest`, `FichaTecnicaConductorPdfLayoutSmokeTest`.

## Problem Statement

Las plantillas PDF previas combinaban herencia de `@extends('pdf.layouts.base')`, colores corporativos heredados (azules `#172554`/`#1d4ed8`/`#1e40af`/`#38bdf8`, pastillas amarillas `#fcd34d` y verdes `#d1fae5`), fuentes de sistema o base-14 (`Courier New`, `Helvetica`, `Arial`) y reglas CSS problemáticas en DomPDF (`* { margin: 0 }`). Esto provocaba contraste deficiente en impresión en blanco y negro, duplicación del logo corporativo cuando la empresa configuraba una hoja membretada completa, y desaprovechamiento del espacio vertical y horizontal (cabeceras partidas con tarjetas flotantes, bloques de totales desnivelados, códigos QR mal dimensionados y actas o cartas extendidas a páginas innecesarias).

## Filosofía de diseño e impresión PDF (Prototipo A aprobado)

- **Estética neutra (digital + impresión B/N):** prioridad absoluta a la legibilidad tanto en pantalla como en impresora monocromática usando la escala neutra oficial:
  - `--pdf-banda: #d8d8d8` (bandas de título de sección)
  - `--pdf-banda-texto: #000000`
  - `--pdf-acento: #262626`
  - `--pdf-encabezado: #f0f0f0` (encabezados de tablas internas)
  - `--pdf-tenue: #f7f7f7` (celdas de etiquetas o bloques secundarios)
  - `--pdf-borde: #1f2937` (marcos principales; en `.meta-table td` bordes horizontales `1px solid #0f172a`)
  - `--pdf-borde-interno: #9ca3af` (divisiones internas)
  - Texto principal en `#000000` y `#111827`.
- **Cero etiquetas/badges decorativos en caja:** queda prohibido usar pastillas con relleno de color o bordes semánticos para estados, asuntos o títulos; la jerarquía visual se logra mediante peso tipográfico (`Inter` 400/500/600/700), mayúsculas controladas y tablas de bordes cerrados.
- **Uso disciplinado del espacio vertical y horizontal:** cabeceras institucionales cerradas alineadas al 100 % del ancho útil (`header-table`), tablas niveladas en altura inferior (como `.resumen-grid` en comprobantes comerciales o cuadrículas compactas de 2 columnas balanceadas en actas de entrega) y ajuste de densidad para que los documentos ejecutivos quepan en **1 página Carta** cuando el volumen es estándar (`technical-sheet-vehicles`, `vehicle-inspection`, `maintenance`, `commercial-invoice`, `commercial-note`, `capacity-letter`, `acceptance-letter`, `promissory-note`).
- **Exclusividad membrete-logo (sin marca duplicada):** una empresa nunca debe ver su logo repetido dos veces ni superpuesto con la hoja membretada de página completa.
- **Aislamiento multi-tenant estricto de identidad:** el logo proviene únicamente de la empresa activa (`PdfService::identidadEmpresa()` / `logoParaMarca()`, colección `LOGO` de `Company`, respaldo `logos` o columna `logo`) o cae al monograma de máximo 3 letras (`PdfService::siglasEmpresa()`), omitiendo sufijos legales (`SAS`, `LTDA`, `S.A.S`). `resolveLogoWithMime()` nunca escanea `public/img` ni `storage/app/public/companies` ni quema logos o datos de otra empresa.
- **Autonomía de plantillas y compatibilidad estricta con DomPDF:** documentos HTML autónomos sin `@extends('pdf.layouts.base')`; tipografía estática local **Inter v4.1** (`resources/fonts/inter/*.ttf`, pesos 400/500/600/700 y 400 itálica, licencia SIL OFL 1.1; caché de métricas en `storage/fonts/` ignorado en git) sin descarga remota (`isRemoteEnabled = false`), sin `Courier`/`Courier New` ni `Helvetica`/`Arial` legados; maquetación basada exclusivamente en tablas (prohibido `flexbox`, `grid`, `box-shadow` y `* { margin }`); `page-break-inside: avoid` en bloques de firmas y resúmenes; y cero avisos en `$_dompdf_warnings`.

## Arquitectura técnica: parciales, marca y sellado

### Parciales estándar (`resources/views/pdf/standard/`)

- **`fonts.blade.php`:** fuente única de las 5 reglas `@font-face` locales de `Inter v4.1` (pesos 400/500/600/700 e itálica), incluida por `styles.blade.php` y por las plantillas de maquetación específica (`service-control-sheet` y `third-parties/technical-sheet`).
- **`styles.blade.php`:** recibe `$orientation` (`'portrait'|'landscape'`) y `$pdfMargen` (`'denso'` `8mm 10mm 8mm 8mm` | `'cartas'` `18mm 20mm 18mm 20mm`), incluye `pdf.standard.fonts`, define `:root` (paleta estrictamente neutra sin badges de color) y provee `#watermark`, `.header-table`, `.meta-table`, `.section-banner`, `.info-table`, `.main-table` y `.placa-inline-tbl`.
- **`header.blade.php`:** cabecera cerrada de 3 celdas (o 2 cuando `$ocultar_celda_logo` es verdadero) con parámetros `$logo_data_url`, `$ocultar_celda_logo`, `$ancho_logo` (defecto `19`), `$ancho_meta` (defecto `26`), `$empresa_siglas`, `$empresa_transportadora`, `$empresa_detalle`, `$titulo_documento`, `$subtitulo_documento` y `$meta_sgc`. El ancho de la celda central se calcula dinámicamente como `100 - $__anchoMeta - $__anchoLogo`.
- **`vehicle-line.blade.php`:** micro-tabla alineada para `$placa` y `$numero_interno` opcional separados por `&middot;`.

### Parciales de radicación (`resources/views/pdf/radicacion/partials/`)

- **`styles.blade.php`:** incluye `pdf.standard.styles` (`pdfMargen => 'cartas'`), sobrescribe `@page` a `31mm 16mm 34mm 16mm` en modo hoja membretada vertical (o `18mm` en modo estándar) y posiciona `.pie` en `bottom: 0mm` sobre el área limpia encima de la curva inferior del membrete.
- **`header.blade.php`:** renderiza `#letterhead-page-bg` o `#watermark` según corresponda, conserva el logo del Ministerio de Transporte a la izquierda e identificación legal de la empresa al centro, y oculta el logo corporativo a la derecha cuando `$__sinMarcaVisual` es verdadero.
- **`signatures.blade.php`:** bloque de firmas (trazo base64 o firma escrita en `Inter` itálica).

### Modos de marca por empresa y documento (`PdfService`)

- La preferencia por empresa y documento se persiste en `system_configuration.pdf_branding`.
- `PdfService` define `PDF_MODO_MEMBRETE` (`'membrete'`), `PDF_MODO_FONDO` (`'fondo'`, valor por defecto en `PDF_MODO_DEFECTO`) y `PDF_MODO_LIMPIO` (`'limpio'`), junto con `PDF_CLAVES` (18 claves registradas) y claves dinámicas `radicacion_<clave>` inyectadas por `RadicacionDocumentoService::datos()`.
- `PdfService::conMarca(array $data, ?object $company, string $clave, mixed $logoModel): array` inyecta `letterhead`, `logo_fondo` y `ocultar_marca`.
- Reglas de los 3 modos:
  - **`membrete`:** `PdfService::esHojaMembretadaCompleta(?string $letterhead, string $orientation = 'portrait'): bool` detecta si la imagen corresponde a una hoja membretada de página completa (en vertical `alto > ancho`; en apaisada `ancho > alto && alto / ancho >= 0.55`). Cuando aplica, se fija `#letterhead-page-bg` cubriendo `215.9mm × 279.4mm` (o `279.4mm × 215.9mm` en apaisada) con márgenes de resguardo para evitar choques con cabecera y curva inferior (`31–32mm` arriba y `33–34mm` abajo en vertical), **se oculta la celda/imagen del logo de empresa** y **se omite `#watermark`**.
  - **`fondo` (defecto):** muestra logo de la empresa (o monograma con siglas) en cabecera y marca de agua `#watermark` (`opacity: 0.08`) centrada, sin `#letterhead-page-bg` *(excepción documentada: `service-control-sheet` omite `#watermark` porque la tabla horizontal ocupa todo el ancho y el logo de fondo restaría legibilidad en DomPDF)*.
  - **`limpio` (`ocultar_marca = true`):** oculta la celda/imagen del logo de la empresa, sin `#letterhead-page-bg` ni `#watermark`.
- **Presupuesto de consultas en reportes PDF:** `VehicleReportController::pdf` y `VehicleActivityReportController::pdf` precargan `Company::with(['media', 'systemConfiguration.media'])` antes de invocar `PdfService`; `PdfService::conMarca()` reutiliza esa relación precargada para mantener 0 consultas extra durante el render del PDF.
- **Sellado de paginado lateral:** `PdfService::sellarPaginado($pdf, ?float $margenDerechoPt)` usa `MARGEN_DERECHO_ESTANDAR_PT` (`28.35`, equivalente a 10 mm) o `MARGEN_DERECHO_PLANILLA_PT` (`19.84`, equivalente a 7 mm).

## Inventario de las 4 rondas completadas y exclusiones

| Ronda | Commit | Plantillas y archivos (`resources/views/pdf/...` / servicios) | Aspectos clave | Prueba que la protege |
| --- | --- | --- | --- | --- |
| Base y preliminares | `7c9bbc0`, `b07e075` | `standard/{styles,header,vehicle-line}`, `maintenance/service-control-sheet`, `fleet/technical-sheet-vehicles` | Paleta neutra, Inter local, aislamiento de logo, renumeración de especificaciones mecánicas vacías y 1 hoja en ficha técnica (`JG-VEH-001`) | `PdfBaseEstandarTest`, `ServiceControlSheetPdfLayoutSmokeTest`, `TechSheetVehiclesPdfLayoutSmokeTest` |
| Ronda 1 — Flota y Mantenimiento | `563e061` | `fleet/handover-record`, `fleet/vehicle-inspection`, `fleet/vehicle-history`, `fleet/vehicle-report`, `fleet/vehicle-activity-report`, `fleet/technical-sheet-vehicles`, `maintenance/maintenance`, `maintenance/service-control-sheet`, `PdfService`, `VehicleReportController`, `VehicleActivityReportController` | Hoja membretada completa (`esHojaMembretadaCompleta`), acta de entrega compacta en cuadrícula de 2 columnas, inspección y mantenimiento en 1 hoja, precarga `Company::with('media')` | `FleetPdfLayoutSmokeTest`, `InspectionPdfLayoutSmokeTest`, `MaintenancePdfLayoutSmokeTest`, `ServiceControlSheetPdfLayoutSmokeTest`, `PdfBaseEstandarTest` |
| Ronda 2 — Terceros, RR.HH. y Contratos | `1a1515a` | `third-parties/technical-sheet`, `hr/employment-contract`, `contracts/admin-charge`, `contracts/business-agreement` | Migración fuera de `pdf.layouts.base`, recibo de caja e informe de conductor sin badges ni fuentes legadas, soporte completo `membrete`/`fondo`/`limpio` | `ContractsAndHrPdfLayoutSmokeTest`, `FichaTecnicaConductorPdfLayoutSmokeTest` |
| Ronda 3 — Comercial y Parciales | `5c03abd` | `commercial-invoice`, `commercial-note`, `standard/header`, revisión de `partials/evidence-status` | Cabecera cerrada (`ancho_logo => 17`, `ancho_meta => 31`), `.resumen-grid` nivelado, `.dian-table` con QR centrado `82px × 82px` (`alt="QR DIAN"`) y márgenes para membrete vertical | `CommercialPdfLayoutSmokeTest`, `EvidenceStatusPdfTest`, `FacturacionComercialTest` |
| Ronda 4 — Radicación | `cd97258` | `RadicacionDocumentoService::datos()`, `radicacion/partials/{styles,header}`, `radicacion/capacity-letter` y las 7 vistas hermanas (`acceptance-letter`, `affiliation-contract`, `policy-inclusion-letter`, `promissory-note`, `renewal-request`, `service-provision-contract`, `unlink-record`) | Inyección de `PdfService::conMarca`, migración de `capacity-letter` fuera de `pdf.layouts.base` (1 hoja con membrete, sin azul ni badges), ajuste de pie sobre curva del membrete | `RadicacionPdfLayoutSmokeTest`, `RadicacionHttpTest`, `RadicacionFirmaTest` |

- **Exclusión obligatoria:** `resources/views/pdf/fuec.blade.php` (`PdfService::getFuecPdf`) **queda expresamente excluida** de este rediseño y no debe modificarse (formato regulado FUEC / Ministerio de Transporte).
- **Legado congelado:** `resources/views/pdf/layouts/base.blade.php` y `resources/views/pdf/layouts/partials/*` permanecen únicamente por compatibilidad histórica y **no deben usarse** en plantillas nuevas ni modificadas.

## Checklist obligatorio para nuevas o futuras modificaciones de PDFs

1. Maquetar el documento como HTML autónomo sin `@extends('pdf.layouts.base')`.
2. Incluir `@include('pdf.standard.styles', ...)` (o `pdf.radicacion.partials.styles` en trámites de radicación) usando tipografía `Inter` local y tablas en lugar de `flexbox`, `grid`, `box-shadow` o `* { margin }`.
3. Usar exclusivamente la paleta neutra oficial (`#d8d8d8`, `#f0f0f0`, `#f7f7f7`, `#1f2937`, `#9ca3af`, `#000000`, `#111827`) sin badges/pastillas de colores ni `Courier New` o `Helvetica`.
4. Soportar los 3 modos de marca (`membrete`, `fondo`, `limpio`) mediante `PdfService::conMarca()` y `esHojaMembretadaCompleta()`, ocultando el logo de empresa cuando hay hoja membretada completa o modo `limpio` y respetando las zonas de logotipo y pie del membrete.
5. En endpoints con presupuesto de consultas estricto (como reportes), precargar `Company::with(['media', 'systemConfiguration.media'])` antes del render para mantener 0 consultas extra en el PDF.
6. Agregar o actualizar una prueba smoke en `tests/Feature/` que verifique la ausencia de colores/fuentes legados sobre el cuerpo (`Str::after($html, '<body')`), los 3 modos de marca y cero avisos en `$_dompdf_warnings`.
7. Generar una muestra visual con hoja membretada real e inspeccionar que no haya solapes, cortes de tablas o páginas sobrantes.
8. Nunca modificar `resources/views/pdf/fuec.blade.php` ni el flujo de `getFuecPdf`.
