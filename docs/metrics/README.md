# Historial de métricas de rendimiento (SPEC-004, capa 4)

Un solo lugar para responder "¿qué tan rápido es cada parte y dónde se midió?".
Tres tipos de archivo conviven aquí; **nunca se mezclan cifras de laboratorio
con las de producción** (ver reglas abajo).

## Tipos de archivo

| Tipo | Patrón | Lo genera | Contenido |
|---|---|---|---|
| Banco backend | `bench-<entorno>-<fecha>-<hora>.json` | `php artisan perf:bench run [--entorno=produccion]` | 8 consultas críticas: mediana de 5 corridas, plan de `EXPLAIN`, coste de escritura |
| Peso frontend | `perf-<fecha>.json` | `npm run perf:budget:local` (vive en el repo frontend) | Pesos de `dist/` y del `public/` que carga `index.html` |
| Navegador | `lighthouse-<env>-<device>-<fecha>.json` | `npm run perf:lighthouse` (vive en el repo frontend) | Performance, accesibilidad, LCP, TBT, CLS por página |

Los dos tipos de frontend viven en el repo `noa-frontend-tsb` (repos separados);
su esquema (`fecha` + `origen`/`env` + `metricas`/`paginas`) se mantiene alineado
con el del banco y `cifras-vigentes.md` los consolida en un solo documento.

`<entorno>` es `local` (volumen sintético con semilla fija) o `produccion`
(copia desechable de producción). El banco compara cada medición solo con la
anterior **del mismo entorno y volumen**; una diferencia mayor al umbral se
marca como informativa, nunca bloquea.

## Campos obligatorios

Todo archivo de métricas declara `fecha` y entorno (`entorno` en el banco;
`origen`/`env` en los del frontend). El banco además registra `motor`
(versión de MySQL/MariaDB), `volumen` (puntos y alertas) y `corridas`.

## Reglas

- Antes de medir en cualquier base: `php artisan migrate:status` (una base con
  migraciones pendientes dio cifras engañosas).
- El banco solo corre contra bases cuyo nombre termine en `_test`; al terminar,
  ejecutar `perf:bench limpiar` (la suite espera esas tablas vacías).
- Los tiempos varían entre corridas: comparar siempre medianas, no corridas
  sueltas, y no tratar el banco como bloqueo.
- `docs/metrics/cifras-vigentes.md` resume las cifras que valen hoy, con fecha,
  entorno y archivo fuente. Al registrar una medición nueva, actualizarlo.

## Protocolo de producción

1. Copia desechable de producción en una base `*_test` con el motor de
   producción; verificar `migrate:status` al día.
2. Backend: `perf:bench run --entorno=produccion --etiqueta="..."` (sin `seed`:
   los datos son los reales). Guardar el archivo `bench-produccion-*.json`.
3. Frontend: protocolo de `lighthouse.md` (Chrome incógnito, 3 corridas,
   mediana) contra la URL de producción y `perf:budget:prod`.
4. Actualizar `cifras-vigentes.md` con las cifras nuevas sin borrar la línea
   base de laboratorio.

### Medir antes y después de una migración de índices

Una migración de índices (ARQ-xxx) no se cierra con «pasó las pruebas»:

1. Con la copia desechable al día, `perf:bench run --entorno=produccion` **antes**
   de aplicar la migración; guardar el archivo.
2. Aplicar la migración (`php artisan migrate --force`).
3. `perf:bench run --entorno=produccion` **después**; el comando compara con la
   medición anterior del mismo entorno y marca las que empeoran.
4. Si alguna empeora, decidir con las medianas si se revierte; registrar la
   decisión y las cifras en `cifras-vigentes.md`.

### Arranque del login con CPU y red limitadas

Lighthouse no cubre bien el arranque en móvil. Para validar el login se usa el
arnés con CPU 4x y red 4G lenta del frontend (el que bajó el FCP de 2,6 a 1,5 s);
medir contra `vite preview` o producción, nunca contra `localhost:5173`, y
anotar el resultado junto a los `lighthouse-*.json`.
