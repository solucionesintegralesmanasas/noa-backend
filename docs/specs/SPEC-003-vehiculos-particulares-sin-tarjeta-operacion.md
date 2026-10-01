# SPEC-003 — Los vehículos particulares no tienen tarjeta de operación

**Estado:** IMPLEMENTADO (2026-10-01). Decisiones finales abajo; el resto del documento es el planteamiento original.
**Fecha:** 2026-10-01

## Decisiones finales (2026-10-01)

- **Regla:** un vehículo particular **no tiene pólizas RCC/RCE ni tarjeta de operación; sí SOAT y RTM**. Se corta de raíz: no se puede REGISTRAR (API 422 y UI), no solo ocultar.
- **Correo de vencimientos:** los particulares activos entran por SOAT y RTM (también los de afiliados registrados en la empresa); nunca pólizas ni tarjeta.
- **Cambio PÚBLICO → PARTICULAR:** permitido; RCC/RCE/tarjeta existentes se conservan como historial (editables sin cambiar de vehículo/tipo) pero no alertan ni salen por correo.
- **Pruebas:** `VehiculoParticularDocumentosTest` (backend, 13 casos) y `useDocumentWizard.test.js` (frontend).

## Problem Statement

Un vehículo registrado con tipo de servicio **PARTICULAR** no opera con tarjeta de operación (esa tarjeta es un requisito del servicio **PÚBLICO**). Hoy la plataforma ignora esa diferencia: el campo "tipo de servicio" se guarda y se muestra, pero ningún flujo lo usa para decidir si la tarjeta aplica.

Consecuencias que vive el usuario:

- Por cada vehículo particular aparece una alerta permanente "Falta registrar la Tarjeta de Operación" en las notificaciones y en el contador de la campana, sin forma de resolverla porque la tarjeta no existe ni debe existir.
- El correo diario de vencimientos decide si un vehículo es "propio" de la empresa mirando la empresa afiliada de su tarjeta de operación. Un vehículo particular, al no tener tarjeta, **queda fuera del correo por completo**: nadie recibe aviso del vencimiento de su SOAT, revisión técnico-mecánica ni pólizas.
- En el reporte de vehículos la columna de tarjeta aparece vacía y parece un faltante en vez de "no aplica".
- Los formularios de vehículo y de documentos empujan al usuario a registrar una tarjeta que no corresponde.

## Solution

La plataforma reconoce una regla de negocio única: **un vehículo PARTICULAR no requiere tarjeta de operación**. Con ella:

- No se generan alertas de tarjeta faltante para vehículos particulares.
- Los vehículos particulares de la empresa siguen recibiendo avisos de vencimiento de sus demás documentos (SOAT, RTM, pólizas) en notificaciones y en el correo.
- Los reportes muestran "No aplica" en lugar de un vacío.
- Los formularios dejan de pedir o sugerir la tarjeta para estos vehículos.
- Si el tipo de servicio de un vehículo cambia de PÚBLICO a PARTICULAR (o al revés), las alertas se ajustan solas.

## User Stories

1. Como administrador de la empresa, quiero que los vehículos particulares no generen la alerta "Falta registrar la Tarjeta de Operación", para que la campana de notificaciones solo muestre pendientes reales.
2. Como administrador, quiero que el contador de notificaciones no cuente tarjetas faltantes de vehículos particulares, para confiar en la cifra.
3. Como administrador, quiero seguir recibiendo en el correo diario los vencimientos de SOAT, RTM y pólizas de los vehículos particulares de mi empresa, para no dejar vencer documentos de esos vehículos.
4. Como destinatario del correo de vencimientos (transespecialessinbarreras@gmail.com), quiero que el correo no liste tarjetas de operación de vehículos particulares, para que el reporte sea correcto.
5. Como administrador, quiero que un vehículo particular que sí tuviera una tarjeta registrada por error histórico no genere una alerta bloqueante, para no perseguir un documento que no aplica.
6. Como administrador, quiero que al cambiar un vehículo de PÚBLICO a PARTICULAR desaparezcan sus alertas de tarjeta, para no limpiar nada a mano.
7. Como administrador, quiero que al cambiar un vehículo de PARTICULAR a PÚBLICO vuelva a exigirse la tarjeta, para que un vehículo que entra a servicio público quede controlado.
8. Como afiliado, quiero que mis vehículos particulares no aparezcan con tarjeta pendiente en mi panel, para no recibir avisos que no puedo atender.
9. Como conductor, quiero que mi panel no me muestre una tarjeta de operación faltante para un vehículo particular, para no confundirme.
10. Como analista, quiero que el reporte de vehículos muestre "No aplica" en la tarjeta de operación de un particular, para distinguirlo de un faltante real.
11. Como analista, quiero que los filtros del reporte por estado o vencimiento de tarjeta excluyan a los vehículos particulares, para que los totales de tarjetas sean correctos.
12. Como analista, quiero exportar el reporte a Excel y PDF con "No aplica" en esos casos, para compartirlo sin explicaciones.
13. Como usuario del formulario de vehículo, quiero que al elegir "Particular" no se me sugiera registrar una tarjeta de operación, para evitar errores.
14. Como usuario del asistente de documentos de vehículo, quiero que el paso o enlace de tarjeta de operación no aparezca para particulares, para ir directo a los documentos que sí aplican.
15. Como usuario del formulario de tarjetas de operación, quiero que el selector de vehículos no ofrezca vehículos particulares, para no registrar una tarjeta que no corresponde.
16. Como usuario de la ficha de un vehículo particular, quiero ver "Tarjeta de operación: no aplica" y no una sección vacía, para entender que no falta nada.
17. Como administrador, quiero que el reporte de vehículos con enlaces a documentos no ofrezca "crear tarjeta" para un particular, para no llevar al usuario a un formulario inútil.
18. Como administrador, quiero que las notificaciones ya guardadas de tarjeta faltante para vehículos particulares se resuelvan o desaparezcan en la siguiente sincronización, para no arrastrar alertas obsoletas.
19. Como auditor, quiero que la regla esté documentada y probada, para que un cambio futuro no vuelva a exigir tarjeta a particulares.
20. Como administrador de la empresa propia, quiero que la regla no cambie el comportamiento de ningún vehículo PÚBLICO, para no alterar los controles actuales de la flota de servicio público.

## Implementation Decisions

- **Regla única de dominio.** El modelo de vehículo expone una pregunta de negocio ("¿requiere tarjeta de operación?") que responde `no` cuando el tipo de servicio es PARTICULAR y `sí` en cualquier otro caso. Todos los flujos consumen esa pregunta; ninguno compara el texto `PARTICULAR` por su cuenta.
- **Notificaciones.** El cálculo de alertas de tarjeta de operación (vencidas, por vencer y faltantes) omite los vehículos que no requieren tarjeta. Si un vehículo particular tuviera una tarjeta registrada, **no se alerta** su vencimiento (la regla es "no aplica", no "opcional"). Las notificaciones persistidas de tipo tarjeta de operación de esos vehículos se desactivan en la sincronización.
- **Correo de vencimientos y "vehículo propio".** Hoy "propio" se decide por la empresa afiliada de la tarjeta, lo que excluye a los particulares. La determinación pasa a dos casos: vehículo que requiere tarjeta → criterio actual (tarjeta activa más reciente afiliada a un nombre de la empresa); vehículo que no la requiere → se considera propio de la empresa dueña del registro (su `company_uuid`). El correo incluye solo sus documentos; nunca una fila de tarjeta. **Decisión a confirmar con el negocio** (ver Notas): si en el sistema los afiliados pueden registrar vehículos particulares, este criterio los incluiría en el correo de la empresa.
- **Reporte de vehículos.** Las columnas de tarjeta (número y vencimiento) devuelven un valor explícito de "no aplica" para particulares; la fila lleva un indicador que el frontend, el Excel y el PDF traducen a "No aplica". Los filtros por tarjeta (empresa afiliada, número, estado, vencimiento) excluyen a los particulares. Los enlaces a documentos no incluyen el de tarjeta para ellos.
- **Dashboard.** Los resúmenes (administrador y conductor) que cuentan tarjetas faltantes o por vencer usan la misma regla.
- **Frontend.** Formulario y ficha de vehículo: la sección o el aviso de tarjeta se sustituye por "No aplica" cuando el tipo de servicio es particular, y se actualiza al cambiarlo en el formulario. Asistente de documentos: se omite el paso de tarjeta. Formulario de tarjetas: el selector de vehículos excluye a los particulares. Reporte de vehículos: muestra "No aplica" y no ofrece el enlace de creación.
- **Sin cambios de esquema.** No se añaden columnas ni migraciones: el tipo de servicio ya existe (`PUBLICO` por defecto, `PARTICULAR`).
- **Registro de tarjetas.** No se bloquea la API de creación de tarjetas para particulares en esta entrega; solo se deja de exigir y de ofrecer. Un rechazo explícito (422) queda para una iteración posterior.
- **Compatibilidad.** Cambiar el tipo de servicio recalcula las alertas en la siguiente consulta o sincronización; no hay migración de datos.

## Testing Decisions

- **Qué es una buena prueba aquí:** observa comportamiento externo (qué alertas devuelve el servicio de notificaciones, qué filas incluye el correo, qué devuelve el reporte), no cómo está implementada la regla.
- **Costura principal (única preferida):** los servicios de aplicación, con pruebas de Feature sobre la base de pruebas, como ya se hace en el repositorio. Un mismo conjunto de vehículos (uno público con tarjeta, uno público sin tarjeta, uno particular sin tarjeta, uno particular con tarjeta histórica) se ejecuta contra:
  1. el cálculo de notificaciones de tarjeta de operación (solo el público sin tarjeta genera "faltante"),
  2. el correo de vencimientos (el particular entra por sus documentos y no genera fila de tarjeta; los afiliados siguen excluidos),
  3. el reporte de vehículos (valor "no aplica" y filtros).
- **Pruebas complementarias:** cambio de tipo de servicio PÚBLICO ↔ PARTICULAR y su efecto en las alertas; la sincronización desactiva las notificaciones obsoletas.
- **Frontend:** pruebas de componente del formulario de vehículo (la sección se oculta al elegir "Particular") y del selector de tarjetas.
- **Antecedentes en el repositorio:** `DigestVencimientosTest` (estructura de datos, auxiliar de inserción y verificación del correo con `Mail::fake`), `OwnCompanyNormalizacionTest` y las pruebas de componentes de formularios del frontend.

## Out of Scope

- Cambiar las reglas de los vehículos PÚBLICOS.
- Bloquear con error la creación de una tarjeta para un vehículo particular (iteración posterior).
- Cambios en el FUEC (el extracto de contrato corresponde al servicio especial; no se modifica aquí).
- Nuevos tipos de servicio distintos de PÚBLICO y PARTICULAR.
- Migración o limpieza masiva de tarjetas ya registradas para particulares.

## Further Notes

- Estado actual verificado (2026-10-01): el tipo de servicio solo se muestra y se valida (`PUBLICO`/`PARTICULAR`); ningún servicio de notificaciones, correo, reportes ni dashboard lo consulta para eximir de tarjeta. El cálculo de "faltante" marca a todo vehículo activo sin tarjetas.
- **Preguntas abiertas para el negocio:**
  1. ¿Los afiliados pueden tener vehículos particulares? Si sí, ¿deben entrar al correo de la empresa propia o seguir excluidos?
  2. ¿Un particular con tarjeta registrada por error debe ignorarse (propuesto) o alertarse?
- Relacionado: correo de vencimientos documentado en el `AGENTS.md` del backend.
