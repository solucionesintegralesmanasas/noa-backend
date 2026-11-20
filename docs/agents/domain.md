# Documentación de Dominio — NOA

Cómo deben consumir los agentes y sus skills locales la documentación de dominio de NOA al explorar o modificar el código.

## Lectura obligatoria antes de explorar

1. **`AGENTS.md`** en `noa-backend/AGENTS.md` y/o `noa-frontend-tsb/AGENTS.md` (especialmente la sección **Mapa de Contexto Vigente** al inicio de cada archivo).
2. **`CONTEXT.md`** en `noa-backend/CONTEXT.md` (glosario de términos de dominio acordados: Tanqueo, Solicitud de tanqueo, Estado de combustible, etc.).
3. **`noa-backend/docs/specs/SPEC-0xx-*.md`** y **`noa-backend/docs/adr/`** cuando se toque un módulo cubierto por una especificación o decisión arquitectónica.
4. **Setup agnóstico de agente:** `.opencode/skills/noa-agent-setup/SKILL.md` en `noa-backend` para configurar flujos de trabajo y skills locales adaptadas al entorno de cada desarrollador.

## Reglas de vocabulario y contratos

- **Usa el vocabulario del glosario y de las specs:** Nombra conceptos, pruebas, servicios y variables respetando los términos de `CONTEXT.md` y del Mapa de Contexto Vigente (`AlcanceDelActor`, `OwnCompany`, `ServiceDeliveryControlSheet`, `PdfService::conMarca`, `esHojaMembretadaCompleta`, `apiClient.global`).
- **Señala conflictos con Specs o ADRs:** Si un requerimiento contradice uno de los 12 Principios Arquitectónicos del backend, los 8 del frontend, una `SPEC-0xx` o un `ADR`, adviértelo explícitamente antes de escribir código:
  > _Contradice SPEC-0xx / Principio #N (...), ¿deseas abrir una nueva spec para cambiar este contrato?_
