# Cifras vigentes de rendimiento

Resumen con fecha, entorno y archivo fuente. Detalle y protocolo en
`docs/metrics/README.md` (backend) y `docs/metrics/lighthouse.md` (frontend).
Las cifras de laboratorio usan volumen sintético y **no son comparables** con
las de producción.

## Backend — banco de volumen (laboratorio)

Fuente: `docs/metrics/bench-local-20261005-162244.json` (ronda del 2026-10-05,
MariaDB 10.4.32, 600 000 puntos + 60 000 alertas, mediana de 5 corridas).

| Consulta | Mediana | Índice |
|---|---:|---|
| Q1 monitor: último punto por conductor | 34 ms | `idx_dl_company_recorded` |
| Q2 último punto de un conductor | 1,2 ms | `idx_dl_driver_recorded` |
| Q3 mapa del día por vehículo | 22 ms | `idx_dl_company_vehicle_recorded` |
| Q4 mapa del día por proyecto | 153 ms | `idx_dl_company_project_recorded` |
| Q5 historial del conductor (1 día) | 8,4 ms | `idx_dl_driver_company_recorded` |
| Q6 punto anterior (ingesta) | 0,8 ms | `idx_dl_driver_recorded` |
| Q7 alertas no leídas, página 1 | 0,8 ms | `idx_dla_company_read_created` |
| Q8 alertas de la empresa, página 1 | 0,9 ms | `idx_dla_company_created` |
| Escritura: 10 000 puntos | ~0,3 s por cada 1000 | — |

La ronda del 02-10 (`bench-local-20261002-101633.json`) queda como referencia
previa del mismo volumen. Las cifras del plan (§4.1 a §4.5) siguen siendo la
referencia histórica por usar otras fechas y vehículos de consulta.

## Backend — estado de combustible (laboratorio, 2026-10-22)

Módulo único `EstadoCombustible` (issue #8). Fuente:
`docs/metrics/fuel-status-20261022-165529-antes.json` (antes del refactor) y
`docs/metrics/fuel-status-20261022-170526-despues.json` (después, mismo arnés
y mismo sembrado). El presupuesto bloqueante vive en
`EstadoCombustibleConsultasTest`.

| Flota | Consultas ANTES | Consultas DESPUÉS | Mediana |
|---|---:|---:|---:|
| 2 vehículos | 5 | 5 | ~2–4 ms |
| 30 vehículos | 5 | 5 | ~5–8 ms |
| 90 vehículos (1 lote) | 5 | 5 | ~11–16 ms |
| 330 vehículos (4 lotes) | 20 | 20 | ~36–67 ms |

Regla del conteo: 5 consultas por lote de 100 vehículos (1 de vehículos + 4
voluminosas: tanqueos, preoperacionales, planillas, proyectos); constante
dentro del lote y crecimiento SOLO por lote. Memoria del flujo ~70–72 MB (pico
del arnés, informe y no bloqueante).

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
