# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Comandos de Desarrollo Comunes

- **Ejecutar todos los tests:** `php artisan test`
- **Ejecutar una prueba unitaria o de característica específica:** `php artisan test --filter=NombreDeLaClaseOmetodo`
- **Ejecutar pruebas de rendimiento (grupo perf):** `php artisan test --group=perf`
- **Iniciar servidor de desarrollo y colas concurrentes:** `composer dev`
- **Instalación y configuración inicial:** `composer setup`
- **Verificar estado de migraciones:** `php artisan migrate:status`
- **Ejecutar migraciones:** `php artisan migrate`

## Arquitectura de Alto Nivel y Estructura del Código

Este repositorio contiene el backend de NOA (Laravel 12 con PHP ^8.5). El frontend Vue 3 reside en un directorio hermano (`C:\xampp\htdocs\tsb-design\noa-frontend-tsb`).

- **Patrón por Capas:** Las solicitudes fluyen a través de `Controller` (`app/Http/Controllers/Api/V1/<Módulo>`) → `FormRequest` (`Store*`/`Update*`) → `Service` (hereda de `App\Services\BaseService` para CRUD por UUID, paginación, búsqueda, transacciones y soft deletes) → `Model`.
- **Autorización y Seguridad:** El control de acceso se rige por permisos de Spatie (`spatie/laravel-permission`), middleware de autorización por recurso (`authz`), contexto multi-tenant mediante `company_uuid` y aislamiento estricto por afiliado/empresa.
- **Rutas y API:** Todo el backend está expuesto bajo prefijo `/api/v1` en `routes/api.php`.
- **Documentación de Dominio:** Guías detalladas y especificaciones técnicas en `docs/specs/` y `docs/adr/`.
- **Regla Absoluta:** Toda la comunicación humana y documentación interna debe realizarse estrictamente en **Español** (conforme a `AGENTS.md`).
