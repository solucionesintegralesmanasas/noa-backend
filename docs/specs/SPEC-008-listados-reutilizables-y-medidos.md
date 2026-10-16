# SPEC-008 — Listados reutilizables, consistentes y medidos

**Estado:** PROPUESTO (2026-10-06)
**Fecha:** 2026-10-06
**Alcance:** Frontend (`noa-frontend-tsb`). Los 33 listados que usan `professional-table` y los 50 archivos que repiten `badge-subtle`. Sin cambios de backend.
**Origen:** la revisión de Radicación TO (SPEC-007) mostró que el listado se sentía distinto del resto, y que el resto también se ve plano (indicadores de avance poco visibles, estados difíciles de distinguir). Decisión del ingeniero: si se hace un trabajo completo, se hace bien y componiendo piezas reutilizables, midiendo que no empeore el rendimiento actual.
**Label:** `ready-for-agent` (`gh` no está instalado: queda como archivo hasta publicarlo como issue).

## Problem Statement

1. **Cada listado copia el mismo bloque de estilos.** 33 vistas repiten dentro de su `<style scoped>` el CSS de la tabla, el paginador y las pastillas de estado; 50 archivos repiten `badge-subtle`. Para cambiar cómo se ve un listado hay que tocar decenas de archivos, y por eso unos se ven distintos de otros (Radicación TO era el más distinto).
2. **Se ve plano.** Información importante, como el avance de un trámite ("2/4"), llama poco la atención, y los estados dependen de pastillas pequeñas y poco contrastadas.
3. **No hay una forma única de hacer las cosas.** El `NoaBadge.vue` existente es el logo de NOA, no una pastilla de estado. Cada módulo inventa sus propias columnas de avance, estado y acciones.
4. **Cambiar el aspecto puede costar rendimiento sin que nadie lo note.** Hoy hay presupuesto de peso (`test:perf`) y Lighthouse, pero no hay medición del tiempo en que un listado muestra sus datos, y ningún criterio de "no empeorar" para una refactorización visual de este tamaño.

## Solution

Un conjunto pequeño de piezas compartidas que componen todos los listados, y un procedimiento de medición que decide si cada paso se queda o se revierte.

Desde la perspectiva del usuario: todos los listados se ven igual de cuidados, con los mismos estados, el mismo indicador de avance, el mismo paginador y las mismas acciones, y cargan igual de rápido o más. Desde la del equipo: crear o cambiar un listado es componer piezas y no copiar estilos.

## User Stories

1. Como usuario, quiero que todos los listados tengan la misma tabla, paginador y búsqueda, para no reaprender cada pantalla.
2. Como usuario, quiero ver el avance de un trámite como una barra con su número, para entenderlo de un vistazo.
3. Como usuario, quiero que los estados (completado, en proceso, pendiente, vencido, activo, inactivo) se distingan por color, ícono y texto, para no depender solo del color.
4. Como usuario con baja visión o daltonismo, quiero que el estado también se lea por el texto o el ícono, para no confundir un estado con otro.
5. Como usuario de lector de pantalla, quiero que la barra de avance, los estados y las acciones tengan roles y etiquetas en español, para poder usar el listado.
6. Como usuario, quiero que el selector de filas por página tenga siempre un valor seleccionado y coherente, para que no aparezca vacío.
7. Como usuario, quiero que la búsqueda, el contador de resultados y el botón de refrescar estén en el mismo sitio en todos los listados, para encontrarlos sin pensar.
8. Como usuario, quiero que los botones de acción de cada fila (ver, editar, eliminar) tengan el mismo estilo, tooltip y orden, para no equivocarme.
9. Como usuario, quiero que el listado muestre un estado de carga y un estado vacío coherentes, para saber si está cargando o si no hay datos.
10. Como usuario en el celular, quiero que el listado se pueda recorrer sin desbordar la pantalla, para usarlo desde la app.
11. Como usuario, quiero que el listado cargue igual de rápido o más que hoy, para no sentir que la plataforma empeoró.
12. Como persona del equipo, quiero un componente de tabla de listado que reciba datos, columnas y paginación del servidor, para crear un listado nuevo sin copiar estilos.
13. Como persona del equipo, quiero un componente de pastilla de estado con un mapa de estados reutilizable, para que "completado" sea verde en todos los módulos.
14. Como persona del equipo, quiero un componente de barra de avance con su cálculo de porcentaje, para usarlo en trámites, inspecciones y mantenimientos.
15. Como persona del equipo, quiero que la barra de búsqueda y la cabecera del listado sean un componente, para no copiar 40 líneas de HTML por vista.
16. Como persona del equipo, quiero que los estilos de listados vivan en un solo lugar y no entren en el arranque de la plataforma, para cambiar el diseño en un archivo sin pesar más al iniciar.
17. Como persona del equipo, quiero una convención escrita de composición en AGENTS.md, para que los listados nuevos nazcan reutilizando piezas.
18. Como persona del equipo, quiero medir peso, tiempo hasta datos y Lighthouse antes y después de cada lote, para saber qué cambio empeoró el rendimiento.
19. Como persona del equipo, quiero un tope automático de peso inicial y total, para que una regresión falle en `test:perf` y no llegue a producción.
20. Como persona del equipo, quiero migrar los listados por lotes con medición entre lotes, para poder detenerme si algo empeora.
21. Como persona del equipo, quiero que Radicación TO y Tarjetas de Operación sean los listados piloto, para validar las piezas antes de tocar los otros 31.
22. Como persona del equipo, quiero que se pueda excluir un listado con un caso muy particular (mapa, reporte) sin romper la convención, para no forzar piezas donde no encajan.

## Implementation Decisions

- **Piezas compartidas (composición):**
  - **Tabla de listado:** envuelve el `DataTable` de PrimeVue ya instalado (sin nueva dependencia) con paginación lazy del servidor, plantilla de paginador en español, opciones de filas por página y estados de carga y vacío. Recibe columnas por ranuras y la paginación por propiedades. El valor por defecto de filas por página debe ser siempre una de las opciones.
  - **Cabecera de listado + búsqueda:** componente que reúne el encabezado de página, el campo de búsqueda con limpieza, el contador de resultados y el botón de refrescar.
  - **Pastilla de estado:** componente que recibe un código de estado y lo traduce a color, ícono y texto con un mapa único y extensible (el nombre no puede ser `NoaBadge`, que ya es el logo).
  - **Barra de avance:** componente que recibe "hechos/total" o un porcentaje, con el cálculo y los roles de accesibilidad dentro. Reutiliza el cálculo de porcentaje que ya existe en el módulo de radicación.
  - **Botones de acción de fila:** componente con ver, editar y eliminar, con tooltip y `aria-label`, condicionados por permiso.
- **Estilos en un solo lugar:** un archivo de estilos de listados que contiene lo que hoy está copiado (tabla, paginador, pastillas). Se carga solo cuando se entra a un listado (parte del componente de tabla), **no** en `main.js`. Los bloques copiados de las 33 vistas se eliminan al migrar cada una.
- **Color:** estados en tres niveles consistentes con SPEC-007 — verde (completado/activo/vigente), azul (en proceso/actual), gris (pendiente/inactivo); el naranja se reserva para "editar" y la advertencia, y el rojo para vencido/error. El estado nunca depende solo del color.
- **Migración por lotes y reversible:** piloto (Radicación TO y Tarjetas de Operación), luego lotes de unos 5 listados por vez, cada uno con su medición. Un listado que no encaje (mapa, reporte con filtros especiales) puede quedar fuera, con el motivo anotado.
- **Compatibilidad:** las vistas no migradas siguen funcionando con sus estilos propios; no hay un "big bang".
- **Medición y criterios de aceptación (decisión del ingeniero: sin empeorar en demasía):**
  - **Línea base antes de tocar nada:** repetir `npm run test:perf` (peso de `dist/` y del `public/` que carga `index.html`), Lighthouse del dashboard y de un listado, y el tiempo hasta datos de 3 listados (Radicación, Tarjetas de Operación, Vehículos) con el arnés de interacciones (`perf-fuec.js` con `--route` y `--ready`), mediana de corridas cálidas contra `vite preview`, nunca contra `localhost:5173`. Se guarda en `docs/metrics/` con la fecha y la etiqueta `antes`.
  - **Topes tras cada lote:**
    - JS inicial y CSS inicial: sin aumento (el CSS de listados no entra al arranque).
    - CSS total: no sube; se espera que baje al quitar duplicados.
    - JS total: sube a lo sumo 3 kB en todo el proyecto por las piezas nuevas.
    - Tiempo hasta datos de los 3 listados medidos: la mediana no empeora más de 5 %.
    - Lighthouse del listado: sin bajar de puntaje en escritorio ni móvil.
    - Accesibilidad: `test:a11y` sin incumplimientos nuevos.
  - **Si un tope se rompe**, el lote se revierte o se corrige antes de seguir; no se acumulan regresiones.
  - **Presupuesto automático:** tras el piloto, fijar en `perf-budget.js` los topes `publicInicialJS`/`publicInicialCSS` y un tope del CSS compartido de listados con el número medido (hoy "aún sin tope" según el propio script), de modo que una regresión falle con `test:perf:strict`.
- **Convención en AGENTS.md:** una sección de composición y reutilización de listados y la entrada de este spec en la lista de documentos.

## Testing Decisions

- **Qué es una buena prueba aquí:** parte de lo que el usuario ve y hace (porcentaje de la barra, texto y rol del estado, valor del selector de filas, botones por permiso) y no de colores exactos ni de nombres de clases CSS.
- **Punto de prueba principal (único):** pruebas de componente con vitest + jsdom + `@vue/test-utils` (`// @vitest-environment jsdom` en la primera línea) para cada pieza compartida y para un listado piloto montado de punta a punta con datos simulados: barra de avance (50 %, 100 %, valores inválidos, `role="progressbar"`), pastilla de estado (cada código conocido y uno desconocido), tabla (filas por página con valor por defecto presente, evento de página, estado vacío y de carga) y botones de acción (ocultos sin permiso).
- **Rendimiento (segundo punto, ya existente):** `npm run test:perf` y `test:perf:strict` como control de peso; `docs/metrics/lighthouse.md` y el arnés de interacciones para tiempos. No se añade una herramienta de capturas visuales: no existe en el proyecto y es un trabajo aparte.
- **Antecedentes:** `normalizarLinea.test.js` y `radicacion.store.test.js` (pruebas de lógica pura y de store del módulo de radicación), `useDocumentWizard.test.js` (composable), el protocolo de `docs/metrics/` y las mediciones de SPEC-004 (números, no milisegundos).

## Out of Scope

- Cambiar el diseño de formularios, el wizard, el dashboard o el mapa.
- Rediseñar el tema global (`theme.min.css`, variables de color de la marca) o purgar CSS no relacionado con listados.
- Nueva librería de componentes o de gráficos.
- Orden por columna en radicación (el backend no lo soporta).
- Pruebas de capturas visuales automáticas.
- Cambios de backend.

## Further Notes

- **Dependencia:** el piloto de Radicación TO ya existe (SPEC-007); su listado, su barra de avance y su store sirven como primer caso para extraer las piezas.
- **No hay base medida todavía:** las cifras de AGENTS.md (2026-09-29) son de antes de varias entregas; la línea base debe repetirse el día en que empiece el trabajo.
- **Medir en local con cuidado:** cada petición tarda ~600 ms en local por el arranque de Apache; comparar siempre antes/después en la misma máquina y con el mismo usuario.
- **Riesgo conocido:** las clases de PrimeVue (`p-datatable`, `p-paginator`) cambian de nombre entre versiones; los estilos compartidos deben apoyarse en la clase propia `professional-table` y no multiplicar selectores internos.
