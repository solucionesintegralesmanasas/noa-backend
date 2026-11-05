# SPEC-015: Renderizado en Memoria de Mapas de Ruta (Sin Caché en Disco) y Respaldo GPS Acotado

## Problem Statement

El servicio de generación de mapas estáticos para la exportación de PDFs diarios (`RouteMapService`) depende de llamadas externas mediante `curl_exec` a servidores públicos de OpenStreetMap (con un tiempo de espera de hasta 6 segundos). Para mitigar la lentitud y fragilidad de esta conexión de red, el servicio implementa una capa de caché de archivos PNG en disco (`storage/app/route-maps/*.png`) acompañada de un mecanismo probabilístico de poda de archivos obsoletos (con probabilidad de 1 en 20 en cada petición).
Asimismo, cuando el monitor satelital de conductores no encuentra ubicaciones GPS recientes (`LocationTrackingService::ultimasRutasConocidas`), ejecuta una consulta de respaldo que descarga hasta 500 planillas de control de entrega de servicio completas (`LIMIT 500`) junto con seis relaciones cada una, recorriendo y filtrando las colecciones en memoria PHP para identificar la última planilla de cada conductor.

## Solution

Eliminar por completo la dependencia de red externa y la caché de archivos en disco en `RouteMapService`. El servicio priorizará la captura real de la ruta generada por la aplicación (`ROUTE_MAP`) o generará un trazado vectorial/rasterizado directamente en memoria utilizando la extensión nativa GD de PHP (`generarPngRespaldo`), produciendo un mapa limpio y determinista en menos de 5 ms sin tocar el sistema de archivos ni la red.
En `ultimasRutasConocidas`, sustituir la descarga masiva de 500 planillas por una consulta SQL agrupada y optimizada con subconsulta `MAX(id)` indexada por conductor o vehículo, retornando exclusivamente la última planilla conocida de cada conductor activo directamente desde la base de datos.

## User Stories

1. Como Coordinador de Operaciones, quiero generar y descargar los PDFs consolidados de los servicios diarios en cuestión de segundos, sin fallos ni esperas causadas por servidores externos de mapas fuera de línea.
2. Como Administrador de Infraestructura, quiero que el directorio de almacenamiento local (`storage`) no acumule miles de imágenes temporales de mapas ni consuma espacio en disco innecesario en el hosting.
3. Como Monitor de Flota, quiero abrir el mapa en vivo de rastreo satelital y ver de inmediato la última posición o ruta conocida de cada conductor, sin que el servidor se congele procesando cientos de planillas históricas en PHP.
4. Como Auditor de Seguridad, quiero que la generación de reportes y documentos oficiales sea 100% determinista y autocontenida, sin fugas de coordenadas a APIs públicas de terceros.
5. Como Desarrollador del Backend, quiero que las pruebas unitarias de exportación de PDF y mapas se ejecuten de forma hermética y sin conexión a Internet en cualquier entorno de integración continua.

## Implementation Decisions

- **Eliminación del Almacenamiento en Disco `route-maps`:**
  - Se retirará la escritura de archivos en `storage/app/route-maps` y se suprimirá el algoritmo de poda aleatoria de archivos PNG.
- **Renderizado en Memoria GD Directo:**
  - `RouteMapService::obtenerMapaBase64` dibujará el polígono de coordenadas de la ruta utilizando funciones de la biblioteca GD (`imagecreatetruecolor`, `imageline`, `imagepng`) en un flujo de memoria temporal (`php://temp`), convirtiéndolo directamente a Base64 para incrustarlo en la plantilla HTML de DomPDF.
- **Optimización de Consulta de Respaldo GPS:**
  - Se reescribirá `ultimasRutasConocidas` para obtener la última planilla mediante una subconsulta indexada:
    ```sql
    SELECT sdcs.*
    FROM service_delivery_control_sheets sdcs
    INNER JOIN (
        SELECT third_party_uuid, MAX(id) as max_id
        FROM service_delivery_control_sheets
        WHERE company_uuid = ?
        GROUP BY third_party_uuid
    ) ultimos ON sdcs.id = ultimos.max_id;
    ```
  - Se eliminará la cláusula arbitraria `LIMIT 500` y la hidratación de relaciones irrelevantes para este propósito.

## Testing Decisions

- **Costura única y más alta posible:**
  - Pruebas HTTP contra la exportación de PDF de rutas (`GET /api/v1/tracking/daily-pdf`) y la consulta de rutas del monitor (`GET /api/v1/tracking/last-known-routes`).
- **Verificación de ausencia de archivos en disco:**
  - Comprobar que tras generar un PDF de ruta, el directorio de mapas no contenga archivos nuevos creados en disco.
- **Presupuesto de consultas del monitor de flota:**
  - Medir que `ultimasRutasConocidas` mantenga un conteo constante y acotado (≤ 3 consultas), sin importar que existan miles de planillas históricas en la base de datos.
- **Antecedentes de prueba en el repositorio:**
  - `CacheMapasRutaTest`, `ResumenDiarioMapaTest`, `MonitorFlotaTest`.

## Out of Scope

- Modificación de la biblioteca de renderizado PDF (DomPDF).
- Cambios en el diseño estético de las plantillas de reporte diario.
- Modificación del protocolo de recepción de coordenadas GPS emitidas por la aplicación móvil.

## Further Notes

- Esta especificación resuelve definitivamente la deuda técnica identificada en `SPEC-004` relativa al recorte arbitrario de 500 planillas en el monitoreo satelital.

