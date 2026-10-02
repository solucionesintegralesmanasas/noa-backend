# Cifras vigentes de rendimiento

Resumen con fecha, entorno y archivo fuente. Detalle y protocolo en
`docs/metrics/README.md` (backend) y `docs/metrics/lighthouse.md` (frontend).
Las cifras de laboratorio usan volumen sintético y **no son comparables** con
las de producción.

## Backend — banco de volumen (laboratorio)

Fuente: `docs/metrics/bench-local-20261002-101633.json` (MariaDB 10.4.32,
600 000 puntos + 60 000 alertas, mediana de 5 corridas).

| Consulta | Mediana | Índice |
|---|---:|---|
| Q1 monitor: último punto por conductor | 31 ms | `idx_dl_company_recorded` |
| Q2 último punto de un conductor | 1,4 ms | `idx_dl_recorded_at` |
| Q3 mapa del día por vehículo | 13 ms | `idx_dl_company_vehicle_recorded` |
| Q4 mapa del día por proyecto | 156 ms | `idx_dl_company_project_recorded` |
| Q5 historial del conductor (1 día) | 11 ms | `idx_dl_driver_company_recorded` |
| Q6 punto anterior (ingesta) | 0,6 ms | `idx_dl_driver_recorded` |
| Q7 alertas no leídas, página 1 | 1,6 ms | `idx_dla_company_read_created` |
| Q8 alertas de la empresa, página 1 | 1 ms | `idx_dla_company_created` |
| Escritura: 10 000 puntos | ~0,3 s por cada 1000 | — |

Las cifras del plan (§4.1 a §4.5) siguen siendo la referencia histórica por
usar otras fechas y vehículos de consulta.

## Frontend — peso del bundle (laboratorio, 2026-09-30)

Fuente: `perf-2026-09-30.json` del frontend (`buildId DdH84NX2`).

| Métrica | Valor |
|---|---:|
| `inicialJS` | 398 kB |
| `totalCSS` | 277 kB |
| `publicInicialJS` + `publicInicialCSS` | 634 + 1066 kB |
| `vendor-primevue` | 223 kB |

## Frontend — navegador en producción (2026-09-30, login)

Fuente: `lighthouse-apptransportessinbarreras-transportessinbarreras-com-{desktop,mobile}-2026-09-30.json`
(Lighthouse 12.8.2, 3 corridas).

| Dispositivo | Performance | Accesibilidad | LCP |
|---|---:|---:|---:|
| Desktop | 79 | 96 | 2,0 s |
| Móvil | 64 | 96 | 6,1 s |

## Presupuestos que bloquean (suite normal)

Fijados en pruebas, no en milisegundos: ingesta ≤ 7 consultas por punto;
monitor, dashboard, listados, historial, campana y reporte con conteo
constante al crecer el volumen; `EXPLAIN` sin recorridos completos en las
consultas críticas; exportación < 16 MB con 50 vehículos. Ver sección
"Pruebas de rendimiento" del `AGENTS.md`.
