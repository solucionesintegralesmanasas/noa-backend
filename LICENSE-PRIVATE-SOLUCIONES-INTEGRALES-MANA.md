=================================================================
                    LICENCIA PRIVADA DE USUARIO FINAL
                      EULA - Licencia de Usuario Final
=================================================================

                        SOLUCIONES INTEGRALES MANA S.A.S
                     NIT: 901-924-358-5  |  Sincelejo, Sucre, Colombia

                      Todos los derechos reservados. 2026
=================================================================



LICENCIATARIO:
--------------
[Nombre de la persona natural o jurídica]

FECHA DE ACEPTACIÓN: _______________
FIRMA: _____________________________



-----------------------------------------------------------------
                    SECCIÓN 1. CONCEPCIÓN Y CONCESIÓN DE LA LICENCIA
-----------------------------------------------------------------

1.1. Otorgante y Licenciatario.

1.1.1. Por medio presente documento, SOLUCIONES INTEGRALES MANA S.A.S., 
con NIT 901-924-358-5, con domicilio en Sincelejo, Sucre, Colombia 
(en adelante "la Empresa"), otorga al Licenciatario una licencia 
privada, no exclusiva y para uso exclusivo, para la utilización del 
software descrito en el presente documento.

1.1.2. El Licenciatario es la persona natural o jurídica que accede, 
instala o utiliza la API NOA, en conjunto con sus componentes frontend 
y backend (en adelante "el Software").

1.2. Alcance de la Licencia.

1.2.1. La presente licencia confiere al Licenciatario el derecho de:
    (a) Instalar y utilizar el Software en un único entorno de producción;
    (b) Modificar el código fuente del Software para adaptarlo a las 
        necesidades operativas del Licenciatario, siempre y cuando tales 
        modificaciones no violen los términos de la Sección 8;
    (c) Utilizar el Software en cantidad ilimitada de vehículos, conductores 
        y usuarios dentro de la organización del Licenciatario;
    (d) Realizar copias de seguridad y archivado del Software.

1.2.2. La licencia es para uso interno del Licenciatario y no debe ser:
    (a) Sub-licenciada, distribuida o vendida a terceros;
    (b) Utilizada para aplicaciones ajenas a la gestión de flotas y transporte.

1.3. Licencia "Tal Cual".

1.3.1. El Software se otorga "tal como está", sin garantías de ningún 
tipo, ya sea expresas o implícitas, incluidas pero no limitadas a 
garantías de comerciabilidad, idoneidad para un fin en particular, 
ausencia de violación de derechos de terceros o rendimiento específico.

-----------------------------------------------------------------
                    SECCIÓN 2. PROPIEDAD INTELECTUAL
-----------------------------------------------------------------

2.1. Propiedad de la Empresa.

2.1.1. El Software y toda su documentación son propiedad exclusiva de 
SOLUCIONES INTEGRALES MANA S.A.S. Todos los derechos no otorgados 
expresamente en el presente documento quedan reservados.

2.2. Reconocimiento del Licenciatario.

2.2.1. El Licenciatario reconoce y acepta que el Software contiene:
    (a) Código original desarrollado por la Empresa;
    (b) Módulos de terceros bajo licencias respectivas (ver Sección 7);
    (c) Datos de configuración y personalizaciones específicas de la Empresa.

2.3. Limitaciones de Derechos.

2.3.1. El Licenciatario no adquirirá ningún derecho de propiedad sobre 
el Software, sino solo el derecho de uso conforme a los términos del 
presente documento. Queda prohibida la eliminación o alteración de 
cualquier aviso de copyright, marca o esta licencia.

-----------------------------------------------------------------
                    SECCIÓN 3. COMPONENTES DE TERCEROS
-----------------------------------------------------------------

3.1. El Software incluye componentes de código abierto y comerciales 
con sus propias licencias. El Licenciatario debe cumplir con los 
términos de cada licencia de terceros incluida en el Software.

3.2. Lista de Componentes de Terceros.

| Componente | Licencia | Nota |
|------------|----------|------|
| Laravel Framework | MIT | Núcleo del sistema |
| Vue 3 | MIT | Aplicación frontend |
| Spatie Laravel Permission | MIT | Gestión de roles/permisos |
| DomPDF | MIT | Generación de PDF |
| Maatwebsite Excel | MIT | Exportación Excel |
| Guzzle HTTP | MIT | Clientes HTTP |
| Laravel Sanctum | MIT | Autenticación |

3.2.1. El Licenciatario es responsable de cumplir con los términos de 
cada licencia de terceros y exime a la Empresa de cualquier reclamo 
derivado del uso indebido de dichos componentes.

-----------------------------------------------------------------
                    SECCIÓN 4. RESTRICCIONES DE SEGURIDAD
-----------------------------------------------------------------

4.1. Arquitectura Multi-Tenant.

4.1.1. El Software está diseñado para operar con arquitectura 
multi-tenant, donde cada empresa tiene su propio `company_uuid` y 
configuración aislada.

4.1.2. El Licenciatario se compromete a:
    (a) No permitir que otros usos del Software se mezclen con datos 
        de la Empresa;
    (b) Mantener la confidencialidad de los `company_uuid` y tokens de 
        acceso;
    (c) No intentar acceder a datos de otras empresas mediante 
        manipulación de parámetros;
    (d) Proteger las credenciales de autenticación (access tokens de 8 
        horas, refresh tokens de 30 días).

4.2. Responsabilidad del Licenciatario.

4.2.1. El incumplimiento de las restricciones multi-tenant constituirá 
una violación material de la presente licencia y dará derecho a la 
Empresa a ejercer las acciones legales pertinentes según la Sección 11.

-----------------------------------------------------------------
                    SECCIÓN 5. EXPORTACIONES Y FUNCIONALIDADES
-----------------------------------------------------------------

5.1. Exportaciones PDF y Excel.

5.1.1. Las funcionalidades de exportación (PDF con DomPDF, Excel con 
Maatwebsite) están incluidas para uso exclusivo del Licenciatario.

5.1.2. El Licenciatario se compromete a:
    (a) Utilizar las exportaciones únicamente para fines internos o de 
        su organización;
    (b) No revender, distribuir ni compartir archivos generados con el 
        Software a terceros;
    (c) Asegurar que los datos sensibles (ubicación GPS, inspecciones, 
        documentos) estén protegidos según normativas locales de protección 
        de datos.

5.1.3. Rendimiento y optimización.

5.1.3.1. El rendimiento de exportaciones está optimizado según las 
pruebas de rendimiento del proyecto (SPEC-004): PDF 0 consultas < 24 
MB, Excel 9 consultas < 28 MB con 50 vehículos. El Licenciatario no 
debe modificar la lógica de consulta que permita exportaciones masivas 
no controladas.

-----------------------------------------------------------------
                    SECCIÓN 6. GESTIÓN DE CORREOS Y CRON
-----------------------------------------------------------------

6.1. Correos de Vencimientos.

6.1.1. Las funcionalidades de correo de vencimientos (`fleet:notify-expiring-documents`) 
requieren configuración adecuada en el entorno del Licenciatario.

6.1.2. Limitaciones de responsabilidad.

6.1.2.1. La Empresa no se responsabiliza por:
    (a) Fallos en el envío de correos por configuración SMTP del 
        Licenciatario;
    (b) Bloqueos de cuentas por parte proveedores de correo (incidencia 
        conocida: `mail.solucionesintegralesmana.com` suspendió envíos 
        desde `notificaciones@solucionesintegralesmana.com`);
    (c) Uso indebido de los endpoints `/public/cron/*` sin el `CRON_SECRET` 
        correspondiente.

6.1.3. Obligaciones del Licenciatario.

6.1.3.1. El Licenciatario debe:
    (a) Configurar variables de entorno `.env` adecuadas (MAIL_DRIVER, 
        SMTP, CRON_SECRET);
    (b) Utilizar `MAIL_MAILER=log` para pruebas sin enviar correos reales;
    (c) Proteger los endpoints `/public/cron/*` con el `CRON_SECRET` 
        configurado.

-----------------------------------------------------------------
                    SECCIÓN 7. SEGURIDAD Y AUTORIZACIÓN
-----------------------------------------------------------------

7.1. Autorización por Recurso.

7.1.1. El Software implementa autorización por recurso (`authz` middleware) 
y control de roles (Spatie Laravel Permission).

7.1.2. Obligaciones del Licenciatario.

7.1.2.1. El Licenciatario debe:
    (a) Mantener la configuración de roles y permisos según la arquitectura 
        definida;
    (b) No asignar permisos `SUPERADMIN` a roles subordinados sin autorización 
        escrita;
    (c) Respetar el aislamiento por `company_uuid` en todas las operaciones;
    (d) Mantener actualizada la configuración de `AUTHZ_ENFORCE` (auditoría 
        vs. aplicación).

7.1.3. Evaluación de Rutas.

7.1.3.1. La fase de autorización por recurso evalúa aproximadamente 391 de 
489 rutas. El Licenciatario debe revisar el log de auditoría 
(`storage/logs/laravel.log`) antes de activar `AUTHZ_ENFORCE=true` en 
producción.

-----------------------------------------------------------------
                    SECCIÓN 8. TRACKING Y GPS
-----------------------------------------------------------------

8.1. Datos de Tracking.

8.1.1. Las funcionalidades de tracking GPS incluyen guarda de 
`driver_locations` con `distance_meters` y estadísticas diarias.

8.1.2. Compromisos del Licenciatario.

8.1.2.1. El Licenciatario se compromete a:
    (a) Utilizar los datos de tracking exclusivamente para la gestión de su 
        flota;
    (b) Cumplir con normativas locales de protección de datos y privacidad;
    (c) No ceder datos de ubicación a terceros sin consentimiento de los 
        conductores;
    (d) Mantener índices de rendimiento según SPEC-004 (monitor 34 ms, mapa 
        por vehículo 22 ms, historial 8.4 ms, alertas ~1 ms con volumen de 
        600k puntos).

-----------------------------------------------------------------
                    SECCIÓN 9. DURACIÓN Y TERMINACIÓN
-----------------------------------------------------------------

9.1. Plazo de la Licencia.

9.1.1. La presente licencia entra en vigor a partir de la fecha de 
aceptación y tiene una duración de **1 año** a partir de dicha fecha, 
renovable por períodos iguales por mutuo acuerdo escrito.

9.2. Causas de Terminación.

9.2.1. La Empresa podrá terminar esta licencia de inmediato si el 
Licenciatario:
    (a) Incumple cualquiera de las disposiciones de la presente Sección 9;
    (b) Utiliza el Software con fines ilícitos o no autorizados;
    (c) Intenta distribuir o sub-licenciar el Software sin autorización;
    (d) Rompe las restricciones de multi-tenant o seguridad del sistema.

9.3. Obligaciones al Terminar.

9.3.1. Al terminar la licencia, el Licenciatario debe:
    (a) Dejar de utilizar el Software de inmediato;
    (b) Eliminar todas las copias del código fuente, documentación y bases 
        de datos que contengan el Software (o devolverlas a la Empresa si 
        así se solicita);
    (c) Eliminar cualquier archivo generado mediante las exportaciones (PDF/Excel) 
        que contengan datos del período de licencia.

-----------------------------------------------------------------
                    SECCIÓN 10. LIMITACIÓN DE RESPONSABILIDAD
-----------------------------------------------------------------

10.1. Límites de Responsabilidad.

10.1.1. EN NINGÚN CASO LA EMPRESA SERÁ RESPONSABLE POR:
    (a) Pérdidas de resultados, disminución de ingresos o gastos indirectos;
    (b) Pérdida de datos o costos de recuperación de datos, hasta en la 
        medida permitida por la ley aplicable;
    (c) Pérdidas causadas por uso ilícito o no autorizado del Software;
    (d) Interrupción de servicios o fallos en el entorno del Licenciatario.

10.2. Techo de Responsabilidad.

10.2.1. La responsabilidad total de la Empresa por cualquier reclamo, 
sea en contrato, agravio o de otro tipo, no excederá el monto pagado 
por el Licenciatario por esta licencia en los últimos 30 (treinta) días.

-----------------------------------------------------------------
                    SECCIÓN 11. LEY APLICABLE Y JURISDICCIÓN
-----------------------------------------------------------------

11.1. Legislación Aplicable.

11.1.1. La presente licencia se rige por las leyes de **Colombia**. 
Específicamente por el Código de Comercio y la Ley 1340 de 2009 
(Propiedad Intelectual).

11.2. Jurisdicción.

11.2.1. Todas las controversias derivadas de o relacionadas con esta 
licencia serán sometidas a la jurisdicción de los tribunales de la ciudad 
de **Sincelejo, Sucre, Colombia**.

11.3. Divisibilidad.

11.3.1. Si alguna disposición de esta licencia se considera inválida o 
inejecutable, las disposiciones restantes permanecerán en pleno vigor 
y efecto. Las partes sustituirán la disposición inválida por una 
válida que se acerque lo máximo posible a la intención de la disposición 
original.

-----------------------------------------------------------------
                    SECCIÓN 12. ACEPTACIÓN Y FIRMA
-----------------------------------------------------------------

12.1. Aceptación de Términos.

12.1.1. Al instalar, acceder o utilizar el Software, el Licenciatario 
reconoce haber leído, entendido y aceptado los términos de la presente 
Licencia Privada de Usuario Final (EULA). Si no está de acuerdo con 
algún término, debe dejar de utilizar el Software y ponerse en contacto 
con SOLUCIONES INTEGRALES MANA S.A.S para discutir alternativas.

12.2. Firma y Aceptación.

-----------------------------------------------------------------
| Firma del Licenciatario | Fecha |
|-------------------------|-------|
| _______________________ | ______|

-----------------------------------------------------------------
| Firma de la Empresa | Fecha |
|---------------------|-------|
| ___________________ | ______|

-----------------------------------------------------------------
| Testigo 1 | Firma | Fecha |
|-----------|---------|-------|
| __________| __________| ______|

-----------------------------------------------------------------
| Testigo 2 | Firma | Fecha |
|-----------|---------|-------|
| __________| __________| ______|



-----------------------------------------------------------------
                    INFORMACIÓN DE DOCUMENTO
-----------------------------------------------------------------

Documento: LICENCIA PRIVADA DE USUARIO FINAL (EULA)
Versión: 1.0
Fecha de Emisión: 6 de octubre de 2026
Empresa: SOLUCIONES INTEGRALES MANA S.A.S
NIT: 901-924-358-5
Ubicación: Sincelejo, Sucre, Colombia
Sistema: API NOA - Gestión de Flotas
Tecnología: Laravel 12 + Vue 3

Contacto Administrativo:
Correo: contacto@solucionesintegralesmana.com
Web: www.solucionesintegralesmana.com



=================================================================
                      FIN DE LA LICENCIA PRIVADA
=================================================================