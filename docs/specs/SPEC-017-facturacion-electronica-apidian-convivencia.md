# SPEC-017: Facturación Electrónica ApiDian (FACTW) en Convivencia con Factus

**Estado:** IMPLEMENTADO (2026-11-12)
**Servicios principales:** `App\Services\Billing\ApidianCliente`, `ApidianFacturacion`, `ApidianMapeo`, `FacturacionPorEmpresa`, `BillingProviderService`, `App\Console\Commands\SyncApidianCatalogs`.
**Pruebas asociadas:** `tests/Feature/ApidianFacturacionTest.php`, `tests/Feature/ProveedoresBillingHttpTest.php`.

## Problem Statement

NOA eliminó la integración ApiDian en 2026-11-04 y quedó con un único driver (Factus). La empresa requiere facturar **servicios** contra FACTW (`https://apidian.systemsfact.com.co`), manteniendo la capacidad de elegir el proveedor por empresa y **conviviendo** con Factus: una empresa puede usar Factus y otra ApiDian, sin romper el flujo existente.

## Solución

Reintroducir un driver `apidian` que resuelve por empresa desde `billing_providers.driver`, junto al `factus` existente. ApiDian autentica con un **token de API estático** (sin OAuth2), recibe el **consecutivo propio de NOA** y **no gestiona retenciones**.

## Decisiones de implementación

- **Resolución por empresa:** `FacturacionPorEmpresa::proveedorDe()` deja de filtrar `driver = factus`; toma el proveedor activo de la empresa y despacha por `driver` (`factus` → `FactusFacturacion`, `apidian` → `ApidianFacturacion`, sin proveedor → `FacturacionNula`). `CommercialInvoiceService::emitirFactura()` valida solo que exista un proveedor activo, sin filtrar por driver.
- **Autenticación por JWT (login + refresh):** ApiDian (FACTW) autentica con `POST /api/auth/login` (`email` + `password`) → `access_token` (JWT) + `expires_in`. El JWT expira (~1 h) y se renueva con `POST /api/auth/refresh` pasando el JWT vigente como Bearer. `ApidianCliente::asegurarToken()` resuelve el token (maestro `whs_…` → JWT vigente → refresh → login) y `enviar()` refresca y reintenta ante un 401. Las credenciales se guardan en `billing_providers.email` + `client_password` (cifradas); el JWT queda en `access_token`/`access_token_expires_at`. Al guardar el proveedor, `BillingProviderService` inicia sesión y valida (si falla, 422). También se admite el token maestro `whs_…` directo en `client_password` (sin expiración).
- **Consecutivo y resolución de FACTW (regla FAB12):** FACTW valida el número **y** la resolución contra SU rango de numeración y rechaza cualquier combinación que no coincida (un número fuera del rango da 422 FAB12; una resolución ajena da 422 «no coincide con ninguna resolución activa»). Por eso, al emitir, `ApidianFacturacion` lee el rango activo de factura (`GET /ubl2.1/numbering-ranges`, `type_document_id=1`, `active`, primero por `id`) y usa su `next` como `number` y su `resolution` como `resolution_number`; la factura interna de NOA conserva su propio `invoice_number` y su resolución. El `number` real de FACTW queda en `dian_response` y `provider_document_id` guarda el `document_id` numérico.
- **Catálogos por id de FACTW (no códigos DIAN):** `ApidianMapeo` traduce:
  - tipo de documento (`type_of_documents.prefix` → id): CC=3, CE=5, NIT=6, TI=2, Pasaporte=7;
  - régimen (`tax_regime` → id): 48=1, 49=2; los demás caen a 2 con aviso;
  - responsabilidad (`tax_responsibilities.code` → id): O-13=7, O-15=9, O-23=14, O-47=112, R-99-PN=117;
  - unidad (`measurement_units.code` → id): 94=70, HUR=730, ZZ=1093, resto 70;
  - medio de pago (`payment_methods.dian_code` → id): numérico igual, `ZZZ`=75, defecto 10.
- **Municipios por DANE:** el `municipality_id` de FACTW es un id interno, no el código DANE. El comando `billing:sync-apidian-catalogs` descarga `/ubl2.1/listings` una vez y escribe `config/apidian-catalogs.php` (mapa `dane_code → id`, 1122 municipios, versionado). Un municipio sin equivalencia falla cerrado con mensaje claro (no se envía un id incorrecto).
- **Retenciones:** ApiDian no las gestiona. `payable_amount = tax_inclusive_amount` y no se envían claves `withholding_*`. La retención se conserva en la factura interna de NOA (la retención no hace parte del pagaré de la factura ante la DIAN).
- **Transmisión (`send`):** reutiliza `billing_providers.send_to_dian`. Con `send=false`, FACTW valida y firma sin contactar la DIAN (`VALIDADA_SIN_ENVIO`).
- **Estados:** `ACEPTADA_DIAN`, `RECHAZADA_DIAN` (HTTP fallido o `is_valid=false`), `VALIDADA_SIN_ENVIO` (`is_valid=true` y `send=false`) y `PENDIENTE_DIAN` (`status_code "99"`, la DIAN no respondió; se reintenta).
- **`provider_document_id`:** guarda el `document_id` **numérico** de FACTW (las descargas `/documents/{id}/pdf|xml` fallan con el número de consecutivo). El `number` (consecutivo) queda en `dian_response` para reconciliar.
- **Sincronización:** `ApidianFacturacion` sincroniza `ElectronicInvoice` con el mismo patrón de `FactusFacturacion`.
- **Notas crédito/débito:** fuera de alcance en v1; `emitirNota` lanza mensaje explícito ("ApiDian aún no soporta notas").

## Alcance (v1)

Solo factura de venta de servicios. Fuera de alcance: notas crédito/débito, nómina, documento soporte, documento equivalente, RADIAN, XML propio firmado y sincronización automática del rango de FACTW.

## Pendientes operativos

- Canal DIAN de FACTW roto (`status_code "99"`): los documentos quedan en `PENDIENTE_DIAN` hasta que FACTW repare el canal. Reportado a FACTW por separado; no bloquea el desarrollo con `send=false`.
- El formulario de proveedores pide el token de FACTW; no hay renovación automática (el token es estático).
