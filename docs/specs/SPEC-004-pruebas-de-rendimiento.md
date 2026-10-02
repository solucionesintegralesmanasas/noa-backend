# SPEC-004 — Pruebas de rendimiento reproducibles

**Estado:** IMPLEMENTADO (2026-10-02: capas 1-4; ver "Cierre" al final). Origen: BORRADOR (quedó como archivo porque `gh` no está instalado en esta máquina).
**Fecha:** 2026-10-02
**Relacionado:** `PLAN-ARQUITECTURA-RENDIMIENTO-NOA.md` (ARQ-001, ARQ-014, ARQ-015), `docs/metrics/lighthouse.md` del frontend.

## Problem Statement

El rendimiento de NOA se ha mejorado con trabajo real y medido (índices que bajan el monitor GPS de 948 ms a 36 ms, ingesta de 16 a 7 consultas por punto, monitor de flota sin N+1, caché de mapas, arranque del frontend de 2,6 s a 1,5 s en móvil), pero **nada impide que se pierda sin que nadie se dé cuenta**:

- Los bancos de medición que dieron esas cifras (carga de 600 000 puntos GPS, `EXPLAIN`, tiempos por consulta, coste de escritura) viven en una carpeta temporal de la sesión de trabajo. Si se borra, la evidencia no se puede repetir y la próxima persona parte de cero.
- Solo tres áreas tienen una prueba que "falla si el rendimiento empeora" (consultas por punto GPS, consultas del monitor de flota, existencia de los índices). Dashboard, listados de flota, historial GPS, notificaciones, reportes y PDF no tienen ninguna.
- Los índices se verifican por su existencia, pero no que las consultas críticas **sigan usándolos**: un cambio en la consulta puede dejar un índice sin uso y nadie lo ve hasta que producción se pone lenta.
- Las mediciones de producción dependen de pasos manuales (Lighthouse a mano, `EXPLAIN` a mano) y los números locales se confunden con los reales. Ya ocurrió: la base local no tenía 6 migraciones y daba números engañosos.
- No hay una forma sencilla de decidir "esto se despliega" o "esto empeora el sistema" con datos, ni un lugar único donde ver la historia de las cifras.

Quien lo sufre: el equipo de desarrollo (pierde tiempo reconstruyendo mediciones y se arriesga a regresiones) y, al final, los usuarios (pantallas lentas, mapas que tardan, PDF que esperan red).

## Solution

Un conjunto de pruebas de rendimiento **reproducibles, versionadas y de cuatro capas**, que cualquier persona del equipo pueda ejecutar con un comando y cuyos resultados queden registrados:

1. **Presupuestos de consultas** (rápidos, determinísticos, corren con la suite normal): cada flujo crítico declara cuántas consultas puede hacer como máximo y que ese número no crece con el volumen (conductores, geocercas, vehículos, planillas).
2. **Uso de índices**: las consultas críticas se verifican con `EXPLAIN` y fallan si dejan de usar el índice esperado o recorren la tabla completa.
3. **Banco de volumen reproducible**: un comando que siembra una base desechable con un volumen realista (cientos de miles de puntos GPS, alertas, planillas), ejecuta las consultas críticas, mide tiempo y plan, y guarda el resultado comparable con el anterior. No bloquea el despliegue por sí solo; sirve para decidir y para tener historia.
4. **Frontend y producción**: los presupuestos de peso y el bucle de Lighthouse ya existentes quedan integrados al mismo proceso y a un protocolo claro para medir en producción (sin mezclarlo con números locales).

El resultado para el usuario del sistema: lo que hoy es rápido sigue siéndolo, y cuando algo se degrada se detecta antes de llegar a producción.

## User Stories

1. Como desarrollador, quiero ejecutar un solo comando que corra todas las pruebas de rendimiento del backend, para saber en minutos si mi cambio empeoró algo.
2. Como desarrollador, quiero que las pruebas de presupuesto de consultas corran junto con la suite normal, para que una regresión de N+1 rompa la integración continua y no llegue a producción.
3. Como desarrollador, quiero que el número máximo de consultas de cada flujo crítico esté escrito en la prueba, para que cambiarlo sea una decisión consciente y revisable.
4. Como desarrollador, quiero que las pruebas comprueben que el número de consultas no crece al aumentar los conductores, geocercas o vehículos, para detectar N+1 aunque el total absoluto parezca pequeño.
5. Como desarrollador, quiero presupuestos de consultas para el dashboard (administrador y conductor), para completar ARQ-014 con una red de seguridad.
6. Como desarrollador, quiero presupuestos de consultas para los listados paginados de flota (vehículos, documentos, tarjetas, terceros), para que agregar una relación no multiplique las consultas por fila.
7. Como desarrollador, quiero presupuestos para el historial y las estadísticas GPS, para que el rango y la paginación sigan acotando el trabajo.
8. Como desarrollador, quiero presupuestos para la sincronización de notificaciones y el conteo de la campana, que se ejecutan en cada carga de pantalla.
9. Como desarrollador, quiero que el reporte de vehículos y sus exportaciones tengan un tope de consultas y de memoria, para que crecer la flota no lo rompa.
10. Como desarrollador, quiero una prueba por cada índice compuesto crítico que ejecute `EXPLAIN` y falle si la consulta deja de usar ese índice, para que el índice no quede huérfano tras un cambio de consulta.
11. Como desarrollador, quiero que la prueba de `EXPLAIN` distinga "usa el índice" de "recorre toda la tabla", para entender la causa cuando falle.
12. Como desarrollador, quiero un comando de banco de volumen que siembre una base desechable con datos realistas y reproducibles (semilla fija), para obtener las mismas cifras en cualquier máquina.
13. Como desarrollador, quiero que el banco rechace ejecutarse contra una base que no sea la desechable de pruebas, para no borrar datos reales por error.
14. Como desarrollador, quiero que el banco mida cada consulta con varias corridas y reporte la mediana, para que las cifras no dependan de una corrida con suerte.
15. Como desarrollador, quiero que el banco mida también el coste de escritura de la ingesta GPS, para comprobar que un índice nuevo no frena la recepción de puntos.
16. Como desarrollador, quiero que el resultado del banco se guarde como archivo comparable (fecha, volumen, consulta, tiempo, plan), para ver la tendencia y detectar degradaciones graduales.
17. Como desarrollador, quiero que el banco compare contra la última medición y marque las consultas que empeoraron más de un umbral, para que una regresión de tiempo no pase desapercibida.
18. Como desarrollador, quiero que las pruebas de tiempo NO sean parte del bloqueo del despliegue, para no tener falsos fallos por una máquina lenta o ocupada.
19. Como líder técnico, quiero saber qué pruebas bloquean (consultas, uso de índices) y cuáles informan (tiempos), para confiar en que un fallo es real.
20. Como desarrollador, quiero que el banco documente el volumen sintético usado y sus límites, para no confundir un resultado de laboratorio con uno de producción.
21. Como administrador de la plataforma, quiero un protocolo claro para repetir las mediciones con datos de producción, para validar las mejoras donde importa.
22. Como desarrollador, quiero que el protocolo de producción incluya antes y después de cada migración de índices, para medir el efecto real y decidir si se revierte.
23. Como desarrollador, quiero que el protocolo incluya verificar que la base medida tiene todas las migraciones aplicadas, para evitar cifras engañosas por una base desactualizada.
24. Como desarrollador frontend, quiero que los presupuestos de peso (`inicialJS`, `totalCSS` y el `public/` que carga el HTML) formen parte del mismo informe, para ver backend y frontend juntos.
25. Como desarrollador frontend, quiero que el bucle de Lighthouse registre en el mismo formato de historial (fecha, entorno, dispositivo, página, métricas), para compararlo con el tiempo.
26. Como desarrollador, quiero medir el arranque del login con CPU y red limitadas (el arnés usado en la mejora del arranque), para validar las mejoras de carga sin depender de Lighthouse.
27. Como desarrollador, quiero que el informe diferencie claramente "entorno local" de "producción", para no mezclar cifras.
28. Como líder técnico, quiero un documento único con las cifras vigentes y su fecha, para responder "¿qué tan rápido es el monitor?" sin buscar en el historial de conversaciones.
29. Como desarrollador, quiero que la caché de mapas del PDF tenga una prueba de comportamiento (mismo trazado no vuelve a descargar), para que el ahorro de red no desaparezca sin avisar.
30. Como desarrollador, quiero que cada mejora futura del plan de rendimiento (ARQ-xxx) incluya su prueba de presupuesto, para que la regresión sea imposible por diseño.
31. Como administrador del hosting compartido, quiero que ninguna de estas pruebas requiera colas, workers ni servicios externos, para que funcionen en nuestro entorno.
32. Como desarrollador, quiero que los fallos expliquen qué consulta cambió y cuántas hizo contra el presupuesto, para corregirlos sin depurar a ciegas.

## Implementation Decisions

- **Cuatro capas, con distinto efecto sobre el despliegue.**
  - *Presupuestos de consultas* y *uso de índices*: pruebas de la suite normal. **Bloquean.**
  - *Banco de volumen*: comando aparte con salida comparable. **Informa**, no bloquea.
  - *Frontend y producción*: protocolos y herramientas ya existentes (presupuesto de peso, bucle de Lighthouse, arnés de arranque), integradas al mismo historial.
- **Presupuesto de consultas como concepto único.** Un auxiliar de pruebas cuenta las consultas de un bloque de código y compara contra un máximo declarado; expone también "mismo conteo con 1 elemento y con N" para detectar crecimiento. Es la generalización de lo que ya hacen las pruebas del monitor de flota y de la ingesta GPS.
- **Fijar el número, no el tiempo.** Una prueba bloqueante nunca depende de milisegundos: depende del número de consultas, del plan de `EXPLAIN` y de la memoria acotada. El tiempo se mide solo en el banco informativo.
- **Verificación de índices por plan.** Para cada consulta crítica (monitor GPS, mapa por vehículo y proyecto, historial por conductor, punto anterior de la ingesta, alertas, planillas por empresa y fecha) existe una comprobación del plan que exige el índice esperado y rechaza el recorrido completo de la tabla. Se ejecuta con datos mínimos pero suficientes para que el optimizador elija un plan representativo.
- **Banco de volumen reproducible.** Comando de consola que: (1) valida que la conexión apunta a la base desechable de pruebas; (2) siembra con semilla fija un volumen configurable (por defecto 600 000 puntos GPS, 60 000 alertas, decenas de conductores/vehículos/proyectos y varias empresas con el 30 % del volumen en la propia); (3) ejecuta el conjunto de consultas críticas con varias corridas y mediana; (4) mide el coste de escritura de la ingesta; (5) guarda un archivo de resultado comparable y lo compara con el anterior. El modelo de datos sintético imita la distribución de producción (varias empresas, rangos de 30 días).
- **Historial de métricas unificado.** Los resultados del banco y de Lighthouse/presupuesto se guardan en el mismo directorio y esquema de archivo con fecha, entorno (local o producción), volumen y versión de la aplicación. Un documento único resume las cifras vigentes.
- **Protocolo de medición en producción.** Pasos fijos: confirmar migraciones al día, medir antes, aplicar el cambio, medir después, registrar y decidir; nunca comparar cifras locales con las de producción. Incluye el uso del arnés de CPU/red limitadas para el arranque del login.
- **Alcance de los presupuestos iniciales.** Se cubren primero los flujos ya optimizados (monitor de flota, ingesta GPS) para consolidarlos como referencia, y luego, por prioridad del plan: dashboard (ARQ-014), listados de flota, historial/estadísticas GPS, notificaciones, reporte de vehículos y exportaciones.
- **Regla de proceso.** Cada nueva mejora del plan de rendimiento debe llegar con su presupuesto de consultas o su comprobación de índice; una mejora sin prueba se considera incompleta.
- **Sin cambios de esquema ni de contrato de API.** Todo es herramienta de prueba y documentación; no modifica el comportamiento de la aplicación.
- **Compatibilidad con el hosting.** Sin colas, workers ni servicios externos; el banco corre en una máquina de desarrollo o integración.

## Testing Decisions

- **Qué hace buena a una prueba de rendimiento aquí:** observa comportamiento externo medible y estable (cuántas consultas, qué plan de ejecución, cuánta memoria), no detalles internos ni tiempos de reloj. Una prueba bloqueante que falla por una máquina lenta es una mala prueba.
- **Costura preferida (la más alta posible):** los servicios de aplicación y, donde el flujo vive en el controlador, la petición HTTP completa con autenticación (que incluye middlewares como la autorización por recurso). Se evita probar métodos privados.
- **Qué se prueba:**
  - Presupuestos de consultas de los flujos críticos y su independencia del volumen.
  - Uso de índices por consulta crítica mediante el plan.
  - Comportamiento de la caché de mapas del PDF (ya existe) como ejemplo de "ahorro verificado".
  - El propio banco: que rechaza bases no desechables, que es reproducible con la misma semilla y que genera el archivo de resultado con el formato esperado.
- **Antecedentes en el repositorio:** `MonitorFlotaTest` (conteo de consultas independiente del número de conductores), `IngestaGpsTest` (tope de consultas por punto y de escrituras de auditoría), `IndicesTrackingTest` (existencia y reversibilidad de índices), `CacheMapasRutaTest` (red sustituida por un doble), el presupuesto de peso y el bucle de Lighthouse del frontend.
- **Datos de prueba:** se insertan con un auxiliar que rellena automáticamente las columnas obligatorias de cada tabla, ya usado en varias pruebas, para no acoplarlas a campos irrelevantes.
- **Estabilidad:** las pruebas bloqueantes deben ser deterministas; el banco documenta su variabilidad y compara por medianas y umbrales amplios.

## Out of Scope

- Pruebas de carga contra producción o con usuarios simultáneos reales.
- Monitoreo continuo de aplicación (APM), alertas de producción y observabilidad.
- Colas, workers o procesamiento asíncrono (el hosting compartido no los tiene).
- Optimizar nuevas consultas: este spec solo crea la red de seguridad y la medición.
- Pruebas de rendimiento del cliente nativo (Capacitor).
- Cambios en la política de retención de datos GPS.

## Cierre (2026-10-02)

- **Capa 1:** presupuestos de consultas en la suite (`PresupuestoConsultas`, `DashboardConsultasTest`, `ListadosFlotaConsultasTest`, `HistorialGpsConsultasTest`, `NotificacionesConsultasTest`, `ReporteVehiculosConsultasTest`).
- **Capa 2:** `IndicesEnUsoTest` (plan de `EXPLAIN` de las consultas reales).
- **Capa 3:** comando versionado `perf:bench seed|run|limpiar` con semilla fija, guard de base desechable y archivo comparable; `run` acepta `--entorno=produccion` y cada entorno compara solo con su historial.
- **Capa 4:** `docs/metrics/README.md` (esquema único, reglas y protocolo de producción) y `docs/metrics/cifras-vigentes.md` (documento único de cifras).
- Queda fuera del spec y como deuda con red de seguridad: `syncNotifications` (24 consultas por vehículo) y el respaldo del monitor (solo `LIMIT 500`); cada mejora debe bajar el tope de su prueba. La medición con motor y datos de producción sigue pendiente (protocolo en `docs/metrics/README.md`).

## Further Notes

- **Cifras de referencia vigentes (laboratorio, 2026-10-01):** con 600 000 puntos GPS en una base desechable, el monitor GPS pasó de 948 ms a 36 ms, el mapa del día por vehículo de 973 ms a 3,5 ms y por proyecto de 936 ms a 117 ms; la ingesta de un punto, de 16 a 7 consultas (independiente del número de geocercas); el monitor de flota, a un número constante de consultas. Detalle en el plan (§4.1 a §4.5).
- **Evidencia rescatada (2026-10-02):** el banco que produjo esas cifras vivía en una carpeta temporal; ahora es el comando versionado `perf:bench`.
- **Lección registrada:** una base de desarrollo con migraciones pendientes da cifras engañosas; el protocolo debe verificar el estado de migraciones antes de medir.
- **Pendiente de datos reales:** repetir las mediciones con volúmenes de producción y confirmar la política de retención GPS (ver §10 y §11 del plan).
