# SPEC-015: Mapas de Ruta sin Caché en Disco y Respaldo GPS Acotado

> Estado: IMPLEMENTADO (2026-11-06).
> Principio rector: es una **mejora, no una pérdida**. Ninguna funcionalidad visible hoy (fondo cartográfico, ruta, funcionario, proyecto, conductor) puede desaparecer.

## Problem Statement

`RouteMapService::generar` obtiene el mapa del PDF diario con `descargarMapaOsm` (cURL a un servidor público, timeout de 6 s por defecto, `services.route_map.timeout`) y lo guarda como PNG en `storage/app/route-maps/*.png`, con poda probabilística (`tocaLimpiar` 1 de 20, `limpiarCacheAntigua`, `cache_days`). Cuando falla la descarga usa `generarPngRespaldo` (GD), que ya existe.

`LocationTrackingService::ultimasRutasConocidas` (respaldo del monitor cuando la planilla de hoy no trae rutas) trae hasta 500 planillas (`limit(500)`) con 5 relaciones cada una y elige la primera por conductor en PHP.

## Solution

1. **Mapa:** quitar la caché en disco y la poda. El PNG se obtiene en memoria y se devuelve en Base64. El fondo cartográfico se conserva mientras el proveedor responda (no se pierde nada); si falla o está desactivado, se usa el respaldo GD, que se mejora (ver "Mejoras del respaldo GD").
2. **Monitor:** sustituir los 500 registros por una consulta acotada que devuelve **una planilla por vehículo/placa**, conservando exactamente los criterios actuales de elección, y precargar relaciones solo de las planillas elegidas.

## User Stories

1. Como Coordinador, quiero descargar los PDFs diarios en segundos, aunque el servidor de mapas externo esté lento o caído.
2. Como Administrador de Infraestructura, quiero que `storage` no acumule PNG temporales de mapas.
3. Como Monitor de Flota, quiero ver de inmediato la última ruta conocida de cada conductor sin que el servidor procese cientos de planillas en PHP.
4. Como Auditor, quiero poder desactivar el envío de coordenadas a terceros por configuración, y que entonces el documento siga siendo completo.
5. Como Desarrollador, quiero pruebas herméticas que no necesiten Internet.

## Implementation Decisions

### A. `RouteMapService`

- **Nombres reales:** el punto de entrada es `generar(Collection $points)`; no existen `obtenerMapaBase64` ni una captura `ROUTE_MAP` (no inventarlas).
- **Retirar:** `CARPETA_CACHE`, la lectura/escritura en disco de `obtenerImagen`, `tocaLimpiar`, `limpiarCacheAntigua` y las claves de config `cache_enabled` y `cache_days` (en `config/services.php` y `.env.example`).
- **Conservar:** `descargarImagen` (protegido, es la costura que las pruebas sustituyen), `construirUrlMapbox`, `simplificar`, `calcularStats` y el límite `MAX_POINTS_DRAW`.
- **Proveedor:** `services.route_map.provider` admite `osm` (por defecto, comportamiento actual), `mapbox` y `none` (nuevo: sin red, solo GD). Con `none` no se hace ninguna llamada externa.
- **Orden de resolución:** proveedor externo en memoria → si devuelve vacío o lanza → `generarPngRespaldo`. El resultado nunca se escribe en disco.
- **Salida GD en memoria:** `ob_start()` + `imagepng()` + `ob_get_clean()` (más simple que `php://temp`; mismo efecto, sin archivos).

### Mejoras del respaldo GD (sin cambiar el diseño de las plantillas)

- El respaldo offline nativo en `RouteMapService::generarPngRespaldo` consolida retícula tenue de referencia (6×3 divisiones), trazado visible de 4 px y marcadores de inicio (verde) y fin (rojo) con borde blanco, operando 100% en memoria mediante `imagepng` y `ob_start`/`ob_get_clean`, sin tocar el sistema de archivos.
- Mantiene el mismo tamaño estándar (900×380) y la misma estructura de retorno (`imagen_base64`, `mime`, `sin_datos`, `stats`).

### B. `LocationTrackingService::ultimasRutasConocidas`

- **No** usar `MAX(id)` ni `GROUP BY third_party_uuid`: esa tabla no tiene `third_party_uuid` (conductor y vehículo viven en `internalControl`/`subcontractedControl` y en la placa) y `id` no equivale a `service_date`.
- **Criterios que se conservan** (hoy obligatorios): `company_uuid` (si hay), `service_date <= hoy`, al menos una ruta con `is_active`, coincidencia por `vehicle_uuid` o placa (interna o subcontratada) y, si el GPS trae `project_uuid`, igualdad de proyecto.
- **Orden de elección:** `service_date DESC, id DESC` (desempate determinista).
- **Consulta en dos fases:**
  - **Fase 1 (Candidatas ligeras):** Consulta acotada con `select(['id', 'uuid', 'service_date', 'project_uuid', 'type_of_control_sheet', 'parent_uuid', 'company_uuid'])` y precarga mínima indexada (`internalControl`, `internalControl.vehicle`, `subcontractedControl` con solo columnas de enlace y placas), con tope proporcional con holgura `max(100, count($ubicaciones) * 50)`. Se determina en memoria PHP la planilla óptima por ubicación.
  - **Fase 2 (Hidratación completa enfocada):** `with(relacionesDePlanilla())` ejecutado **únicamente** para los IDs únicos seleccionados en la Fase 1 (≤ número de conductores activos), eliminando N+1 y evitando procesar relaciones de planillas descartadas.
- **Relaciones:** se conservan las que usa `planillaDia()` (`routes` activas, `internalControl.vehicle`, `internalControl.thirdParty`, `subcontractedControl`, `project`), pero solo para las elegidas. No se pueden eliminar: `driver_name` y `vehicle_license_plate` son accesores que consultan relaciones y reintroducirían N+1.
- **Resiliencia:** Si ocurre un fallo en base de datos, se registra con `Logger::warning('LocationTrackingService: fallo al obtener ultimas rutas conocidas: ...')` y se retorna array vacío sin interrumpir la operación del monitor.

## Puntos a tener cuidado

> [!WARNING]
>
> 1. **No perder el fondo cartográfico.** Un polilínea sin calles es un retroceso. El proveedor externo sigue siendo el primario salvo que se configure `none`; solo se elimina la caché en disco. Consecuencia asumida: sin caché, cada PDF repite la descarga; compensar con el timeout corto existente y el respaldo GD inmediato.
> 2. **`ext-gd` en el hosting.** Si no está activa, `generarPngRespaldo` devuelve `null` y el PDF saldría sin mapa. Verificar con `extension_loaded('gd')` antes de desplegar.
> 3. **Contrato de `planillaDia`.** No quitar relaciones sin revisar sus accesores (N+1 silencioso).
> 4. **Orden por `service_date`, no por `id`.** Planillas cargadas con retraso tienen `id` mayor y fecha menor.
> 5. **`ONLY_FULL_GROUP_BY` de MySQL.** Si se usa agrupación, no mezclar `SELECT *` con `GROUP BY` sin subconsulta de unión.
> 6. **El `catch (\Throwable)` actual** de `ultimasRutasConocidas` oculta errores de SQL y los convierte en "sin respaldo". Conservarlo para no romper el monitor, pero registrar con `Logger::warning`.
> 7. **Fuga de coordenadas:** solo se evita con `provider=none`; con `osm`/`mapbox` las coordenadas siguen saliendo hacia terceros. Documentar en `.env.example`.
> 8. **Residuos en producción:** la carpeta `storage/app/route-maps` existente ya no se poda; limpiarla una vez a mano tras desplegar.
> 9. **Firmas protegidas:** `descargarImagen` es la costura de pruebas; cambiar su firma rompe `CacheMapasRutaTest` y cualquier subclase anónima. Las firmas de servicios deben seguir cumpliendo `FirmasDeClasesTest`.

## Testing Decisions

- **Costura real:** el servicio `RouteMapService::generar` y el endpoint `api.v1.tracking.active-drivers` (el monitor). Para el PDF, la prueba de `ResumenDiarioMapaTest`.
- **`tests/Feature/CacheMapasRutaTest.php`:** casos que comprueban que **no se crea ningún archivo** en disco (`route-maps`), que proveedor caído → respaldo GD en memoria, que `provider=none` no invoca `descargarImagen`, y que colecciones sin puntos o de un solo punto no tocan la red ni el disco.
- **`tests/Feature/MonitorFlotaTest.php`:**
  - Comprueba que (a) se elige la más reciente con rutas activas y proyecto coincidente, (b) una planilla futura o sin rutas se ignora, (c) el número de consultas es constante e independiente de la cantidad de conductores y del volumen de planillas históricas en base de datos.
  - Verifica la tolerancia a fallos: una excepción en la consulta de respaldo emite `Logger::warning` y retorna de forma segura sin abortar la respuesta del monitor.
- **Regresión:** suite completa validada con `php artisan test --filter="CacheMapasRutaTest|ResumenDiarioMapaTest|MonitorFlotaTest|FirmasDeClasesTest"`.

## Out of Scope

- Cambiar DomPDF o el diseño de las plantillas del reporte diario.
- Cambiar el protocolo de recepción de coordenadas de la app móvil.
- Retirar el proveedor externo por defecto (decisión de producto aparte; aquí solo se habilita `none`).

## Further Notes

- Resuelve la deuda de `SPEC-004` sobre el recorte arbitrario de 500 planillas, reemplazándolo por un tope proporcional y una selección determinista.
- Encaja con `SPEC-014` y `SPEC-016`: sin caché en disco ni en tabla `cache`.
