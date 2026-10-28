# Glosario de dominio — NOA

Términos con definición acordada. Los skills de ingeniería usan este
vocabulario en nombres de issue, refactor, hipótesis y pruebas; no derivar a
sinónimos que el glosario evita.

## Combustible

### Tanqueo
Registro de surtimiento de combustible de un vehículo, con fecha, dinero
gastado, precio por galón y (opcional) odómetros inicial y final. Su fila de
base de datos es `fuel_logs`; el servicio de escritura es `FuelLogService`. Al
guardar, el saldo se hereda del tanqueo anterior del mismo vehículo y editar o
eliminar recalcula la cadena posterior (escritura en cascada).

### Solicitud de tanqueo
Pedido previo de combustible (código QR para el conductor) en estado
aprobado y vigente; al registrar el tanqueo queda **usada** y no vuelve a
asignarse. No confundir con el tanqueo mismo. Tabla `fuel_requests`.

### Estado de combustible
Vista por vehículo de cuánto se ha recorrido desde el último tanqueo y si el
tanque alcanza. Regla única viviendo en el módulo `EstadoCombustible`
(`app/Services/Fleet/Combustible`); la pantalla (`GET /fuel-logs/status`), la
fachada `FuelStatusService` y los avisos de agotamiento de notificaciones usan
LA MISMA regla. Componentes de la regla:

- **Línea de base**: odómetro final del tanqueo; si falta, el preoperacional
  más cercano a la fecha del tanqueo (el último anterior o, si no hay, el
  primero posterior). La fuente se marca `tanqueo` o `preoperacional` para no
  confundir la lectura.
- **Kilometraje actual**: el mayor entre la lectura diaria del preoperacional
  (`vehicle_inspections.mileage`) y el kilometraje final de la planilla de
  control (`service_delivery_control_sheet.ending_kilometer`), igual que el
  pronóstico de mantenimiento.
- **Clasificación**: `EXCEDIDO` (el tanque ya no alcanza), `PROXIMO` (resta
  menos que la reserva o se superó el porcentaje de aviso, configuración
  `fuel.notification_warning_percent` / `fuel.notification_reserve_km`), `OK`
  y `SIN_DATOS` (sin línea de base, sin kilometraje actual, sin alcance o con
  lecturas inconsistentes). En los avisos, `EXCEDIDO` se publica como
  `VENCIDO`.
- **Rendimiento (km/gal)**: cadena de origen manual → registro (km/gal de la
  propia fila) → promedio histórico del vehículo → tabla por cilindraje;
  sin dato calculable, `sin_dato`.

Un vehículo **sin tanqueos** nunca está `SIN_DATOS`: se lista como
`SIN_TANQUEO` para que toda la flota sea visible.

### Permiso del estado de combustible
Las rutas de tanqueos comparten el permiso `fuel_logs.index` (listado,
contexto de formulario, detección por uuid y la pantalla de estado). El
frontend condiciona el botón y la llamada del modal con
`permissionsStore.can('fuel_logs.index')`.
