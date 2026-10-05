# SPEC-006 — La configuración del sistema con imágenes no debe romper su consulta

**Estado:** IMPLEMENTADO (2026-10-05). `SystemConfiguration::$hidden = ['media']` con URLs seguras, `CompanyPathGenerator::modeloDuenio()` sin colgar el dueño del archivo, carga de configuración no bloqueante en `FuecFormView` y `ConfiguracionConImagenesTest` (5 casos; 18 consultas con 1 archivo y 30 con 3, antes ~43.000). Reproducido antes del arreglo: la petición agotaba 512 MB. Pendiente: verificación manual del paso 1 del FUEC con membrete cargado.
**Fecha:** 2026-10-05

## Problem Statement

Cuando la configuración del sistema de una empresa tiene imágenes cargadas (logo del ministerio, logo de la superintendencia o membrete), pedir esa configuración al API deja de responder: la petición consume toda la memoria del servidor (más de 500 MB en ~43.000 consultas encadenadas) y el proceso muere. En local el worker de Apache cae; en producción equivale a un error 500 o a una espera hasta timeout.

Consecuencias que vive el usuario:

- Crear un FUEC nuevo tarda muchísimo en mostrar el paso 1 (incluida la selección del vehículo), sobre todo la primera vez: el formulario espera a esa configuración antes de pintar, así que todo el paso queda bloqueado detrás de una petición que nunca responde bien.
- Cualquier otra pantalla que lea la configuración de la empresa con imágenes sufre la misma espera.
- Sin imágenes cargadas todo funciona: el fallo solo aparece cuando hay al menos un archivo, por eso costó asociarlo (empezó a notarse tras subir el membrete).
- El volumen de datos es inocente (el combo de vehículos pagina de 15 en 15): el problema no es cantidad de vehículos sino esta serialización.

## Solution

Pedir la configuración del sistema responde 200 con las mismas URLs de imágenes de siempre, tenga o no archivos cargados, sin recursión y con un número pequeño y constante de consultas. El formulario de FUEC deja de esperar a una petición condenada y el paso 1 (vehículo incluido) pinta en cuanto llegan los catálogos.

## User Stories

1. Como auxiliar que crea un FUEC, quiero que el paso 1 (selección de vehículo) aparezca en segundos aunque la empresa tenga membrete y logos cargados, para no esperar minutos frente a un cargador.
2. Como auxiliar, quiero que la primera creación del día no sea dramáticamente más lenta que las siguientes por culpa de esta petición, para planificar mi trabajo.
3. Como administrador de la empresa, quiero subir y cambiar logos y membrete sin miedo a romper las pantallas que leen la configuración, para mantener la imagen de mis documentos.
4. Como administrador, quiero que la configuración con imágenes siga exponiendo sus tres URLs (ministerio, superintendencia, membrete) igual que hoy, para que PDFs y vistas no pierdan las imágenes.
5. Como SUPERADMIN, quiero que pedir la configuración de cualquier empresa responda 200 tenga o no archivos, para diagnosticar sin caídas del servidor.
6. Como responsable de operaciones, quiero que un fallo al leer una imagen (archivo borrado del disco, colección vacía) degrade a URL vacía y no tumbe la petición, para que un archivo faltante no bloquee la operación.
7. Como desarrollador, quiero una prueba que fije el número de consultas de este endpoint con imágenes adjuntas, para que una regresión falle de inmediato en `php artisan test` en vez de descubrirse en producción.
8. Como desarrollador, quiero que el generador de rutas de media no cargue el modelo dueño dentro de la serialización de un archivo, para que ningún otro endpoint con adjuntos herede este ciclo.
9. Como usuario del formulario de FUEC, quiero que si la configuración tarda o falla, el selector de vehículo igual se pinte con los vehículos ya cargados, para no quedar bloqueado por un dato secundario.
10. Como auditor, quiero que la respuesta de configuración no incluya el modelo dueño anidado dentro de cada archivo (solo sus URLs), para recibir un payload predecible y liviano.

## Implementation Decisions

- La causa es una recursión mutua al serializar: la configuración incluye sus archivos, calcular la URL de un archivo carga el modelo dueño para armar la ruta por NIT, y ese dueño vuelve a incluir sus archivos. Cada vuelta usa instancias nuevas, así que el guard antirrecursión por instancia no la frena. Ocurre solo con al menos un archivo.
- El corte se hace por los dos lados (cualquiera de los dos basta; se hacen ambos por defensa en profundidad):
  - La serialización de la configuración no anida la relación de archivos; conserva los escalares y las tres URLs de imagen ya existentes, que es lo que consumen el frontend y los PDFs.
  - El generador de rutas por NIT no deja el modelo dueño cargado en el archivo que se está serializando; resuelve el NIT sin hidratar modelos dentro del grafo de respuesta.
- Sin cambios de contrato: mismos campos escalares, mismas tres URLs, mismos códigos (200 con datos, 200 con `null` si no hay configuración). El frontend no necesita ajustes para este fix.
- Sin cambios de esquema ni migraciones: el almacenamiento por NIT y las colecciones (un solo archivo) se mantienen.
- El formulario de FUEC no se toca en este spec salvo lo necesario: si la configuración falla o tarda, el paso 1 ya no queda bloqueado por ella (el detalle de paralelizar catálogos y quitar la espera fija queda para el backlog de rendimiento, no para este fix).
- Comportamiento ante archivo faltante en disco o colección vacía: URL vacía, petición 200. Nunca una excepción por un adjunto.

## Testing Decisions

- Qué es una buena prueba aquí: la que reproduce el patrón real del fallo (pedir la configuración de una empresa **con archivo adjunto**) y afirma comportamiento externo (código 200, URLs presentes, sin modelo dueño anidado), no detalles internos del generador de rutas.
- Costuras (decisión: las relevantes, dos):
  - Principal (bloquea, seam más alto): prueba Feature contra el endpoint de configuración por empresa con un archivo adjunto (membrete), que exige 200 y un presupuesto de consultas pequeño y constante medido con el auxiliar compartido de presupuestos (patrón de las pruebas de rendimiento: mismo flujo con 1 y con N elementos, tope absoluto). Sin el fix, esta prueba agota la memoria en vez de pasar.
  - Apoyo (unidad al modelo): serializar una configuración con archivo no incluye la relación de archivos ni el modelo dueño, y conserva las tres URLs.
- Prior art en el repo: el auxiliar de presupuestos de consultas y sembrado compartido, las pruebas de presupuestos de listados y reportes, y el test de regresión de contratos-configuración como modelo de test de regresión con nombre de culpable.
- El fix no está completo sin su presupuesto de consultas; el tope se fija en lo medido tras el fix.
- Verificación manual: crear un FUEC con la empresa teniendo membrete cargado y confirmar que el paso 1 pinta sin espera anómala; quitar el archivo y repetir.

## Out of Scope

- Paralelizar la carga de catálogos del formulario de FUEC, quitar su espera fija de 400 ms o cachear catálogos: backlog de rendimiento, specs aparte.
- Purga del CSS del tema, peso del JS inicial y cualquier otro hallazgo de los reportes Lighthouse: deuda conocida del plan de rendimiento.
- Cambiar la estructura de carpetas por NIT, los discos remotos (S3/Drive) o las colecciones de un solo archivo.
- Aislamiento de afiliado (SPEC-005) y cualquier cambio de autorización o multi-tenant.
- Backfill o migración de datos de archivos existentes.

## Further Notes

- Diagnóstico de respaldo: arnés de tiempos por endpoint (todos 0,24–0,60 s salvo configuración: 6 s y muerte del worker), query-log con ~43.000 consultas en ciclo archivo→configuración→empresa, y stack que muestra el generador de rutas cargando el modelo dueño dentro de `getUrl` durante la serialización. Loop de reproducción: serializar la configuración de la empresa local (tiene un membrete) agota 512 MB; sin archivos, la misma serialización es inocente.
- `gh` no está instalado en este entorno: este spec queda como archivo hasta publicarlo como issue; al publicarlo, aplicar la etiqueta de triage `ready-for-agent`.
