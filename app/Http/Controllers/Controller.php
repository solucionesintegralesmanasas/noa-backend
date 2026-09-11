<?php

namespace App\Http\Controllers;

use App\Traits\HandlesApiResponse;
use OpenApi\Attributes as OA;

if (! defined('L5_SWAGGER_CONST_HOST')) {
    define('L5_SWAGGER_CONST_HOST', config('app.url', 'http://localhost'));
}

#[OA\Info(
    version: '1.0.0',
    description: 'Documentación de la API de NoaTrasporteApi',
    title: 'NoaTrasporteApi Documentation'
)]
#[OA\Server(
    url: L5_SWAGGER_CONST_HOST,
    description: 'Servidor API Dinámico'
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    name: 'Authorization',
    in: 'header',
    scheme: 'bearer',
    bearerFormat: 'JWT'
)]
// ── Auth ─────────────────────────────────────────────────────────────────────
#[OA\Tag(name: 'Autenticación', description: 'Login, logout, refresh y gestión de sesiones')]
#[OA\Tag(name: 'Autenticación 2FA', description: 'Activación, confirmación, verificación y desactivación del doble factor')]
#[OA\Tag(name: 'Usuarios', description: 'Operaciones para la administración de usuarios en el sistema')]
#[OA\Tag(name: 'Roles', description: 'Operaciones para la administración de roles y perfiles de usuario')]
#[OA\Tag(name: 'Permisos', description: 'Operaciones para la administración de permisos y capacidades de usuario')]
// ── Integration ───────────────────────────────────────────────────────────────
#[OA\Tag(name: 'Integración', description: 'Integraciones con servicios externos (Google Drive, etc.)')]
// ── Catalogs ──────────────────────────────────────────────────────────────────
#[OA\Tag(name: 'Ciudad', description: 'Catálogo de ciudades')]
#[OA\Tag(name: 'Departamento', description: 'Catálogo de departamentos')]
#[OA\Tag(name: 'Marca', description: 'Catálogo de marcas de vehículos')]
#[OA\Tag(name: 'ClaseVehiculo', description: 'Catálogo de clases de vehículo')]
#[OA\Tag(name: 'TipoDocumento', description: 'Catálogo de tipos de documento')]
#[OA\Tag(name: 'ParametroDian', description: 'Parámetros DIAN')]
#[OA\Tag(name: 'RegimenFiscal', description: 'Regímenes fiscales')]
#[OA\Tag(name: 'MedioPago', description: 'Medios de pago')]
#[OA\Tag(name: 'TipoImpuesto', description: 'Tipos de impuesto')]
#[OA\Tag(name: 'TipoResolucionFacturacion', description: 'Tipos de resolución de facturación')]
#[OA\Tag(name: 'PucComercial', description: 'Plan Único de Cuentas comercial')]
#[OA\Tag(name: 'Tributo', description: 'Catálogo de tributos')]
#[OA\Tag(name: 'Retencion', description: 'Catálogo de retenciones')]
#[OA\Tag(name: 'ResponsabilidadTributaria', description: 'Responsabilidades tributarias')]
#[OA\Tag(name: 'UnidadMedida', description: 'Unidades de medida')]
#[OA\Tag(name: 'ItemInspeccion', description: 'Ítems de inspección vehicular')]
// ── Administrations ───────────────────────────────────────────────────────────
#[OA\Tag(name: 'Empresa', description: 'Gestión de empresas (multi-tenant)')]
#[OA\Tag(name: 'Sucursal', description: 'Gestión de sucursales')]
#[OA\Tag(name: 'ActividadEconómica', description: 'Actividades económicas de la empresa')]
#[OA\Tag(name: 'DetalleBancario', description: 'Cuentas bancarias de la empresa')]
#[OA\Tag(name: 'InformaciónTributaria', description: 'Información tributaria de la empresa')]
#[OA\Tag(name: 'Contacto', description: 'Contactos de la empresa')]
#[OA\Tag(name: 'EstadoFinanciero', description: 'Estados financieros de la empresa')]
#[OA\Tag(name: 'DeclaraciónRenta', description: 'Declaraciones de renta')]
#[OA\Tag(name: 'RegistroRUP', description: 'Registro Único de Proponentes (RUP)')]
#[OA\Tag(name: 'Experiencia', description: 'Experiencia habilitante de la empresa')]
#[OA\Tag(name: 'CapacidadTransporte', description: 'Capacidad transportadora habilitada')]
#[OA\Tag(name: 'SeguridadSaludTrabajo', description: 'Registros de seguridad y salud en el trabajo')]
#[OA\Tag(name: 'ResoluciónHabilitación', description: 'Resoluciones de habilitación')]
// ── ThirdParties ──────────────────────────────────────────────────────────────
#[OA\Tag(name: 'Tercero', description: 'Gestión de terceros (clientes, proveedores, etc.)')]
// ── Fleet ─────────────────────────────────────────────────────────────────────
#[OA\Tag(name: 'Vehiculo', description: 'Parque automotor')]
#[OA\Tag(name: 'Propietario', description: 'Propietarios de vehículos')]
#[OA\Tag(name: 'ConductorPropietario', description: 'Conductores y propietarios vinculados')]
#[OA\Tag(name: 'RegistroMantenimiento', description: 'Mantenimientos del parque automotor')]
#[OA\Tag(name: 'RepuestoMantenimiento', description: 'Repuestos usados en mantenimientos')]
#[OA\Tag(name: 'InspeccionVehiculo', description: 'Inspecciones preoperacionales de vehículos')]
#[OA\Tag(name: 'ResultadoInspeccion', description: 'Resultados de inspecciones vehiculares')]
#[OA\Tag(name: 'LicenciaConduccion', description: 'Licencias de conducción de conductores')]
#[OA\Tag(name: 'TarjetaOperacion', description: 'Tarjetas de operación de vehículos')]
#[OA\Tag(name: 'DocumentoVehiculo', description: 'Documentos asociados a vehículos')]
#[OA\Tag(name: 'AporteSeguridadSocial', description: 'Aportes de seguridad social de conductores')]
#[OA\Tag(name: 'ConvenioColaboracionEmpresarial', description: 'Convenios de colaboración empresarial')]
#[OA\Tag(name: 'Geolocalización', description: 'Tracking GPS de conductores, geocercas y alertas de ubicación')]
// ── ContractExtraction ────────────────────────────────────────────────────────
#[OA\Tag(name: 'Contratista', description: 'Contratistas del servicio de transporte')]
#[OA\Tag(name: 'FUEC', description: 'Formato Único de Extracto del Contrato')]
#[OA\Tag(name: 'PasajeroFUEC', description: 'Pasajeros registrados en el FUEC')]
#[OA\Tag(name: 'ObjetoContrato', description: 'Objetos del contrato de transporte')]
// ── Procedure ─────────────────────────────────────────────────────────────────
#[OA\Tag(name: 'Tramite', description: 'Trámites ante autoridades de transporte')]
#[OA\Tag(name: 'InventarioCapacidad', description: 'Inventarios de capacidad transportadora')]
#[OA\Tag(name: 'DirectorTerritorial', description: 'Directores territoriales del ministerio')]
#[OA\Tag(name: 'ContratoGestionFlota', description: 'Contratos de gestión de flota')]
abstract class Controller
{
    use HandlesApiResponse;
}
