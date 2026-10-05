# SPEC-005 — Aislamiento de datos del afiliado (pruebas del cliente)

**Estado:** EN CURSO (2026-10-05): acceso por UUID, rastreo y reporte corregidos y probados (`AislamientoAfiliadoTest`, 17 casos) y bloqueo de correo duplicado de terceros (`AvisoCorreoTerceroTest`); pendientes mantenimientos, licencias, aportes, FUEC, reporte, notificaciones, dashboard, `GET third-parties/{uuid}` y el rol CONDUCTOR
**Fecha:** 2026-10-05
**Alcance:** Backend (API `/api/v1`), rol `AFILIADO`. Pruebas Feature sobre `noa_test`.

## Problem Statement

Un afiliado es un tercero (persona natural o jurídica) dueño de vehículos que opera bajo la empresa transportadora. Comparte la misma empresa (`company_uuid`) con decenas de afiliados más: en la base local de TRANSPORTES ESPECIALES SIN BARRERAS S.A.S. hay 27 usuarios `AFILIADO`, todos de la misma empresa. Por eso el aislamiento multi-tenant (`BelongsToCompany`, `SetCompanyContext`) **no lo protege de los demás afiliados**: dentro de la empresa, lo único que separa los datos de un afiliado de los de otro es el filtro por tercero.

Ese filtro vive hoy en dos sitios:

1. **Frontend** (`noa-frontend-tsb/src/services/api/base.service.js`): agrega `third_party_uuid` del usuario a cada GET. Sirve de conveniencia, no de control: se puede quitar o cambiar desde el navegador.
2. **Backend** (`BaseService::applyCompanyFilter`): restringe por afiliado los vehículos, conductores, licencias, inspecciones, aportes de seguridad social, `owners_drivers` y cualquier modelo con `third_party_uuid`.

Riesgos encontrados al leer el código (2026-10-05, **por confirmar con las pruebas**):

- `applyCompanyFilter` solo se aplica en los listados (`search`, `getPaginatedData` y los métodos que lo llaman) y solo cuando llega un `company_uuid`. `findByUuid`, `update` y `delete` de `BaseService` usan `query()` sin filtro de afiliado, y `VehicleController::show/update/destroy` resuelve con `getVehicleByUuid`. **Un afiliado que conozca el UUID de un vehículo o documento de otro afiliado de su empresa podría verlo, editarlo o borrarlo.**
- `VehicleController::index` (y otros listados) aceptan `third_party_uuid` en la query: hay que verificar que el valor del cliente no sustituya al del usuario autenticado.
- `authz` está en modo auditoría (`AUTHZ_ENFORCE=false`): no bloquea escrituras para las que el afiliado no tiene permiso (por ejemplo `vehicles.update` o `vehicles.delete`, que el `RoleSeeder` no le concede). Hoy esas escrituras **pasan**.

Además, ARQ-015 señala que no hay pruebas del CRUD de flota; estas pruebas cubren una parte de esa deuda desde la perspectiva del cliente.

## Solution

Una batería de pruebas Feature (`AislamientoAfiliadoTest`) que se autentica como un afiliado real en forma (un afiliado nuevo con proyecto en Cali) y otro afiliado de la misma empresa, y comprueba por la API que cada uno **solo ve y modifica lo suyo**, aunque manipule la petición. Las pruebas que fallen documentan una fuga; cada fuga confirmada se corrige en el backend (no en el frontend) en una entrega separada de este spec, con su prueba ya escrita en rojo.

## Escenario de prueba

Sembrado en `noa_test`, sin tocar la base de desarrollo:

- **Empresa E:** la transportadora (un `ADMIN_EMPRESA`).
- **Afiliado A — "afiliado nuevo de Cali":** persona jurídica con NIT 901301544 (perfil del afiliado que acaba de empezar un proyecto en Cali; no está en la copia local de la base, que llega hasta el 2026-09-08, así que se crea en el test). Tiene usuario `AFILIADO` con `company_user.third_party_uuid` = su tercero, un proyecto en Cali, un vehículo PÚBLICO con documentos y tarjeta de operación, un conductor vinculado (`owners_drivers` + licencia), un mantenimiento, una inspección y un FUEC.
- **Afiliado B:** otro afiliado de la misma empresa E con el mismo conjunto de datos.
- **Empresa F:** otra empresa con su propio afiliado (control multi-tenant).

## User Stories

1. Como afiliado A, quiero que el listado de vehículos me devuelva solo mis vehículos, para no ver la flota de otros afiliados.
2. Como afiliado A, quiero que, si alguien quita o cambia `third_party_uuid` en la petición, la API me siga devolviendo solo lo mío, para que el aislamiento no dependa del frontend.
3. Como afiliado B, quiero que el afiliado A no pueda abrir mi vehículo por UUID (`GET /fleet-management/vehicles/{uuid}`), para que mi información no se filtre.
4. Como afiliado B, quiero que el afiliado A no pueda editar ni eliminar mis vehículos, documentos, tarjetas de operación, mantenimientos ni inspecciones, aunque conozca sus UUID.
5. Como afiliado A, quiero ver solo mis documentos de vehículo, tarjetas de operación, mantenimientos (y su pronóstico), inspecciones y FUEC.
6. Como afiliado A, quiero ver solo a mis conductores (los vinculados por `owners_drivers`) y a mí mismo en terceros, para no ver datos personales de conductores ajenos.
7. Como afiliado A, quiero ver solo mis licencias de conducción y aportes de seguridad social.
8. Como afiliado A, quiero que el reporte de vehículos (pantalla, Excel y PDF) incluya solo mis vehículos.
9. Como afiliado A, quiero que el PDF de historial y la ficha técnica solo se generen para mis vehículos.
10. Como afiliado A, quiero que la campana de notificaciones solo me muestre alertas de mis vehículos y conductores.
11. Como afiliado A, quiero que el dashboard solo resuma mis datos.
12. Como afiliado A, quiero que, si mi usuario no tiene `third_party_uuid`, la API me devuelva listas vacías y no la información de toda la empresa (comportamiento actual de `applyCompanyFilter`, que hay que fijar).
13. Como afiliado A, quiero que enviar `X-Company-UUID` de la empresa F me responda 403.
14. Como administrador de la empresa, quiero que mis permisos no cambien con estas pruebas: sigo viendo a todos los afiliados.
15. Como responsable de seguridad, quiero una lista de las rutas que usa el afiliado y que `authz` rechazaría con `AUTHZ_ENFORCE=true`, para decidir sus permisos antes de activarlo.

## Implementation Decisions

- **Solo pruebas en esta entrega.** El spec define el comportamiento esperado; las correcciones de las fugas confirmadas van en commits aparte (`fix(backend): ...`), cada uno con su prueba en rojo antes del arreglo.
- **Respuesta esperada ante un recurso de otro afiliado:** `404` (igual que un UUID inexistente, sin revelar que existe). **Decisión a confirmar**; la alternativa es `403`.
- **Dónde se corrige:** en la capa de servicio, para que `findByUuid`, `update` y `delete` apliquen el mismo filtro de afiliado que los listados (por ejemplo, extrayendo de `applyCompanyFilter` el filtro por afiliado a un método reutilizable desde `query()` o `findByUuid`). Los servicios que sobrescriben `BaseService` conservan la firma (ver `FirmasDeClasesTest`).
- **Parámetro `third_party_uuid` del cliente:** para `AFILIADO`/`CONDUCTOR` se ignora o se intersecta con el del usuario autenticado; nunca lo amplía.
- **Escrituras sin permiso:** estas pruebas corren con `AUTHZ_ENFORCE=false` (el valor de producción hoy). El aislamiento por afiliado debe sostenerse **sin** depender de `authz`. Además, un caso aparte con `AUTHZ_ENFORCE=true` registra qué rutas del afiliado se bloquearían (historia 15), sin asertar el número: es informe para la decisión de negocio.
- **Sin cambios de esquema.**

## Testing Decisions

- **Buena prueba aquí:** hace peticiones HTTP como el afiliado (Sanctum, `X-Company-UUID` de su empresa, igual que el frontend) y observa la respuesta: qué UUID vienen, qué código devuelve, si la fila cambió en la base. No prueba `applyCompanyFilter` por dentro.
- **Archivo:** `tests/Feature/AislamientoAfiliadoTest.php`, con el sembrado en un trait o en `tests/Support` (reutilizar `InsertaFilas` si encaja). Datos con `company_uuid` y relaciones reales, como pide la sección de rendimiento del `AGENTS.md` (sin eso los filtros dan falsos verdes).
- **Matriz por recurso:** vehículos, documentos de vehículo, tarjetas de operación, mantenimientos (+ forecast), inspecciones, licencias, terceros/conductores, aportes, FUEC, reporte de vehículos, notificaciones y dashboard. Por cada uno: listado propio, listado sin `third_party_uuid`, listado con el `third_party_uuid` de B, `show` del recurso de B, y `update`/`delete` del recurso de B (cuando la ruta existe) comprobando que la fila **no** cambió.
- **Controles:** el admin de la empresa ve los dos afiliados; el afiliado de F no ve nada de E; el usuario `AFILIADO` sin `third_party_uuid` recibe listas vacías.
- **Antecedentes:** `TenantScopeTest`, `SeguridadAdministracionTest`, `AutorizacionPorRecursoTest`, `VehiculoParticularDocumentosTest`.
- **Ejecución:** `php artisan test --filter=AislamientoAfiliadoTest` y la suite completa antes de commitear.

## Out of Scope

- Rol `CONDUCTOR` (tiene reglas parecidas en `applyCompanyFilter`; se prueba en un spec aparte si hace falta).
- Pruebas manuales o E2E del frontend (opción B, para después).
- Activar `AUTHZ_ENFORCE` o cambiar los permisos del `RoleSeeder`.
- Radicación TO y firma de contratos (tienen `RadicacionFirmaTest`).
- Rendimiento del filtro de afiliado (las subconsultas de `applyCompanyFilter` cargan UUID en memoria; si preocupa, va a un presupuesto de consultas aparte, según SPEC-004).

## Further Notes

- **Preguntas abiertas:**
  1. ¿Respuesta ante un recurso ajeno: 404 (propuesto) o 403?
  2. ¿El afiliado debe poder ver en "terceros" algo más que a sí mismo y a sus conductores (por ejemplo, clientes de su proyecto)?
  3. El afiliado real (NIT 901301544) no está en la base local. Si se quiere un recorrido manual con su usuario, hace falta una copia de la base posterior a su alta.
- Relacionado: "Fase C — autorización por recurso" y "Multi-tenant" en el `AGENTS.md`; ARQ-015 del plan.

## Adenda 2026-10-05 — rastreo del afiliado y reporte de vehículos

- **Decisión de negocio:** el afiliado (p. ej. TURISVAL) puede **rastrear a sus propios conductores** en el mapa en vivo y ver su historial de ruta; el **reporte de vehículos es solo de SUPERADMIN y ADMIN_EMPRESA**.
- **Rastreo:** `AFILIADO` recibe `locations.view` y `locations.history`. `AlcanceAfiliado` (`app/Services/Tracking`) acota en el backend: `active-drivers`, `last-location/{uuid}`, `driver/{uuid}/history` y `driver/{uuid}/stats` solo devuelven conductores vinculados al afiliado (`owners_drivers`) o asignados en proyectos a sus vehículos; los ajenos responden 404. No recibe `locations.geofences` ni `locations.alerts` (son de administración); las vistas de mapa no consultan esos endpoints sin el permiso.
- **Reporte de vehículos:** se retira `reports.vehicles.index` al rol `AFILIADO` y las cinco rutas de `api/v1/reports/vehicles` llevan `permission:` (índice/catálogos, `export-excel`, `export-pdf`), así que el afiliado recibe 403 aunque `AUTHZ_ENFORCE` siga en auditoría.
- **Pruebas:** `AislamientoAfiliadoTest` (rastreo propio vs. ajeno, administrador ve todos, reporte 403 para el afiliado).
- **Despliegue:** `RoleSeeder` no se vuelve a correr en producción (`syncPermissions` pisaría ediciones hechas desde la UI): conceder `locations.view`/`locations.history` y quitar `reports.vehicles.index` al rol AFILIADO desde "Roles y permisos". En la base local ya está aplicado.
