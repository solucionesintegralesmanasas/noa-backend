# SPEC-017: Facturación Electrónica ApiDian (FACTW) en Convivencia con Factus

**Estado:** IMPLEMENTADO (2026-11-12; ampliado y alineado a `AGENTS.md` el 2026-11-13)
**Servicios principales:** `App\Services\Billing\ApidianCliente`, `ApidianFacturacion`, `ApidianMapeo`, `FacturacionPorEmpresa`, `BillingProviderService`, `NotaPdfService`, `App\Console\Commands\SyncApidianCatalogs`.
**Pruebas asociadas:** `tests/Feature/ApidianFacturacionTest.php`, `tests/Feature/ProveedoresBillingHttpTest.php`, `tests/Feature/FacturacionComercialTest.php`.

## Problem Statement

NOA eliminó la primera integración ApiDian en 2026-11-04 y quedó con un único driver (Factus). La empresa requiere facturar **servicios** y emitir **notas crédito/débito** contra FACTW (`https://apidian.systemsfact.com.co`), manteniendo la capacidad de elegir el proveedor por empresa y **conviviendo** con Factus: una empresa puede usar Factus y otra ApiDian, sin romper el flujo existente.

## Solución

Se implementa el driver `apidian` en convivencia con `factus`, resolviendo dinámicamente el proveedor por empresa desde `billing_providers.driver` a través de `FacturacionPorEmpresa` (contratos `ContratoFacturacionElectronica` y `ContratoNotasElectronica`). ApiDian soporta autenticación por **token maestro (`whs_...`) o JWT con login y renovación automática ante 401**, usa el rango de numeración activo en FACTW y **no gestiona retenciones** en el payload DIAN.

## Decisiones de implementación

- **Resolución por empresa:** `FacturacionPorEmpresa::proveedorDe()` toma el proveedor activo de la empresa y despacha por `driver` (`factus` → `FactusFacturacion`, `apidian` → `ApidianFacturacion`, sin proveedor → `FacturacionNula`). `CommercialInvoiceService::emitirFactura()`, `CreditNoteService::emitirNota()` y `DebitNoteService::emitirNota()` validan la existencia del proveedor activo y la visibilidad por alcance (`AlcanceDelActor`).
- **Autenticación por JWT (login + refresh) y token maestro:** ApiDian (FACTW) autentica con `POST /api/auth/login` (`email` + `password`) → `access_token` (JWT) + `expires_in`. El JWT expira (~1 h) y se renueva con `POST /api/auth/refresh` pasando el JWT vigente como Bearer. `ApidianCliente::asegurarToken()` resuelve el token (maestro `whs_…` → JWT vigente → refresh → login) y `enviar()` refresca y reintenta ante un 401. Las credenciales se guardan cifradas en `billing_providers.email` + `client_password`; el JWT queda en `access_token`/`access_token_expires_at`. Al guardar el proveedor, `BillingProviderService` inicia sesión y valida credenciales.
- **Consecutivo y resolución de FACTW (regla FAB12):** al emitir facturas (`type_document_id=1`), notas crédito (`type_document_id=4`) o notas débito (`type_document_id=5`), `ApidianFacturacion` consulta `/api/ubl2.1/numbering-ranges` y toma `next` como `number` y `resolution` como `resolution_number`; los documentos internos de NOA conservan su consecutivo propio y la respuesta queda en `dian_response`/`provider_document_id`.
- **Catálogos por id de FACTW (no códigos DIAN):** `ApidianMapeo` traduce tipo de documento, régimen, responsabilidad, unidad de medida y medio de pago.
- **Municipios por DANE:** `billing:sync-apidian-catalogs` escribe `config/apidian-catalogs.php` (mapa `dane_code → id`). Un municipio sin equivalencia falla cerrado con mensaje claro.
- **Retenciones:** ApiDian no las envía (`payable_amount = tax_inclusive_amount`). La retención se conserva en la factura interna de NOA.
- **Transmisión (`send`) y estados:** reutiliza `billing_providers.send_to_dian` y mapea `ACEPTADA_DIAN`, `RECHAZADA_DIAN`, `VALIDADA_SIN_ENVIO` y `PENDIENTE_DIAN`.
- **Notas crédito y débito, PDF y correo:** `ApidianFacturacion` implementa `ContratoNotasElectronica` (`POST /api/ubl2.1/credit-note`, `POST /api/ubl2.1/debit-note` y consulta de estado `GET /api/ubl2.1/documents/{cude}`), `NotaPdfService` genera los PDF corporativos bajo las claves `nota_credito` y `nota_debito` registradas en `PdfService::PDF_CLAVES`, y `CommercialInvoiceService::enviarPorCorreo()` envía facturas vía `POST /api/ubl2.1/documents/{id}/send-email`.
- **Solo lectura en GET (Principio #2):** `GET /api/v1/billing/commercial-invoices/{uuid}/estado-dian` consulta el estado ante FACTW/DIAN sin ejecutar escrituras (`INSERT`/`UPDATE`/`DELETE`) en BD.

## Alcance

- Factura de venta electrónica de servicios (`FACTURA_VENTA`), notas crédito (`credit_notes`) y notas débito (`debit_notes`) en convivencia con Factus.
- Descarga de PDF de facturas y notas, consulta de estado DIAN y envío de correo de factura en ApiDian.
- Aislamiento completo por `AlcanceDelActor` (`commercial_invoices`, `credit_notes`, `debit_notes` en `TABLAS_AISLADAS_POR_AFILIADO`) y permisos explícitos en emisión/anulación de notas (`credit_notes.update`, `debit_notes.update`).
