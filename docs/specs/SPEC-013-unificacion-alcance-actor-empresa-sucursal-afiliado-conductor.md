# SPEC-013: Unificación del Alcance Multi-Tenant, Sede, Afiliado y Conductor (`AlcanceDelActor`)

## Problem Statement

Actualmente, las reglas de visibilidad y aislamiento de datos para los diferentes actores del sistema (Empresa, Sede Operativa, Afiliado y Conductor) se encuentran fragmentadas en al menos seis capas y servicios independientes:
1. El middleware de resolución de contexto (`SetCompanyContext`).
2. El trait general de modelos (`BelongsToCompany`).
3. El servicio base (`BaseService::aplicarFiltroAfiliado` y `aislarPorAfiliado`).
4. El servicio de vehículos (`VehicleService::getVehicleUuidsForConductor` y orden de evaluación de roles combinados).
5. El servicio de rastreo satelital (`AlcanceAfiliado`).
6. El servicio de alertas y notificaciones (`NotificationsService::applyCompanyFilter`).

Esta fragmentación provoca que en una sola petición HTTP se ejecuten repetidamente entre 4 y 8 consultas redundantes a tablas pivote (`owners_drivers`, `affiliate_drivers`, `project_driver_vehicles`), además de generar inconsistencias de seguridad (por ejemplo, la omisión de conductores compartidos en notificaciones de afiliados o diferencias en cómo un conductor ve sus vehículos asignados en rastreo vs. inspecciones).

## Solution

Crear un módulo profundo y cohesivo (`AlcanceDelActor` / `ActorScopeService`) que encapsule toda la lógica de resolución de permisos y visibilidad del actor autenticado para la petición en curso.
Este módulo resolverá una sola vez por petición la tupla de contexto `{empresa, sucursal, tercero, roles}` y expondrá métodos expresivos para aplicar los predicados de visibilidad directamente sobre cualquier instancia de consulta Eloquent o Query Builder (`scopeVehicles`, `scopeDrivers`, `scopeNotifications`, `scopeTracking`).
Se eliminarán las consultas redundantes de resolución en cascada y se garantizará que todos los módulos compartan las mismas definiciones estrictas de aislamiento para afiliados y conductores.

## User Stories

1. Como Afiliado, quiero ver única y exclusivamente mis vehículos, conductores y documentos asociados en todos los módulos de la plataforma (flota, tracking, notificaciones, inspecciones), sin que existan fugas ni discrepancias entre listados.
2. Como Conductor, quiero consultar con rapidez los vehículos que tengo asignados por proyectos o FUECs activos, para diligenciar mis inspecciones preoperacionales sin demoras al iniciar turno.
3. Como Coordinador de Sede Operativa, quiero que las consultas de mi sede se filtren de manera consistente y eficiente, sin ralentizar el servidor por comprobaciones repetitivas de pertenencia.
4. Como Administrador de Empresa, quiero que el sistema aplique el aislamiento de datos de forma hermética y uniforme, garantizando el cumplimiento normativo de protección de datos entre afiliados.
5. Como Desarrollador del Backend, quiero disponer de una sola costura clara y centralizada para aplicar filtros de seguridad por actor sobre cualquier consulta, reduciendo la complejidad ciclomática de `BaseService` y `VehicleService`.

## Implementation Decisions

- **Creación del Servicio Centralizado de Alcance (`AlcanceDelActor`):**
  - Se registrará como un servicio scoped en el contenedor de Laravel, resolviendo y memorizando en memoria de la petición el contexto del usuario autenticado en una única consulta inicial.
- **Predicados SQL Reutilizables:**
  - El servicio proveerá métodos declarativos:
    - `aplicarAVehiculos(Builder $query)`
    - `aplicarAConductores(Builder $query)`
    - `aplicarANotificaciones(Builder $query)`
    - `aplicarARastreo(Builder $query)`
  - Se asegurará la compatibilidad total con la tabla de conductores compartidos (`affiliate_drivers`) y asignaciones de proyectos (`project_driver_vehicles`).
- **Simplificación de `BaseService` y `VehicleService`:**
  - Se refactorizarán los métodos privados dispersos en `BaseService` para delegar la aplicación de scopes en el nuevo servicio centralizado.
- **Falla Cerrada (*Fail-Closed*):**
  - Ante cualquier ambigüedad en el contexto o falta de identificador de empresa para usuarios no privilegiados, el servicio forzará un predicado vacío (`WHERE 1 = 0`) o lanzará una excepción HTTP 403, impidiendo cualquier exposición accidental de datos globales.

## Testing Decisions

- **Costura única y más alta posible:**
  - Pruebas HTTP y de integración simulando las peticiones de los distintos roles:
    - Rol SUPERADMIN (acceso global o a la empresa seleccionada).
    - Rol ADMIN_EMPRESA (aislamiento estricto a su empresa).
    - Rol AFILIADO (aislamiento estricto a sus vehículos y conductores).
    - Rol CONDUCTOR (aislamiento estricto a sus vehículos asignados).
    - Rol Combinado AFILIADO + CONDUCTOR (prevalencia de la regla estricta de afiliado).
- **Verificación de presupuesto de consultas en resolución de contexto:**
  - Comprobar que el número de consultas para resolver el alcance del actor en una petición sea constante (exactamente 1 consulta) y no crezca con la cantidad de asignaciones del usuario.
- **Antecedentes de prueba en el repositorio:**
  - `AislamientoAfiliadoTest`, `AislamientoSucursalTest`, `TenantScopeTest`.

## Out of Scope

- Cambios en la interfaz gráfica de usuario o en el almacenamiento de tokens de autenticación en el frontend.
- Modificación de la estructura de roles y permisos RBAC en Spatie Permission.
- Modificación de los endpoints públicos no autenticados (rutas de firma pública o validación de FUEC).

## Further Notes

- Esta unificación soluciona definitivamente las discrepancias detectadas entre las consultas de tracking GPS y las consultas del listado de conductores de terceros.

