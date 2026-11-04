# SPEC-016: Simplificación del Cliente HTTP y Persistencia Reactiva sin Caché en Frontend

## Problem Statement

En la arquitectura del cliente frontend (`noa-frontend-tsb`), existen múltiples capas de almacenamiento intermedio y cachés en memoria que generan inconsistencias y degradación de rendimiento:
1. `client.js` y `signature.service.js` mantienen mapas de instancias cacheadas de Axios por inquilino (`instances.tenants = new Map()`), mientras que los servicios derivados de `BaseService` ignoran este mapa y consumen la instancia global, corriendo el riesgo de omitir la cabecera `X-Company-UUID` en peticiones concurrentes.
2. El plugin de persistencia de Pinia (`persistence.js`) escucha reactivamente todas las mutaciones mediante `$subscribe` sin debouncing ni filtrado de claves transitorias, lo que provoca que ante cualquier cambio menor de UI se ejecute la serialización completa de cuatro stores y se dispare el algoritmo criptográfico AES-GCM en el hilo principal del navegador.
3. El store de notificaciones (`notifications.store.js`) controla los avisos flotantes (toasts) mostrados al usuario iterando secuencialmente sobre todas las claves de Capacitor Preferences (`expiry_alert_shown_*`), ejecutando decenas de llamadas asíncronas consecutivas (`await Preferences.get()`) que bloquean la inicialización de la interfaz en dispositivos móviles.

## Solution

Unificar el cliente HTTP en una única instancia canónica de Axios, configurando un interceptor de solicitudes que resuelva dinámicamente la cabecera `X-Company-UUID` desde el store activo de autenticación en cada petición, eliminando por completo los mapas de instancias múltiples.
Incorporar debouncing y filtrado de propiedades en el plugin de persistencia (`persistence.js`), asegurando que solo los datos esenciales de sesión y credenciales se cifren y persistan cuando el usuario finalice una secuencia de acciones.
Reemplazar la lectura secuencial de preferencias en `notifications.store.js` por un conjunto en memoria de sesión (`Set<string>`) para el control de alertas mostradas durante la sesión activa, eliminando las lecturas repetitivas contra el almacenamiento nativo del dispositivo.

## User Stories

1. Como Usuario de la Plataforma Web, quiero que la interfaz no sufra micro-bloqueos ni congelamientos al cambiar de pestañas o alternar filtros de pantalla, manteniendo una tasa de cuadros estable y fluida.
2. Como Conductor en la Aplicación Móvil, quiero que la aplicación abra y cargue inmediatamente sin demorarse leyendo docenas de claves de configuración en el arranque.
3. Como Administrador Multi-Empresa, quiero cambiar de empresa activa en el selector superior y tener la certeza absoluta de que todas las peticiones posteriores incluirán de inmediato la cabecera de la empresa seleccionada, sin retención de instancias cacheadas obsoletas.
4. Como Desarrollador Frontend, quiero que la arquitectura de comunicación con el backend sea predecible y simple, con un único punto de entrada para autenticación, cabeceras multi-tenant y manejo de errores 401.
5. Como Auditor de Seguridad, quiero que las operaciones criptográficas en el cliente se limiten a lo estrictamente necesario, protegiendo las credenciales sin sobrecargar la batería y CPU del dispositivo.

## Implementation Decisions

- **Instancia Única de Axios con Interceptor Dinámico:**
  - Se eliminarán los mapas `instances.tenants` en `client.js` y `signature.service.js`.
  - El interceptor de peticiones (`interceptors.js`) inyectará la cabecera `X-Company-UUID` obteniendo el valor directamente del estado reactivo del store de inquilinos (`auth.currentTenant.id`).
- **Debounce y Filtrado en `persistence.js`:**
  - Se implementará un temporizador de debounce (por ejemplo, 300 ms) para retrasar la serialización y cifrado de los stores durante mutaciones de alta frecuencia.
  - Se omitirán del cifrado las propiedades temporales de interfaz de usuario (`isLoading`, `isOpen`, `activeTab`).
- **Control de Alertas Mostradas en Memoria de Sesión:**
  - `notifications.store.js` mantendrá las claves de alertas mostradas en un `Set` en memoria durante el ciclo de vida de la página.
  - Se eliminará el barrido cíclico de claves con `Preferences.keys()` y los `await Preferences.get()` encadenados.

## Testing Decisions

- **Costura única y más alta posible:**
  - Pruebas unitarias y de integración con Vitest en entorno jsdom para verificar:
    - Que el interceptor de Axios añada dinámicamente la cabecera correcta ante cambios de empresa en Pinia.
    - Que el plugin de persistencia ejecute una única operación de almacenamiento tras múltiples mutaciones rápidas consecutivas.
    - Que el store de notificaciones registre las alertas mostradas sin invocar reiteradamente a Capacitor Preferences.
- **Antecedentes de prueba en el repositorio:**
  - `src/services/api/__tests__/client.test.js`, `src/store/plugins/__tests__/persistence.test.js`, `notifications.store.test.js`.

## Out of Scope

- Rediseño de las vistas de autenticación o del panel de notificaciones.
- Modificación del algoritmo AES-GCM o cambio de la biblioteca Capacitor Preferences.
- Alteración de los contratos de API con el backend Laravel.

## Further Notes

- Esta optimización en el frontend complementa el rediseño del backend sin caché, logrando una arquitectura de extremo a extremo donde tanto el servidor como el cliente operan de forma directa, reactiva y determinista.

