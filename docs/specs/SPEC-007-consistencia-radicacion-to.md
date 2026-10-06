# SPEC-007 — Consistencia del módulo Radicación TO con el resto de la plataforma

**Estado:** EN CURSO (2026-10-06). Hecho en backend: orden de pasos, `permission:` en las 9 rutas, empresa del contexto, código único y retiro de `radicacion_to.delete` (`RadicacionHttpTest`, 6 casos). Hecho en frontend (primera iteración): una sola línea de tiempo con un paso en proceso, botón "Cerrar paso N", acciones condicionadas al permiso, título de sección sin mayúsculas y sin doble separador. Hecho también: store Pinia, listado paginado con buscador y botón "Nuevo", formulario en `/radicacion/nuevo` con `useAccessibleForm` que al guardar lleva al wizard. Decidido e implementado: el menú queda como "Radicación TO" (corto) y títulos y breadcrumbs usan "Radicación de tarjeta de operación"; el paso actual va en azul (el naranja se reserva para "editar") y la placa es un chip gris como en el listado.
**Fecha:** 2026-10-06
**Alcance:** Backend (`/api/v1/procedure/radicacion/*`) y frontend (`features/radicacionTO`). Rol `AFILIADO` y usuarios con permisos `radicacion_to.*`.
**Origen:** revisión comparativa del módulo contra tarjetas de operación, vehículos y la convención de AGENTS.md, con capturas de la lista, el formulario "Nuevo trámite" y el wizard del expediente.
**Label:** `ready-for-agent` (`gh` no está instalado: queda como archivo hasta publicarlo como issue).

## Problem Statement

El módulo de Radicación de tarjeta de operación se construyó aparte del resto de la plataforma y hoy se comporta distinto en tres frentes:

1. **Reglas de negocio flojas.** Un expediente es una ruta de pasos (p. ej. Inclusión de pólizas → Carta de aceptación → Capacidad → Tarjeta de operación). Antes se podía cerrar un paso sin cerrar los anteriores y volver a completar uno ya completado. Las rutas del módulo tampoco exigen permisos propios: con `authz` en auditoría, un usuario sin `radicacion_to.*` (p. ej. un afiliado) puede llamarlas. El `company_uuid` de los expedientes y de los enlaces de firma lo manda el cliente sin verificar que sea una empresa suya, y un mismo código de trámite se puede crear dos veces.
2. **Estructura distinta.** No sigue el patrón de los demás módulos: sin store, sin separar listado y formulario, listado sin paginación ni buscador reales (tope fijo de 50), validación manual en vez de `useAccessibleForm`, y los botones de crear, avanzar, enlace de firma y TXT no dependen del permiso.
3. **Aspecto visual distinto.** Nombre del módulo distinto en menú, título y breadcrumb; títulos de sección en mayúsculas pequeñas; etiquetas más oscuras; doble separador sobre los botones; estados de pasos con colores que no coinciden con el resto, y en el wizard dos pasos "En proceso" a la vez.

## Solution

Que el módulo se comporte, se estructure y se vea como el resto, sin cambiar lo que ya funciona (ruta de pasos, contratos y firma por enlace, PDF y TXT RUNT).

Desde la perspectiva del usuario:
- Solo avanza al paso siguiente cuando el anterior está completado, y no repite un paso cerrado.
- Solo ve y usa las acciones para las que tiene permiso; un afiliado sin permiso recibe 403, no datos de otros.
- Crea un expediente en una pantalla propia y consulta el listado con buscador y paginación, como en tarjetas de operación.
- Ve un solo nombre del módulo, colores de estado coherentes (verde completado, azul/actual, gris pendiente) y botones que dicen lo que hacen ("Cerrar paso 3").

## User Stories

1. Como usuario de radicación, quiero que no se pueda cerrar un paso sin haber cerrado el anterior, para que el expediente respete la ruta del trámite.
2. Como usuario de radicación, quiero que un paso ya completado no se pueda completar otra vez, para no alterar el avance por un doble clic.
3. Como usuario de radicación, quiero ver los pasos futuros en gris y solo el paso actual resaltado, para saber qué toca hacer ahora.
4. Como usuario de radicación, quiero que el botón diga "Cerrar paso N" y no "Finalizar trámite" cuando solo cierro un paso, para no confundirlo con terminar todo el expediente.
5. Como usuario de radicación, quiero que los documentos de un paso aún no disponible estén deshabilitados con una explicación, para no abrir PDF que no existen.
6. Como administrador de empresa, quiero que crear expedientes, avanzar pasos, generar enlaces de firma y TXT exijan sus permisos `radicacion_to.create` y `.update`, para controlar quién hace qué.
7. Como administrador de empresa, quiero que ver expedientes, detalle, ruta, validación y documentos exijan `radicacion_to.index`, para que la lectura también esté controlada.
8. Como afiliado sin permisos de radicación, quiero recibir 403 al llamar estas rutas, para que no se filtren expedientes de otros afiliados de mi empresa.
9. Como usuario con solo lectura, quiero que no se me muestren los botones de crear, avanzar, enlace de firma y TXT, para no recibir un 403 al usarlos.
10. Como administrador de empresa, quiero que el expediente solo se cree para una empresa a la que pertenezco, para que nadie cree trámites a nombre de otra.
11. Como administrador de empresa, quiero que el enlace de firma solo se genere para contratos de mi empresa, para evitar enlaces cruzados entre empresas.
12. Como usuario de radicación, quiero que el código de trámite no se pueda repetir dentro de la empresa, para no tener expedientes duplicados.
13. Como usuario de radicación, quiero un listado con buscador y paginación, para encontrar un expediente aunque haya más de 50.
14. Como usuario de radicación, quiero crear el expediente en su propia pantalla con botón "Regresar", como en tarjetas de operación, para tener la misma navegación en toda la plataforma.
15. Como usuario con lector de pantalla, quiero que el formulario use etiquetas, errores con `role="alert"` y foco al primer error, para poder completarlo (convención `useAccessibleForm`).
16. Como usuario de la plataforma, quiero que el módulo se llame igual en menú, título y breadcrumb, para ubicarme sin dudar.
17. Como usuario de la plataforma, quiero que los títulos de sección y las etiquetas tengan el mismo estilo que en los demás formularios, para que la pantalla me resulte familiar.
18. Como usuario de la plataforma, quiero un solo separador sobre los botones del formulario y botones secundarios iguales a los de otros formularios, para que no parezca un módulo aparte.
19. Como usuario de la plataforma, quiero que los colores de estado del listado y del wizard signifiquen lo mismo que en documentos y vencimientos, para interpretarlos sin leyenda.
20. Como persona del equipo, quiero que el encabezado del wizard no repita "paso 3 de 4" y "En proceso" en dos sitios, para que la pantalla sea más limpia.
21. Como persona del equipo, quiero pruebas del módulo (crear, avanzar, orden, permisos, aislamiento y estado del wizard), para cambiarlo sin romper el flujo.

## Implementation Decisions

- **Orden de pasos (HECHO):** al avanzar, el servicio rechaza con 422 un paso ya completado o cuyo paso anterior no está completado; el paso inicial de la ruta y el expediente padre pasan siempre. La validación de requisitos de cada paso no cambia.
- **Permisos por ruta:** las nueve rutas del módulo llevan `permission:` explícito: `radicacion_to.index` (expedientes, detalle, ruta, validar, documento), `radicacion_to.create` (expediente, enlace-firma), `radicacion_to.update` (avanzar, txt). Mismo patrón que SPEC-005 con el reporte de vehículos, para que el 403 no dependa de `AUTHZ_ENFORCE`. Los tres permisos ya existen y los tiene `ADMIN_EMPRESA`; `SUPERADMIN` pasa siempre; `AFILIADO`, `EMPLEADO` y `CONDUCTOR` no los tienen y siguen sin tenerlos. Sin cambios en `RoleSeeder` (no se vuelve a correr en producción).
- **Permiso sin ruta (RESUELTO 2026-10-06):** se retiró `radicacion_to.delete` de `PermissionSeeder`, `RoleSeeder` y de la base local; en producción hay que borrarlo desde "Roles y permisos" o con SQL (no se vuelve a correr el seeder).
- **Empresa del expediente y del enlace de firma:** `company_uuid` se toma del contexto de empresa (`current_company_uuid`) o, si se envía, se valida con `exists` y que pertenezca al usuario (SUPERADMIN en cualquiera). El filtro `?company_uuid` del listado se valida igual.
- **Unicidad:** `procedure_code` único por empresa entre expedientes padre; el 422 lleva mensaje en español. Requiere comprobar antes si hay duplicados en producción para decidir si la migración o la regla de validación los tolera.
- **Frontend, estructura:** store de Pinia del módulo; separar listado y formulario de creación (rutas propias, como tarjetas de operación); listado con búsqueda y paginación contra `expedientes`; formulario con `useAccessibleForm`; botones de crear, avanzar, enlace y TXT condicionados con `permissionsStore.can(...)`.
- **Frontend, visual:** nombre único del módulo; títulos de sección en el estilo compartido; etiquetas con el estilo común; un solo separador de pie; botones secundarios iguales a los de otros formularios; estados de paso en tres niveles (verde completado, azul actual, gris pendiente) y el estado `RECIBIDO` pintado como pendiente; encabezado del wizard sin duplicar el paso; botón del paso actual con texto "Cerrar paso N"; documentos de pasos no disponibles deshabilitados con motivo.
- **Contrato del wizard:** la línea de tiempo ya devuelve `paso`, `estado` y `uuid` por paso; el frontend deriva "actual" como el primer paso no completado. No hace falta un campo nuevo.
- **Sin cambios:** estructura de datos, firma por enlace, generación de PDF y TXT RUNT.

## Testing Decisions

- **Qué es una buena prueba aquí:** parte de la petición HTTP y comprueba lo que ve el cliente (código de estado y datos), sin mirar métodos internos del servicio. El punto de prueba es único por capa: HTTP del módulo y componente del wizard.
- **Backend (HTTP, `/api/v1/procedure/radicacion/*`):** crear expediente (éxito, 422 por código repetido y por empresa ajena); avanzar (éxito, 422 por salto de paso, 422 por repetición); permisos (403 sin `radicacion_to.*` para cada una de las 9 rutas, éxito con el permiso); aislamiento (afiliado recibe 403 y no ve expedientes ajenos). **`RadicacionOrdenPasosTest` hoy llama al servicio directamente y debe subirse a este nivel.** El usuario de `RadicacionFirmaTest` necesita recibir los permisos `radicacion_to.*`, porque con el middleware un ADMIN_EMPRESA sin ellos recibiría 403.
- **Frontend (componente, vitest + jsdom + `@vue/test-utils`, `// @vitest-environment jsdom` en la primera línea):** el wizard pinta pasos completados, el actual y los pendientes (`RECIBIDO` como pendiente); el botón dice "Cerrar paso N"; los documentos de pasos no disponibles están deshabilitados; los botones de acción no aparecen sin permiso.
- **Antecedentes:** `RadicacionFirmaTest` (patrón HTTP con `Sanctum::actingAs` y `InsertaFilas`), `AislamientoAfiliadoTest` (403 y 404 por afiliado), `AutorizacionPorRecursoTest` (permisos por ruta), `fechasContrato.test.js` y `useDocumentWizard.test.js` (frontend).
- **Rendimiento:** `listarExpedientes` consulta los hijos de cada expediente por separado (N+1); `detalleExpediente` hace varias consultas por contrato. Se añade un presupuesto de consultas con `PresupuestoConsultas` (mismo número con 1 y con N expedientes) cuando se paginen, como pide SPEC-004. El tope se fija en lo medido.

## Out of Scope

- Rediseñar la línea de tiempo vertical del wizard por un stepper horizontal: se mantiene, porque los pasos traen documentos.
- Refactor de `RadicacionDocumentoService` (God Service mezclado con `Response` HTTP): ya está anotado como deuda y no se toca sin pruebas previas.
- Cancelación de expedientes: se retiró el permiso `radicacion_to.delete`.
- Dar permisos `radicacion_to.*` a otros roles o cambiar `RoleSeeder`.
- Documentación Swagger (`#[OA\...]`) del controlador y la reorganización de rutas al patrón `index/list/store/show/update/destroy`.
- El aislamiento por afiliado de `procedures` dentro de `BaseService` (`TABLAS_AISLADAS_POR_AFILIADO`): con `permission:` el afiliado queda fuera; si algún día se le da acceso a radicación, necesita su propio caso en `AislamientoAfiliadoTest`.

## Further Notes

- **Falta una captura:** no se vio el wizard con un paso futuro `RECIBIDO` bloqueado; los puntos visuales del wizard salen de una sola captura del expediente 54326.
- **Producción:** además de la unicidad del código, correr `php artisan migrate --force` si se añade migración; el orden de pasos no la necesita.
- El módulo no tiene spec propio desde 2026-10-02 (ver AGENTS.md, "Radicación TO"); este documento es el primer criterio de aceptación formal.
