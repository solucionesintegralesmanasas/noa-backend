-- =====================================================================================================================
-- MÓDULO 1 · CATÁLOGOS BASE
-- =====================================================================================================================
CREATE TABLE IF NOT EXISTS departments (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT COMMENT 'Identificador único del departamento',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID universal único del departamento',
    dane_code VARCHAR(5) NOT NULL UNIQUE COMMENT 'Código oficial DANE del departamento',
    name VARCHAR(100) NOT NULL COMMENT 'Nombre oficial del departamento',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de creación del registro',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha y hora de última modificación'
) COMMENT = 'Catálogo de departamentos de Colombia';

CREATE TABLE IF NOT EXISTS cities (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT COMMENT 'Identificador único del municipio',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID universal único del municipio',
    department_uuid CHAR(36) NOT NULL COMMENT 'UUID del departamento al que pertenece',
    dane_code VARCHAR(8) NOT NULL COMMENT 'Código oficial DANE del municipio',
    name VARCHAR(100) NOT NULL COMMENT 'Nombre oficial del municipio',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de creación del registro',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha y hora de última modificación',
    CONSTRAINT fk_cities_department FOREIGN KEY (department_uuid) REFERENCES departments (uuid) ON DELETE RESTRICT ON UPDATE CASCADE
) COMMENT = 'Catálogo de ciudades y municipios colombianos';

CREATE TABLE IF NOT EXISTS type_of_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único del tipo de documento',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID universal único del tipo de documento',
    name VARCHAR(255) NOT NULL COMMENT 'Nombre descriptivo del tipo de documento (ej: CC, NIT, CE, Pasaporte)',
    prefix VARCHAR(255) NOT NULL COMMENT 'Prefijo estándar asociado al tipo de documento',
    status TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Estado del tipo de documento (1=activo, 0=inactivo)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de creación del registro',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha y hora de última modificación'
) COMMENT = 'Catálogo de tipos de identificación legal y tributaria';

CREATE TABLE IF NOT EXISTS tax_regimes (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT COMMENT 'Identificador único del régimen fiscal',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID universal único del régimen fiscal',
    code VARCHAR(10) NOT NULL UNIQUE COMMENT 'Código oficial DIAN del régimen fiscal',
    name VARCHAR(100) NOT NULL COMMENT 'Nombre del régimen fiscal',
    description VARCHAR(255) COMMENT 'Descripción detallada del régimen fiscal',
    is_active BOOLEAN DEFAULT TRUE COMMENT 'Indica si el régimen fiscal se encuentra vigente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de creación del registro',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha y hora de última modificación'
) COMMENT = 'Catálogo de regímenes tributarios según normativa DIAN';

CREATE TABLE IF NOT EXISTS tax_responsibilities (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT COMMENT 'Identificador único de la responsabilidad tributaria',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID universal único de la responsabilidad tributaria',
    code VARCHAR(5) NOT NULL UNIQUE COMMENT 'Código DIAN de la responsabilidad tributaria',
    name VARCHAR(255) NOT NULL COMMENT 'Nombre oficial de la responsabilidad',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de creación del registro',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha y hora de última modificación'
) COMMENT = 'Catálogo de responsabilidades y obligaciones tributarias';

CREATE TABLE IF NOT EXISTS vehicle_class (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT COMMENT 'Identificador único de la clase de vehículo',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID universal único de la clase de vehículo',
    class_code_class CHAR(5) NOT NULL COMMENT 'Código interno corto de la clase de vehículo',
    description VARCHAR(200) NOT NULL COMMENT 'Descripción técnica de la clase de vehículo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de creación del registro',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha y hora de última modificación'
) COMMENT = 'Catálogo de clases y categorías de vehículos';

CREATE TABLE IF NOT EXISTS brands (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT COMMENT 'Identificador único de la marca',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID universal único de la marca',
    description VARCHAR(200) NOT NULL COMMENT 'Nombre comercial o descripción de la marca',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de creación del registro',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha y hora de última modificación'
) COMMENT = 'Catálogo de marcas comerciales';

CREATE TABLE IF NOT EXISTS billing_resolution_types (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 del tipo de resolución',
    code VARCHAR(10) NOT NULL COMMENT 'Código DIAN que clasifica el documento electrónico autorizado',
    name VARCHAR(100) NOT NULL COMMENT 'Descripción comercial del documento amparado',
    is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Habilitación para uso en resoluciones de facturación',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de creación del registro',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha y hora de última modificación',
    PRIMARY KEY (id),
    UNIQUE KEY uuid(uuid),
    UNIQUE KEY code (code)
) COMMENT = 'Tipos de documentos electrónicos autorizados por la DIAN';

CREATE TABLE IF NOT EXISTS dian_parameters (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 único para los parámetros',
    year SMALLINT NOT NULL COMMENT 'Año fiscal al que aplican los parámetros',
    uvt DECIMAL(10, 2) NOT NULL COMMENT 'Valor de la Unidad de Valor Tributario (UVT) para el año',
    iva_withholding_rate DECIMAL(6, 4) NOT NULL DEFAULT 0.15 COMMENT 'Porcentaje estándar de retención de IVA (ej: 0.15 = 15%)',
    minimum_wage DECIMAL(12, 2) NULL COMMENT 'Salario mínimo legal vigente mensual para el año',
    transport_subsidy DECIMAL(12, 2) NULL COMMENT 'Auxilio de transporte mensual para el año',
    usury_rate DECIMAL(6, 4) NULL COMMENT 'Tasa máxima de usura certificada por la Superfinanciera',
    observations TEXT NULL COMMENT 'Observaciones o notas legales aplicables al año',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de creación del registro',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha y hora de última modificación',
    PRIMARY KEY (id),
    UNIQUE KEY uk_uuid (uuid),
    UNIQUE KEY uk_year (year)
) COMMENT = 'Parámetros tributarios y económicos anuales de referencia DIAN';

CREATE TABLE IF NOT EXISTS puc_commercial (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 del PUC comercial base',
    code VARCHAR(6) NOT NULL COMMENT 'Código contable según PUC oficial (ej: 130505)',
    level TINYINT NOT NULL COMMENT 'Nivel jerárquico de la cuenta (1:Clase, 2:Grupo, 3:Cuenta, 4:Subcuenta, 5:Auxiliar)',
    description VARCHAR(300) NOT NULL COMMENT 'Denominación oficial de la cuenta PUC',
    nature CHAR(1) NOT NULL COMMENT 'Naturaleza contable: D (Débito) o C (Crédito)',
    account_class VARCHAR(50) NOT NULL COMMENT 'Clase contable a la que pertenece',
    is_reductive BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Indica si la cuenta es reductora de activo o pasivo',
    is_active BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Indica si la cuenta está vigente en el catálogo nacional',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de creación del registro',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha y hora de última actualización',
    PRIMARY KEY (id),
    UNIQUE KEY uk_uuid (uuid)
) COMMENT = 'Catálogo base del Plan Único de Cuentas (PUC) comercial colombiano';

CREATE TABLE IF NOT EXISTS payment_methods (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 único del método de pago',
    dian_code VARCHAR(5) NOT NULL UNIQUE COMMENT 'Código DIAN para facturación electrónica (10:Efectivo, 42:Consignación, 48:Tarjeta, etc.)',
    name VARCHAR(100) NOT NULL COMMENT 'Nombre descriptivo del medio de pago',
    is_active TINYINT(1) DEFAULT 1 COMMENT 'Indica si el método de pago está habilitado para su uso',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de creación del registro',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha y hora de última modificación',
    PRIMARY KEY (id),
    UNIQUE KEY uk_uuid (uuid),
    UNIQUE KEY uk_dian_code (dian_code)
) COMMENT = 'Catálogo de medios de pago según resolución DIAN';

CREATE TABLE IF NOT EXISTS tax_types (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 único del tipo de impuesto',
    dian_code VARCHAR(10) NOT NULL UNIQUE COMMENT 'Código DIAN del impuesto (01:IVA, 02:ICA, 03:INC, 04:Timbre, 05:Bolsa, etc.)',
    name VARCHAR(100) NOT NULL COMMENT 'Nombre completo del impuesto',
    type ENUM(
        'IVA',
        'INC',
        'ICA',
        'TIMBRE',
        'FOMENTO',
        'OTRO'
    ) NOT NULL COMMENT 'Clasificación tributaria del impuesto',
    rate DECIMAL(6, 4) NOT NULL COMMENT 'Tarifa porcentual aplicada (ej: 0.1900 = 19%)',
    debit_account VARCHAR(20) NULL COMMENT 'Cuenta PUC para registrar el débito del impuesto',
    credit_account VARCHAR(20) NULL COMMENT 'Cuenta PUC para registrar el crédito del impuesto',
    applies_sales TINYINT(1) DEFAULT 1 COMMENT 'Indica si el impuesto aplica en ventas/facturación',
    applies_purchases TINYINT(1) DEFAULT 1 COMMENT 'Indica si el impuesto aplica en compras/proveedores',
    is_active TINYINT(1) DEFAULT 1 COMMENT 'Indica si la configuración del impuesto está vigente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de creación del registro',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha y hora de última actualización',
    PRIMARY KEY (id),
    UNIQUE KEY uk_uuid (uuid),
    UNIQUE KEY uk_dian_code (dian_code)
) COMMENT = 'Configuración de impuestos conforme a la normatividad DIAN';

CREATE TABLE IF NOT EXISTS withholdings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 único de la retención',
    code VARCHAR(20) NOT NULL UNIQUE COMMENT 'Código interno de la retención',
    name VARCHAR(150) NOT NULL COMMENT 'Denominación oficial de la retención',
    type ENUM(
        'RETEFUENTE',
        'RETEIVA',
        'RETEICA',
        'RETECREE',
        'AUTORETENCIÓN'
    ) NOT NULL COMMENT 'Tipo de retención según clasificación tributaria',
    dian_concept VARCHAR(10) NULL COMMENT 'Concepto DIAN aplicable',
    base_minimum DECIMAL(18, 2) NOT NULL DEFAULT 0.00 COMMENT 'Base mínima en COP para aplicar la retención',
    rate DECIMAL(8, 6) NOT NULL COMMENT 'Porcentaje de retención (ej: 0.035000 = 3.5%)',
    debit_account VARCHAR(20) NULL COMMENT 'Cuenta contable de débito para la retención',
    credit_account VARCHAR(20) NULL COMMENT 'Cuenta contable de crédito para la retención',
    applies_purchases TINYINT(1) DEFAULT 1 COMMENT 'Indica si se retiene a proveedores en compras',
    applies_sales TINYINT(1) DEFAULT 0 COMMENT 'Indica si se retiene a clientes en ventas',
    is_active TINYINT(1) DEFAULT 1 COMMENT 'Indica si la configuración de retención está activa',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de creación del registro',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha y hora de última modificación',
    PRIMARY KEY (id),
    UNIQUE KEY uk_uuid (uuid),
    UNIQUE KEY uk_code (code)
) COMMENT = 'Tabla de retenciones tributarias según normativa DIAN';

CREATE TABLE IF NOT EXISTS measurement_units (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 de la unidad de medida',
    code VARCHAR(10) NOT NULL COMMENT 'Código UN/CEFACT o DIAN (ej. UND, KG, LT, HUR)',
    name VARCHAR(100) NOT NULL COMMENT 'Nombre descriptivo de la unidad',
    is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Estado de habilitación para transacciones',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Marca de tiempo de creación',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Marca de tiempo de última modificación',
    PRIMARY KEY (id),
    UNIQUE KEY uk_uuid (uuid),
    UNIQUE KEY uk_code (code)
) COMMENT = 'Catálogo estandarizado de unidades de medida para facturación e inventario';

CREATE TABLE IF NOT EXISTS tributes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 único del tributo',
    code VARCHAR(10) NOT NULL COMMENT 'Código DIAN del tributo (01:IVA, 04:INC, ZA:No aplica, etc.)',
    name VARCHAR(100) NOT NULL COMMENT 'Nombre oficial del tributo según DIAN',
    description VARCHAR(255) NULL COMMENT 'Descripción o aclaración del tributo',
    is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Estado de habilitación',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Marca de tiempo de creación',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Marca de tiempo de última modificación',
    PRIMARY KEY (id),
    UNIQUE KEY uk_uuid (uuid),
    UNIQUE KEY uk_dian_code (dian_code)
) COMMENT = 'Catálogo de tributos DIAN para líneas de facturación electrónica';

CREATE TABLE inspection_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único del item de inspección',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID único universal del item de inspección',
    category ENUM(
        'DOCUMENTOS',
        'DOTACION',
        'VIDRIOS_ESPEJOS',
        'OTROS',
        'EMERGENCIAS',
        'EXTINTOR',
        'HERRAMIENTAS',
        'LUCES',
        'FLUIDOS',
        'NEUMATICOS',
        'PRESION'
    ) NOT NULL COMMENT 'Categoría del item de inspección',
    item_name VARCHAR(150) NOT NULL COMMENT 'Nombre del item de inspección',
    description TEXT NULL COMMENT 'Descripción del item de inspección',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última modificación'
) COMMENT = 'Items de inspección';

-- =====================================================================================================================
-- MÓDULO 2 · ADMINISTRATIVO
-- =====================================================================================================================
CREATE TABLE IF NOT EXISTS companies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único de la empresa',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID universal único de la empresa',
    person_type ENUM(
        'PERSONA NATURAL',
        'PERSONA JURIDICA'
    ) NOT NULL COMMENT 'Tipo de persona jurídica o natural',
    type_of_company ENUM('PUBLICO', 'PRIVADO') NOT NULL COMMENT 'Naturaleza de la compañía',
    economic_sector VARCHAR(255) NULL COMMENT 'Sector económico de operación',
    legal_structure ENUM(
        'SOCIEDAD POR ACCIONES SIMPLIFICADA - SAS',
        'SOCIEDAD DE RESPONSABILIDAD LIMITADA - LTDA',
        'SOCIEDAD POR ACCIONES - SPA',
        'SOCIEDAD ANONIMA - SA',
        'UNIÓN TEMPORAL - UT',
        'ENTIDAD SIN ÁNIMO DE LUCRO - ESAL'
    ) NULL COMMENT 'Estructura legal registrada',
    document_type_uuid CHAR(36) NOT NULL COMMENT 'UUID del tipo de documento de la empresa',
    document_number VARCHAR(20) NOT NULL COMMENT 'Número de documento único',
    verification_digit VARCHAR(1) COMMENT 'Dígito de verificación del NIT',
    business_name VARCHAR(200) NOT NULL COMMENT 'Razón social legal completa',
    trade_name VARCHAR(100) COMMENT 'Nombre comercial o marca',
    commercial_registration VARCHAR(50) COMMENT 'Matrícula mercantil en Cámara de Comercio',
    municipality_uuid CHAR(36) NOT NULL COMMENT 'UUID del municipio de domicilio',
    address VARCHAR(200) NOT NULL COMMENT 'Dirección física completa',
    postal_code VARCHAR(10) COMMENT 'Código postal de la dirección',
    phone VARCHAR(20) COMMENT 'Teléfono corporativo principal',
    email VARCHAR(100) NOT NULL COMMENT 'Correo electrónico corporativo',
    tax_regime_uuid CHAR(36) NOT NULL COMMENT 'UUID del régimen fiscal asignado',
    currency_code CHAR(3) DEFAULT 'COP' COMMENT 'Código ISO 4217 de moneda principal',
    approximate_number_of_employees CHAR(10) NULL COMMENT 'Rango o número aproximado de empleados',
    web_page VARCHAR(255) NOT NULL COMMENT 'Sitio web oficial',
    country_code CHAR(2) DEFAULT 'CO' COMMENT 'Código ISO 3166-1 alfa-2 del país',
    legal_representative_name VARCHAR(255) NOT NULL COMMENT 'Nombre(s) del representante legal',
    legal_representative_last_name VARCHAR(255) NOT NULL COMMENT 'Apellido(s) del representante legal',
    legal_representative_document_type VARCHAR(20) NOT NULL COMMENT 'Tipo de documento del representante legal',
    legal_representative_document_number VARCHAR(20) NOT NULL COMMENT 'Número de documento del representante legal',
    legal_representative_nationality VARCHAR(20) NULL COMMENT 'Nacionalidad del representante legal',
    legal_representative_document_issue_date DATE NULL COMMENT 'Fecha de expedición del documento',
    is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Estado de habilitación de la empresa (1=activo, 0=inactivo)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de creación del registro',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha y hora de última modificación',
    CONSTRAINT fk_companies_doc_type FOREIGN KEY (document_type_uuid) REFERENCES type_of_documents (uuid) ON UPDATE CASCADE,
    CONSTRAINT fk_companies_tax_regime FOREIGN KEY (tax_regime_uuid) REFERENCES tax_regimes (uuid) ON UPDATE CASCADE,
    CONSTRAINT fk_companies_municipality FOREIGN KEY (municipality_uuid) REFERENCES cities (uuid) ON UPDATE CASCADE
) COMMENT = 'Registro maestro de empresas o entidades del sistema';

CREATE TABLE IF NOT EXISTS contacts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único del contacto',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID universal único del contacto',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa a la que pertenece el contacto',
    first_name VARCHAR(100) COMMENT 'Nombres del contacto',
    last_name VARCHAR(100) COMMENT 'Apellidos del contacto',
    representative_type ENUM(
        'COMERCIAL',
        'REPRESENTANTE_LEGAL',
        'TECNICO',
        'CARTERA',
        'COMPRAS',
        'CONTACTO_FACTURACION',
        'CUMPLIMIENTO',
        'FINANCIERO',
        'HSE',
        'JURIDICO',
        'REPRESENTANTE_LEGAL_SUPLENTE'
    ) NOT NULL COMMENT 'Cargo o función del contacto en la organización',
    country VARCHAR(100) COMMENT 'País de residencia o ubicación',
    municipality_uuid CHAR(36) NULL COMMENT 'UUID del municipio de ubicación',
    email VARCHAR(150) COMMENT 'Correo electrónico de contacto',
    phone_number VARCHAR(50) COMMENT 'Número de teléfono de contacto',
    remarks TEXT COMMENT 'Observaciones o notas adicionales sobre el contacto',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de creación del registro',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha y hora de última modificación',
    CONSTRAINT fk_contacts_company FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_contacts_municipality FOREIGN KEY (municipality_uuid) REFERENCES cities (uuid) ON DELETE RESTRICT ON UPDATE CASCADE
) COMMENT = 'Tabla de contactos asociados a la empresa';

CREATE TABLE IF NOT EXISTS branches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único de la sucursal',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID universal único de la sucursal',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa propietaria',
    name VARCHAR(100) NOT NULL COMMENT 'Nombre comercial o interno de la sucursal',
    address VARCHAR(200) NOT NULL COMMENT 'Dirección física de la sucursal',
    municipality_uuid CHAR(36) NOT NULL COMMENT 'UUID del municipio de ubicación',
    is_primary BOOLEAN DEFAULT FALSE COMMENT 'Indica si es la sede principal de la empresa',
    status TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Estado operativo de la sucursal',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de creación del registro',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha y hora de última modificación',
    CONSTRAINT fk_branches_company FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE,
    CONSTRAINT fk_branches_municipality FOREIGN KEY (municipality_uuid) REFERENCES cities (uuid) ON UPDATE CASCADE
) COMMENT = 'Catálogo de sucursales o sedes operativas';

CREATE TABLE IF NOT EXISTS economic_activities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único de la actividad',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID universal único de la actividad económica',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa que la ejerce',
    activity_code VARCHAR(20) NOT NULL COMMENT 'Código de la actividad económica (CIIU Rev. 4 A.C.)',
    activity_description VARCHAR(255) COMMENT 'Descripción detallada de la actividad',
    is_main_activity BOOLEAN DEFAULT FALSE COMMENT 'Indica si es la actividad económica principal',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de creación del registro',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha y hora de última modificación',
    CONSTRAINT fk_econ_activities_company FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE
) COMMENT = 'Actividades económicas registradas por empresa';

CREATE TABLE IF NOT EXISTS bank_details (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único del detalle bancario',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa propietaria',
    bank_name VARCHAR(100) COMMENT 'Nombre comercial del banco',
    branch_office VARCHAR(100) COMMENT 'Nombre de la sucursal bancaria',
    account_type VARCHAR(50) COMMENT 'Tipo de producto (ej. ahorros, corriente, fiduciaria)',
    account_number VARCHAR(50) NOT NULL COMMENT 'Número de cuenta bancaria',
    account_holder VARCHAR(255) COMMENT 'Nombre completo del titular de la cuenta',
    is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Indica si la cuenta está habilitada para transacciones',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de registro',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha y hora de última modificación',
    CONSTRAINT fk_bank_details_company FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE
) COMMENT = 'Información bancaria corporativa de la empresa';

CREATE TABLE IF NOT EXISTS tax_information (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único del registro tributario',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa',
    is_withholding_agent_exempt TINYINT(1) NULL COMMENT 'Indica si es agente de retención o está exento',
    tax_special_regime VARCHAR(255) NULL COMMENT 'Ley o régimen tributario especial aplicable',
    company_size VARCHAR(50) NULL COMMENT 'Clasificación de tamaño (Mipyme, mediana, grande, etc.)',
    financial_statements_path VARCHAR(255) NULL COMMENT 'Ruta del archivo de estados financieros del último cierre',
    company_size_certificate_path VARCHAR(255) NULL COMMENT 'Ruta del certificado de tamaño empresarial',
    remarks TEXT NULL COMMENT 'Observaciones generales de carácter tributario',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de creación del registro',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha y hora de última modificación',
    CONSTRAINT fk_tax_info_company FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE
) COMMENT = 'Información tributaria y fiscal complementaria';

CREATE TABLE IF NOT EXISTS enabling_resolutions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único de la resolución',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID universal de la resolución',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa autorizada',
    resolution_number VARCHAR(20) NOT NULL COMMENT 'Número oficial de la resolución',
    number_fuec VARCHAR(20) NOT NULL COMMENT 'Número FUEC (Formulario Único Electrónico de Comunicación)',
    territorial_code VARCHAR(20) NOT NULL COMMENT 'Código territorial asignado',
    resolution_date DATE NOT NULL COMMENT 'Fecha de emisión o publicación',
    status TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Estado de vigencia de la resolución',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de creación del registro',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha y hora de última modificación',
    CONSTRAINT fk_enabling_res_company FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) COMMENT = 'Resoluciones de habilitación para emitir FUECs';

CREATE TABLE IF NOT EXISTS conveyor_capacity (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único de capacidad',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID universal de la capacidad',
    enabling_resolution_uuid CHAR(36) NOT NULL COMMENT 'UUID de la resolución habilitante',
    vehicle_type VARCHAR(20) NOT NULL COMMENT 'Tipo o categoría de vehículo autorizado',
    authorized_capacity INT NOT NULL COMMENT 'Capacidad máxima autorizada',
    current_capacity INT NOT NULL COMMENT 'Capacidad operativa actual',
    minimum_own_capacity INT NOT NULL COMMENT 'Capacidad mínima propia exigida',
    status TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Estado de habilitación',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de creación del registro',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha y hora de última modificación',
    CONSTRAINT fk_conveyor_capacity_res FOREIGN KEY (enabling_resolution_uuid) REFERENCES enabling_resolutions (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) COMMENT = 'Capacidad de transporte autorizada y actual';

CREATE TABLE IF NOT EXISTS experiences (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único de la experiencia',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID universal único de la experiencia',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa que realizó el contrato',
    customer_name VARCHAR(255) NOT NULL COMMENT 'Razón social o nombre del cliente',
    value_before_tax DECIMAL(18, 2) COMMENT 'Valor del contrato antes de impuestos',
    currency VARCHAR(10) DEFAULT 'COP' COMMENT 'Moneda de contratación',
    start_date DATE COMMENT 'Fecha de inicio de ejecución',
    end_date DATE COMMENT 'Fecha de finalización o corte contractual',
    is_ongoing DATE COMMENT 'Fecha hasta la cual la experiencia está vigente (NULL si finalizada)',
    remarks TEXT COMMENT 'Observaciones sobre alcance, cumplimiento o desempeño',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de creación del registro',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha y hora de última modificación',
    CONSTRAINT fk_experiences_company FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE
) COMMENT = 'Registro de experiencias y contratos comerciales previos';

CREATE TABLE IF NOT EXISTS rup_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único del RUP',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID universal único del RUP',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa inscrita',
    registration_number VARCHAR(50) NOT NULL COMMENT 'Número de inscripción en el RUP',
    issue_date DATE COMMENT 'Fecha de expedición del certificado',
    expiration_date DATE COMMENT 'Fecha de vencimiento del certificado',
    legal_capacity_score DECIMAL(15, 2) COMMENT 'Índice de capacidad jurídica',
    financial_capacity_score DECIMAL(15, 2) COMMENT 'Índice de capacidad financiera',
    organizational_capacity_score DECIMAL(15, 2) COMMENT 'Índice de capacidad organizacional',
    contracting_capacity_score DECIMAL(15, 2) COMMENT 'Índice de capacidad de contratación',
    rup_certificate_path VARCHAR(255) COMMENT 'Ruta del archivo digital del certificado RUP',
    status ENUM(
        'VIGENTE',
        'VENCIDO',
        'RENOVADO',
        'CANCELADO',
        'SUSPENDIDO',
        'NO_INSCRITO'
    ) DEFAULT 'VIGENTE' COMMENT 'Estado actual del registro RUP',
    remarks TEXT COMMENT 'Observaciones adicionales',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de registro',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha y hora de última modificación',
    CONSTRAINT fk_rup_company FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE
) COMMENT = 'Registro Único de Proponentes (RUP) y calificaciones';

CREATE TABLE IF NOT EXISTS financial_statements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único del reporte',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID universal de los estados financieros',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa reportante',
    fiscal_year INT NOT NULL COMMENT 'Año del ejercicio fiscal',
    currency VARCHAR(10) DEFAULT 'COP' COMMENT 'Moneda de reporte',
    current_assets DECIMAL(18, 2) COMMENT 'Activo corriente',
    inventory DECIMAL(18, 2) COMMENT 'Inventarios',
    total_assets DECIMAL(18, 2) COMMENT 'Activo total',
    current_liabilities DECIMAL(18, 2) COMMENT 'Pasivo corriente',
    financial_obligations DECIMAL(18, 2) COMMENT 'Obligaciones financieras',
    total_liabilities DECIMAL(18, 2) COMMENT 'Pasivo total',
    retained_earnings DECIMAL(18, 2) COMMENT 'Utilidades retenidas o acumuladas',
    equity DECIMAL(18, 2) COMMENT 'Patrimonio total',
    operational_income DECIMAL(18, 2) COMMENT 'Ingresos operacionales',
    operating_profit_before_tax DECIMAL(18, 2) COMMENT 'Utilidad operacional antes de impuestos',
    net_income_period DECIMAL(18, 2) COMMENT 'Utilidad neta del periodo',
    depreciation_amortization DECIMAL(18, 2) COMMENT 'Depreciación y amortización',
    financial_expenses DECIMAL(18, 2) COMMENT 'Gastos financieros',
    remarks TEXT COMMENT 'Observaciones de auditoría o revelaciones',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de registro',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha y hora de última modificación',
    CONSTRAINT fk_financial_statements_company FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE
) COMMENT = 'Estados financieros anuales (Balance y Estado de Resultados)';

CREATE TABLE IF NOT EXISTS tax_declarations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único de la declaración',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID universal de la declaración de renta',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa declarante',
    fiscal_year INT NOT NULL COMMENT 'Año gravable',
    gross_assets DECIMAL(18, 2) COMMENT 'Patrimonio bruto',
    net_assets DECIMAL(18, 2) COMMENT 'Patrimonio líquido',
    total_gross_income DECIMAL(18, 2) COMMENT 'Total ingresos brutos',
    ordinary_net_income DECIMAL(18, 2) COMMENT 'Renta líquida ordinaria',
    pre_tax_net_profit DECIMAL(18, 2) COMMENT 'Utilidad neta antes de impuestos',
    total_operating_non_operating_income DECIMAL(18, 2) COMMENT 'Total ingresos operacionales y no operacionales',
    remarks TEXT COMMENT 'Observaciones generales',
    status ENUM('BORRADOR', 'PRESENTADO') DEFAULT 'BORRADOR' COMMENT 'Estado de la declaración',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de registro',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha y hora de última modificación',
    CONSTRAINT fk_tax_declarations_company FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE
) COMMENT = 'Declaraciones de renta y complementarios anuales';

CREATE TABLE IF NOT EXISTS occupational_safety_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único del registro',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID universal único',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa propietaria del registro',
    operation_year INT UNSIGNED NOT NULL COMMENT 'Año de operación o reporte',
    fatalities INT DEFAULT 0 COMMENT 'Número de fatalidades reportadas',
    incapacitating_accidents_count INT DEFAULT 0 COMMENT 'Número de accidentes incapacitantes',
    total_incidents_count INT DEFAULT 0 COMMENT 'Número total de incidentes',
    lost_days_count INT DEFAULT 0 COMMENT 'Total de días de incapacidad',
    average_workers_count INT DEFAULT 0 COMMENT 'Número promedio de trabajadores',
    hours_worked DECIMAL(18, 2) COMMENT 'Total de horas-hombre trabajadas',
    arl_accident_certificate_date DATE COMMENT 'Fecha de expedición certificado de accidentalidad ARL',
    risk_level VARCHAR(50) COMMENT 'Nivel de riesgo asignado',
    arl_affiliation_certificate_date DATE COMMENT 'Fecha expedición certificado de afiliación ARL',
    sgsst_rating VARCHAR(50) COMMENT 'Calificación del SG-SST',
    sgsst_evaluation_date DATE COMMENT 'Fecha de evaluación del SG-SST',
    sgsst_certificate_path VARCHAR(255) COMMENT 'Ruta del archivo certificado de resultado SG-SST',
    remarks TEXT COMMENT 'Observaciones generales',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de registro',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha y hora de última modificación',
    CONSTRAINT fk_safety_records_company FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) COMMENT = 'Registros de seguridad y salud en el trabajo (SST)';

CREATE TABLE IF NOT EXISTS fuec_consecutives (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID universal del registro',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa propietaria del registro',
    type ENUM('contract', 'extract') NOT NULL,
    year SMALLINT UNSIGNED NOT NULL COMMENT 'Año para reinicio anual',
    contract_id BIGINT UNSIGNED NULL COMMENT 'ID interno de tu tabla de contratos. NULL si el type es contract',
    current_consecutive INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Último número asignado',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fuec_sequence (type, year, contract_id),
    CONSTRAINT fk_fuec_consecutives_company FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) COMMENT = 'FUEC Consecutive Numbers — Resolution 6652 of 2019';

-
-- =====================================================================================================================
-- MÓDULO 3 · TERCEROS
-- =====================================================================================================================

CREATE TABLE IF NOT EXISTS third_parties (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 único del tercero',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa propietaria del registro',
    person_type ENUM('NATURAL', 'JURIDICA') NOT NULL COMMENT 'Tipo de persona: NATURAL o JURIDICA',
    document_type_uuid CHAR(36) NOT NULL COMMENT 'UUID del tipo de documento (CC, CE, NIT, Pasaporte, etc.)',
    document_number VARCHAR(20) NOT NULL COMMENT 'Número de identificación tributaria o personal',
    nit_check_digit CHAR(1) NULL DEFAULT NULL COMMENT 'Dígito de verificación del NIT (para personas jurídicas)',
    trade_name VARCHAR(200) NULL DEFAULT NULL COMMENT 'Nombre comercial o razón social',
    company_name VARCHAR(200) NULL DEFAULT NULL COMMENT 'Razón social legal completa',
    first_name VARCHAR(100) NULL DEFAULT NULL COMMENT 'Nombre(s) propios (personas naturales)',
    last_name VARCHAR(100) NULL DEFAULT NULL COMMENT 'Apellido(s) (personas naturales)',
    email VARCHAR(100) NOT NULL COMMENT 'Correo electrónico para notificaciones y facturación DIAN',
    phone VARCHAR(20) NULL DEFAULT NULL COMMENT 'Teléfono de contacto principal',
    address VARCHAR(200) NULL DEFAULT NULL COMMENT 'Dirección de residencia o establecimiento',
    municipality_uuid CHAR(36) NOT NULL COMMENT 'UUID del municipio de ubicación geográfica',
    tax_regime ENUM('48', '49', '47', '05', '42') NOT NULL COMMENT 'Régimen tributario DIAN',
    tax_responsibility_uuid CHAR(36) NOT NULL COMMENT 'UUID de la responsabilidad fiscal principal',
    bank_account_number VARCHAR(50) NULL DEFAULT NULL COMMENT 'Número de cuenta bancaria para pagos',
    bank_account_type ENUM(
        'AHORROS',
        'CORRIENTE',
        'MONEDA_EXTRANJERA'
    ) NULL DEFAULT NULL COMMENT 'Tipo de cuenta bancaria',
    bank_name VARCHAR(100) NULL DEFAULT NULL COMMENT 'Nombre de la entidad financiera',
    cost_center_uuid CHAR(36) NULL DEFAULT NULL COMMENT 'UUID del centro de costo por defecto',
    is_customer TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Indica si actúa como cliente',
    is_supplier TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Indica si actúa como proveedor',
    is_employee TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Indica si actúa como empleado',
    is_affiliate TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Indica si es afiliado o accionista',
    is_driver TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Indica si es conductor o transportador',
    is_others TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Indica otra relación comercial',
    is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Estado de habilitación del tercero',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de creación del registro',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha y hora de última actualización',
    PRIMARY KEY (id),
    UNIQUE KEY uk_uuid (uuid),
    UNIQUE KEY uq_company_doc (
        company_uuid,
        document_type_uuid,
        document_number
    ),
    KEY idx_municipality (municipality_uuid),
    KEY idx_tax_responsibility (tax_responsibility_uuid),
    KEY idx_cost_center (cost_center_uuid),
    CONSTRAINT fk_tp_company FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE,
    CONSTRAINT fk_tp_cost_center FOREIGN KEY (cost_center_uuid) REFERENCES cost_centers (uuid) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_tp_doc_type FOREIGN KEY (document_type_uuid) REFERENCES type_of_documents (uuid) ON DELETE RESTRICT,
    CONSTRAINT fk_tp_municipality FOREIGN KEY (municipality_uuid) REFERENCES cities (uuid) ON DELETE RESTRICT,
    CONSTRAINT fk_tp_tax_resp FOREIGN KEY (tax_responsibility_uuid) REFERENCES tax_responsibilities (uuid) ON DELETE RESTRICT
) COMMENT = 'Registro maestro de terceros (clientes, proveedores, empleados) con datos tributarios DIAN';

CREATE TABLE IF NOT EXISTS employment_contracts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v7 único del contrato',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa',
    third_party_uuid CHAR(36) NOT NULL COMMENT 'UUID del empleado (tercero)',
    contract_type ENUM('TERMINO_FIJO', 'TERMINO_INDEFINIDO', 'OBRA_LABOR', 'PRESTACION_SERVICIOS', 'APRENDIZAJE') NOT NULL COMMENT 'Tipo de vinculación laboral',
    start_date DATE NOT NULL COMMENT 'Fecha de inicio del contrato',
    end_date DATE NULL DEFAULT NULL COMMENT 'Fecha de finalización (si aplica)',
    base_salary DECIMAL(18, 2) NOT NULL COMMENT 'Salario base mensual',
    salary_type ENUM('ORDINARIO', 'INTEGRAL') NOT NULL DEFAULT 'ORDINARIO' COMMENT 'Naturaleza salarial',
    transport_subsidy_applies TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Indica si aplica auxilio de transporte',
    working_hours_per_week DECIMAL(5, 2) NULL DEFAULT 47.00 COMMENT 'Horas semanales pactadas',
    status ENUM('ACTIVO', 'SUSPENDIDO', 'TERMINADO') NOT NULL DEFAULT 'ACTIVO' COMMENT 'Estado del contrato',
    termination_reason VARCHAR(255) NULL DEFAULT NULL COMMENT 'Motivo de terminación',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de creación del registro',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha y hora de última modificación',
    PRIMARY KEY (id),
    UNIQUE KEY uk_uuid (uuid),
    KEY idx_third_party_uuid (third_party_uuid),
    KEY idx_company_uuid (company_uuid),
    CONSTRAINT fk_contract_company FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE,
    CONSTRAINT fk_contract_employee FOREIGN KEY (third_party_uuid) REFERENCES third_parties (uuid) ON DELETE CASCADE
) COMMENT = 'Contratos laborales por empleado';

-- =====================================================================================================================
-- MÓDULO 4 · INVENTARIO ( X )
-- =====================================================================================================================
CREATE TABLE IF NOT EXISTS categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 de la categoría',
    name VARCHAR(100) NOT NULL COMMENT 'Nombre descriptivo',
    company_uuid CHAR(36) NOT NULL COMMENT 'Empresa propietaria',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Marca de tiempo de creación',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Marca de tiempo de última modificación',
    PRIMARY KEY (id),
    UNIQUE KEY uk_uuid (uuid),
    KEY idx_company_uuid (company_uuid),
    CONSTRAINT fk_category_company FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE
) COMMENT = 'Clasificación jerárquica de productos y servicios';

CREATE TABLE IF NOT EXISTS warehouses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 único de la bodega',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa propietaria',
    code VARCHAR(20) NOT NULL COMMENT 'Código interno de la bodega',
    name VARCHAR(100) NOT NULL COMMENT 'Nombre descriptivo de la bodega',
    address VARCHAR(200) NULL COMMENT 'Dirección física de la bodega',
    municipality_uuid CHAR(36) NULL COMMENT 'UUID del municipio de ubicación',
    is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Estado de habilitación',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Marca de tiempo de creación',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Marca de tiempo de última modificación',
    PRIMARY KEY (id),
    UNIQUE KEY uk_uuid (uuid),
    UNIQUE KEY uq_company_code (company_uuid, code),
    KEY idx_municipality_uuid (municipality_uuid),
    CONSTRAINT fk_warehouse_company FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE,
    CONSTRAINT fk_warehouse_municipality FOREIGN KEY (municipality_uuid) REFERENCES cities (uuid) ON DELETE RESTRICT
) COMMENT = 'Bodegas y almacenes de inventario por empresa';

CREATE TABLE IF NOT EXISTS products (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 global para APIs',
    product_code VARCHAR(20) NOT NULL COMMENT 'Código interno único por empresa',
    product_reference_code VARCHAR(20) NULL DEFAULT NULL COMMENT 'Código externo o del proveedor',
    product_type ENUM(
        'CONSUMIBLE',
        'SERVICIO',
        'ALMACENABLE',
        'ORDEN_DE_SERVICIO',
        'CONTRATO',
        'LLAMADO'
    ) NOT NULL COMMENT 'Naturaleza del producto',
    product_name VARCHAR(200) NOT NULL COMMENT 'Nombre comercial',
    product_description TEXT NULL COMMENT 'Ficha técnica',
    vat_taxed TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Sujeto a IVA',
    vat_percentage DECIMAL(5, 2) NULL DEFAULT NULL COMMENT 'Tarifa de IVA',
    manages_inventory TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Genera movimientos kardex',
    category_uuid CHAR(36) NULL DEFAULT NULL COMMENT 'Categoría comercial',
    unit_of_measure_uuid CHAR(36) NOT NULL COMMENT 'Unidad de medida base',
    company_uuid CHAR(36) NOT NULL COMMENT 'Empresa propietaria',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Marca de tiempo de creación',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Marca de tiempo de última modificación',
    PRIMARY KEY (id),
    UNIQUE KEY uk_uuid (uuid),
    UNIQUE KEY uq_company_code (company_uuid, product_code),
    KEY idx_category_uuid (category_uuid),
    KEY idx_unit_uuid (unit_of_measure_uuid),
    KEY idx_company_uuid (company_uuid),
    CONSTRAINT fk_product_category FOREIGN KEY (category_uuid) REFERENCES categories (uuid) ON DELETE SET NULL,
    CONSTRAINT fk_product_company FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE,
    CONSTRAINT fk_product_unit FOREIGN KEY (unit_of_measure_uuid) REFERENCES measurement_units (uuid) ON DELETE RESTRICT
) COMMENT = 'Maestro central de productos y servicios';

CREATE TABLE IF NOT EXISTS product_variants (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 único de la variante',
    product_uuid CHAR(36) NOT NULL COMMENT 'UUID del producto padre',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa propietaria',
    sku VARCHAR(50) NOT NULL COMMENT 'Código de referencia único de la variante (SKU)',
    variant_name VARCHAR(200) NULL COMMENT 'Nombre descriptivo de la variante (ej: Talla M, Color Azul)',
    barcode VARCHAR(50) NULL COMMENT 'Código de barras EAN/GTIN',
    purchase_price DECIMAL(18, 2) NULL DEFAULT 0.00 COMMENT 'Precio de compra negociado',
    sale_price DECIMAL(18, 2) NULL DEFAULT 0.00 COMMENT 'Precio de venta sugerido',
    is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Estado de habilitación',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Marca de tiempo de creación',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Marca de tiempo de última modificación',
    PRIMARY KEY (id),
    UNIQUE KEY uk_uuid (uuid),
    UNIQUE KEY uq_company_sku (company_uuid, sku),
    KEY idx_product_uuid (product_uuid),
    CONSTRAINT fk_variant_product FOREIGN KEY (product_uuid) REFERENCES products (uuid) ON DELETE CASCADE,
    CONSTRAINT fk_variant_company FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE
) COMMENT = 'Variantes de productos (presentaciones, tallas, colores, etc.)';

-- =====================================================================================================================
-- MÓDULO 5 · CONTABILIDAD ( X )
-- =====================================================================================================================
CREATE TABLE IF NOT EXISTS chart_of_accounts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 único de la cuenta en la empresa',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa propietaria',
    puc_commercial_uuid CHAR(36) NULL COMMENT 'UUID del PUC comercial base de origen',
    code VARCHAR(10) NOT NULL COMMENT 'Código contable interno de la empresa',
    level TINYINT NOT NULL COMMENT 'Nivel contable: 1(Clase), 2(Grupo), 3(Cuenta), 4(Subcuenta), 5(Auxiliar)',
    description VARCHAR(300) NOT NULL COMMENT 'Descripción personalizada de la cuenta',
    nature CHAR(1) NOT NULL COMMENT 'Naturaleza: D (Débito) o C (Crédito)',
    parent_code VARCHAR(10) NULL COMMENT 'Código de la cuenta padre para estructura jerárquica',
    is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Indica si la cuenta está habilitada para registrar movimientos',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de creación del registro',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha y hora de última modificación',
    PRIMARY KEY (id),
    UNIQUE KEY uk_uuid (uuid),
    UNIQUE KEY uk_company_code (company_uuid, code),
    INDEX idx_parent (company_uuid, parent_code),
    CONSTRAINT fk_coa_company FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE,
    CONSTRAINT fk_coa_puc FOREIGN KEY (puc_commercial_uuid) REFERENCES puc_commercial (uuid) ON DELETE RESTRICT,
    CONSTRAINT fk_coa_parent FOREIGN KEY (company_uuid, parent_code) REFERENCES chart_of_accounts (company_uuid, code) ON DELETE RESTRICT
) COMMENT = 'Plan de cuentas contables personalizado por empresa';

CREATE TABLE IF NOT EXISTS cost_centers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 único del centro de costo',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa propietaria',
    code VARCHAR(20) NOT NULL COMMENT 'Código interno del centro de costo',
    name VARCHAR(150) NOT NULL COMMENT 'Nombre descriptivo del centro de costo',
    description TEXT NULL COMMENT 'Descripción funcional o alcance',
    parent_code VARCHAR(20) NULL COMMENT 'Código del centro de costo padre',
    level TINYINT DEFAULT 1 COMMENT 'Nivel jerárquico en la estructura',
    is_active TINYINT(1) DEFAULT 1 COMMENT 'Indica si el centro de costo está activo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de creación del registro',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha y hora de última actualización',
    PRIMARY KEY (id),
    UNIQUE KEY uk_uuid (uuid),
    UNIQUE KEY uk_company_code (company_uuid, code),
    INDEX idx_parent (company_uuid, parent_code),
    CONSTRAINT fk_cc_company FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE,
    CONSTRAINT fk_cc_parent FOREIGN KEY (company_uuid, parent_code) REFERENCES cost_centers (company_uuid, code) ON DELETE RESTRICT
) COMMENT = 'Estructura de centros de costo por empresa';

CREATE TABLE IF NOT EXISTS voucher_types (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 único del tipo de comprobante',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa propietaria',
    code VARCHAR(10) NOT NULL COMMENT 'Código corto del comprobante (CE, FC, EG, ND, NC, etc.)',
    name VARCHAR(100) NOT NULL COMMENT 'Nombre descriptivo del tipo de comprobante',
    description VARCHAR(255) NULL COMMENT 'Descripción o propósito contable',
    is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Estado de habilitación',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de creación del registro',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha y hora de última modificación',
    PRIMARY KEY (id),
    UNIQUE KEY uk_uuid (uuid),
    UNIQUE KEY uk_company_code (company_uuid, code),
    CONSTRAINT fk_vt_company FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE
) COMMENT = 'Tipos de comprobantes contables por empresa (CE, FC, EG, ND, NC, etc.)';

CREATE TABLE IF NOT EXISTS banks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 único del banco',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa que registra el banco',
    asobancaria_code VARCHAR(10) NOT NULL COMMENT 'Código oficial asignado por Asobancaria Colombia',
    name VARCHAR(150) NOT NULL COMMENT 'Nombre comercial de la entidad financiera',
    tax_id VARCHAR(20) NULL COMMENT 'NIT del banco',
    is_active TINYINT(1) DEFAULT 1 COMMENT 'Indica si el banco está habilitado para transacciones',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de creación del registro',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha y hora de última actualización',
    PRIMARY KEY (id),
    UNIQUE KEY uk_uuid (uuid),
    UNIQUE KEY uk_company_asobancaria (
        company_uuid,
        asobancaria_code
    ),
    CONSTRAINT fk_bank_company FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE
) COMMENT = 'Catálogo de bancos nacionales operativos';

CREATE TABLE IF NOT EXISTS bank_accounts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 único de la cuenta bancaria',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa propietaria',
    bank_id BIGINT UNSIGNED NOT NULL COMMENT 'ID del banco asociado',
    account_number VARCHAR(30) NOT NULL COMMENT 'Número de cuenta o IBAN',
    type ENUM(
        'AHORROS',
        'CORRIENTE',
        'CDT',
        'FIDUCIA',
        'MONEDA_EXTRANJERA'
    ) NOT NULL COMMENT 'Tipo de producto bancario',
    currency CHAR(3) DEFAULT 'COP' COMMENT 'Código ISO 4217 de la moneda',
    accounting_code VARCHAR(10) NOT NULL COMMENT 'Código contable alineado con el plan de cuentas',
    book_balance DECIMAL(18, 2) DEFAULT 0.00 COMMENT 'Saldo según libros contables',
    bank_balance DECIMAL(18, 2) DEFAULT 0.00 COMMENT 'Saldo según extracto bancario',
    is_active TINYINT(1) DEFAULT 1 COMMENT 'Indica si la cuenta bancaria está activa',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de creación del registro',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha y hora de última modificación',
    PRIMARY KEY (id),
    UNIQUE KEY uk_uuid (uuid),
    UNIQUE KEY uk_company_acc (
        company_uuid,
        account_number,
        bank_id
    ),
    CONSTRAINT fk_ba_company FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE,
    CONSTRAINT fk_ba_bank FOREIGN KEY (bank_id) REFERENCES banks (id) ON DELETE RESTRICT,
    CONSTRAINT fk_ba_account FOREIGN KEY (company_uuid, accounting_code) REFERENCES chart_of_accounts (company_uuid, code) ON DELETE RESTRICT
) COMMENT = 'Cuentas bancarias operativas y conciliables por empresa';

CREATE TABLE IF NOT EXISTS accounting_periods (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa propietaria del periodo',
    year SMALLINT NOT NULL COMMENT 'Año fiscal',
    month TINYINT NOT NULL COMMENT 'Mes del periodo (1-12)',
    name VARCHAR(60) NOT NULL COMMENT 'Nombre descriptivo (ej: Enero 2026)',
    start_date DATE NOT NULL COMMENT 'Fecha de inicio del periodo contable',
    end_date DATE NOT NULL COMMENT 'Fecha de fin del periodo contable',
    status ENUM(
        'ABIERTO',
        'CERRADO',
        'BLOQUEADO'
    ) DEFAULT 'ABIERTO' COMMENT 'Estado del periodo para registro contable',
    closing_date DATETIME NULL COMMENT 'Fecha y hora en que se cerró el periodo',
    closed_by_user_id INT UNSIGNED NULL COMMENT 'ID del usuario que realizó el cierre',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de creación del registro',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha y hora de última actualización',
    PRIMARY KEY (id),
    UNIQUE KEY uk_company_period (company_uuid, year, month),
    INDEX idx_status (status),
    CONSTRAINT fk_period_company FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE
) COMMENT = 'Periodos contables mensuales por empresa';

CREATE TABLE IF NOT EXISTS accounting_vouchers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 único del comprobante',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa',
    voucher_type_uuid CHAR(36) NOT NULL COMMENT 'UUID del tipo de comprobante (CE, FC, EG, ND, NC, etc.)',
    period_id BIGINT UNSIGNED NOT NULL COMMENT 'ID del periodo contable asociado',
    number INT UNSIGNED NOT NULL COMMENT 'Consecutivo interno del comprobante',
    document_date DATE NOT NULL COMMENT 'Fecha de elaboración del comprobante',
    reference VARCHAR(100) NULL COMMENT 'Referencia externa (ej: No. factura proveedor)',
    description VARCHAR(300) NULL COMMENT 'Descripción general del asiento contable',
    total_debits DECIMAL(18, 2) NOT NULL DEFAULT 0.00 COMMENT 'Suma total de los débitos',
    total_credits DECIMAL(18, 2) NOT NULL DEFAULT 0.00 COMMENT 'Suma total de los créditos',
    is_posted TINYINT(1) DEFAULT 0 COMMENT 'Indica si el comprobante ya fue contabilizado y afectó saldos',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de creación del registro',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha y hora de última actualización',
    PRIMARY KEY (id),
    UNIQUE KEY uk_uuid (uuid),
    UNIQUE KEY uk_company_type_number (
        company_uuid,
        voucher_type_uuid,
        number
    ),
    CONSTRAINT fk_vch_company FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE,
    CONSTRAINT fk_vch_period FOREIGN KEY (period_id) REFERENCES accounting_periods (id) ON DELETE RESTRICT,
    CONSTRAINT fk_vch_voucher_type FOREIGN KEY (voucher_type_uuid) REFERENCES voucher_types (uuid) ON DELETE RESTRICT
) COMMENT = 'Cabecera de comprobantes contables y asientos diarios';

CREATE TABLE IF NOT EXISTS accounting_entries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    voucher_id BIGINT UNSIGNED NOT NULL COMMENT 'ID del comprobante al que pertenece la línea',
    line SMALLINT NOT NULL COMMENT 'Número de línea dentro del comprobante',
    account_uuid CHAR(36) NOT NULL COMMENT 'UUID de la cuenta contable afectada',
    cost_center_uuid CHAR(36) NULL COMMENT 'UUID del centro de costo aplicable',
    third_party_uuid CHAR(36) NULL COMMENT 'UUID del tercero relacionado en la línea',
    description VARCHAR(300) NULL COMMENT 'Detalle específico del movimiento',
    debit DECIMAL(18, 2) NOT NULL DEFAULT 0.00 COMMENT 'Valor debitado en la línea',
    credit DECIMAL(18, 2) NOT NULL DEFAULT 0.00 COMMENT 'Valor acreditado en la línea',
    tax_base DECIMAL(18, 2) NULL COMMENT 'Base gravable para el cálculo de impuestos',
    tax_uuid CHAR(36) NULL COMMENT 'UUID del impuesto aplicado en la línea',
    withholding_uuid CHAR(36) NULL COMMENT 'UUID de la retención aplicada en la línea',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de creación del registro',
    PRIMARY KEY (id),
    UNIQUE KEY uk_voucher_line (voucher_id, line),
    INDEX idx_account (account_uuid),
    INDEX idx_third_party (third_party_uuid),
    CONSTRAINT fk_entry_voucher FOREIGN KEY (voucher_id) REFERENCES accounting_vouchers (id) ON DELETE CASCADE,
    CONSTRAINT fk_entry_account FOREIGN KEY (account_uuid) REFERENCES chart_of_accounts (uuid) ON DELETE RESTRICT,
    CONSTRAINT fk_entry_cost_center FOREIGN KEY (cost_center_uuid) REFERENCES cost_centers (uuid) ON DELETE SET NULL,
    CONSTRAINT fk_entry_third_party FOREIGN KEY (third_party_uuid) REFERENCES third_parties (uuid) ON DELETE SET NULL,
    CONSTRAINT fk_entry_tax FOREIGN KEY (tax_uuid) REFERENCES tax_types (uuid) ON DELETE SET NULL,
    CONSTRAINT fk_entry_withholding FOREIGN KEY (withholding_uuid) REFERENCES withholdings (uuid) ON DELETE SET NULL
) COMMENT = 'Líneas de asiento contable (movimientos débitos/créditos)';

CREATE TABLE IF NOT EXISTS account_balances (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    period_id BIGINT UNSIGNED NOT NULL COMMENT 'ID del periodo contable',
    account_uuid CHAR(36) NOT NULL COMMENT 'UUID de la cuenta contable',
    cost_center_uuid CHAR(36) NULL COMMENT 'UUID del centro de costo (NULL = consolidado)',
    third_party_uuid CHAR(36) NULL COMMENT 'UUID del tercero (NULL = consolidado)',
    initial_balance DECIMAL(18, 2) DEFAULT 0.00 COMMENT 'Saldo arrastrado del periodo anterior',
    total_debits DECIMAL(18, 2) DEFAULT 0.00 COMMENT 'Suma de débitos del periodo',
    total_credits DECIMAL(18, 2) DEFAULT 0.00 COMMENT 'Suma de créditos del periodo',
    final_balance DECIMAL(18, 2) DEFAULT 0.00 COMMENT 'Saldo resultante para el periodo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de cálculo/creación',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha y hora de última actualización',
    PRIMARY KEY (id),
    UNIQUE KEY uk_balance_unique (
        period_id,
        account_uuid,
        cost_center_uuid,
        third_party_uuid
    ),
    CONSTRAINT fk_bal_period FOREIGN KEY (period_id) REFERENCES accounting_periods (id) ON DELETE CASCADE,
    CONSTRAINT fk_bal_account FOREIGN KEY (account_uuid) REFERENCES chart_of_accounts (uuid) ON DELETE CASCADE,
    CONSTRAINT fk_bal_cost_center FOREIGN KEY (cost_center_uuid) REFERENCES cost_centers (uuid) ON DELETE SET NULL,
    CONSTRAINT fk_bal_third_party FOREIGN KEY (third_party_uuid) REFERENCES third_parties (uuid) ON DELETE SET NULL
) COMMENT = 'Saldos acumulados por cuenta, centro de costo y tercero';

CREATE TABLE IF NOT EXISTS budgets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 único del presupuesto',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa',
    year SMALLINT NOT NULL COMMENT 'Año fiscal del presupuesto',
    account_uuid CHAR(36) NOT NULL COMMENT 'UUID de la cuenta presupuestada',
    cost_center_uuid CHAR(36) NULL COMMENT 'UUID del centro de costo (NULL = global)',
    month TINYINT NULL COMMENT 'Mes presupuestado (NULL = anual)',
    budgeted_amount DECIMAL(18, 2) NOT NULL DEFAULT 0.00 COMMENT 'Monto presupuestado en COP',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de creación del registro',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha y hora de última actualización',
    PRIMARY KEY (id),
    UNIQUE KEY uk_uuid (uuid),
    UNIQUE KEY uk_budget_unique (
        company_uuid,
        year,
        month,
        account_uuid,
        cost_center_uuid
    ),
    CONSTRAINT fk_budget_company FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE,
    CONSTRAINT fk_budget_account FOREIGN KEY (account_uuid) REFERENCES chart_of_accounts (uuid) ON DELETE RESTRICT,
    CONSTRAINT fk_budget_cc FOREIGN KEY (cost_center_uuid) REFERENCES cost_centers (uuid) ON DELETE SET NULL
) COMMENT = 'Presupuestos operativos por cuenta, periodo y centro de costo';

CREATE TABLE IF NOT EXISTS bank_reconciliations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 único de la conciliación',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa propietaria',
    bank_account_uuid CHAR(36) NOT NULL COMMENT 'UUID de la cuenta bancaria conciliada',
    period_id BIGINT UNSIGNED NOT NULL COMMENT 'ID del periodo contable asociado',
    statement_date DATE NOT NULL COMMENT 'Fecha del extracto bancario',
    statement_balance DECIMAL(18, 2) NOT NULL COMMENT 'Saldo reportado por el banco en el extracto',
    book_balance DECIMAL(18, 2) NOT NULL COMMENT 'Saldo según libros contables a la fecha',
    deposits_in_transit DECIMAL(18, 2) DEFAULT 0.00 COMMENT 'Consignaciones en tránsito',
    outstanding_checks DECIMAL(18, 2) DEFAULT 0.00 COMMENT 'Cheques girados no cobrados',
    bank_fees DECIMAL(18, 2) DEFAULT 0.00 COMMENT 'Comisiones bancarias pendientes de registro',
    interest_earned DECIMAL(18, 2) DEFAULT 0.00 COMMENT 'Intereses a favor pendientes de registro',
    other_adjustments DECIMAL(18, 2) DEFAULT 0.00 COMMENT 'Otros ajustes no identificados automáticamente',
    reconciled_balance DECIMAL(18, 2) NOT NULL COMMENT 'Saldo conciliado final',
    is_balanced TINYINT(1) DEFAULT 0 COMMENT 'Indica si la conciliación cuadra correctamente',
    reconciled_by_user_id INT UNSIGNED NULL COMMENT 'ID del usuario que realizó la conciliación',
    reconciled_at DATETIME NULL COMMENT 'Fecha y hora de cierre de la conciliación',
    observations TEXT NULL COMMENT 'Notas sobre diferencias o ajustes manuales',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de creación del registro',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha y hora de última actualización',
    PRIMARY KEY (id),
    UNIQUE KEY uk_uuid (uuid),
    UNIQUE KEY uk_account_period (
        company_uuid,
        bank_account_uuid,
        period_id
    ),
    CONSTRAINT fk_rec_company FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE,
    CONSTRAINT fk_rec_account FOREIGN KEY (bank_account_uuid) REFERENCES bank_accounts (uuid) ON DELETE RESTRICT,
    CONSTRAINT fk_rec_period FOREIGN KEY (period_id) REFERENCES accounting_periods (id) ON DELETE RESTRICT
) COMMENT = 'Registro de conciliaciones bancarias mensuales por cuenta';

CREATE TABLE IF NOT EXISTS bank_reconciliation_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    reconciliation_id BIGINT UNSIGNED NOT NULL COMMENT 'ID de la conciliación padre',
    line SMALLINT NOT NULL COMMENT 'Número de línea del detalle',
    item_type ENUM(
        'DEPOSITO_TRANSITO',
        'CHEQUE_PENDIENTE',
        'COMISION_BANCO',
        'INTERES',
        'AJUSTE_MANUAL'
    ) NOT NULL COMMENT 'Tipo de partida de conciliación',
    reference VARCHAR(100) NULL COMMENT 'Referencia del movimiento bancario',
    item_date DATE NOT NULL COMMENT 'Fecha del movimiento bancario',
    description VARCHAR(300) NOT NULL COMMENT 'Descripción detallada de la partida',
    amount DECIMAL(18, 2) NOT NULL COMMENT 'Valor de la partida (positivo para abonos, negativo para cargos)',
    is_matched TINYINT(1) DEFAULT 0 COMMENT 'Indica si la partida ya se cruzó con un asiento contable',
    voucher_id BIGINT UNSIGNED NULL COMMENT 'ID del comprobante generado para ajustar esta partida',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de registro',
    PRIMARY KEY (id),
    UNIQUE KEY uk_rec_line (reconciliation_id, line),
    CONSTRAINT fk_rec_item_parent FOREIGN KEY (reconciliation_id) REFERENCES bank_reconciliations (id) ON DELETE CASCADE,
    CONSTRAINT fk_rec_item_voucher FOREIGN KEY (voucher_id) REFERENCES accounting_vouchers (id) ON DELETE SET NULL
) COMMENT = 'Detalle de partidas en conciliaciones bancarias';

-- =====================================================================================================================
-- MÓDULO 6 · CONFIGURACIONES — FACTURACIÓN ELECTRÓNICA ( X )
-- =====================================================================================================================
CREATE TABLE IF NOT EXISTS billing_providers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 del proveedor tecnológico',
    name VARCHAR(100) NOT NULL COMMENT 'Razón social o nombre comercial',
    server_url VARCHAR(200) NOT NULL COMMENT 'Endpoint base de la API de facturación',
    client_id VARCHAR(200) NOT NULL COMMENT 'Credencial de cliente OAuth/API',
    client_secret VARCHAR(255) NOT NULL COMMENT 'Clave secreta (debe cifrarse en producción)',
    email VARCHAR(100) NOT NULL COMMENT 'Contacto técnico o administrativo',
    password_hash VARCHAR(255) NOT NULL COMMENT 'Hash seguro de acceso',
    access_token TEXT NULL COMMENT 'Token de acceso vigente',
    refresh_token TEXT NULL COMMENT 'Token de renovación',
    start_date DATE NOT NULL COMMENT 'Fecha de activación del servicio',
    end_date DATE NOT NULL COMMENT 'Fecha de expiración del contrato o licencia',
    company_uuid CHAR(36) NULL DEFAULT NULL COMMENT 'Empresa asignada (NULL si es multi-tenant)',
    description TEXT NULL COMMENT 'Notas técnicas o configuración específica',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de creación del registro',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha y hora de última modificación',
    PRIMARY KEY (id),
    UNIQUE KEY uuid(uuid),
    KEY idx_company_uuid (company_uuid),
    CONSTRAINT fk_billing_provider_company FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE SET NULL
) COMMENT = 'Proveedores tecnológicos para facturación electrónica y firma digital';

CREATE TABLE IF NOT EXISTS billing_resolutions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 de la resolución',
    resolution_type_code VARCHAR(10) NOT NULL COMMENT 'Tipo de documento electrónico autorizado',
    resolution_number VARCHAR(50) NULL DEFAULT NULL COMMENT 'Número oficial otorgado por la DIAN',
    invoice_start_number BIGINT UNSIGNED NOT NULL COMMENT 'Numeración inicial del rango autorizado',
    last_consecutive BIGINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Último consecutivo emitido',
    invoice_prefix VARCHAR(10) NOT NULL COMMENT 'Prefijo alfanumérico de la resolución',
    start_date DATE NOT NULL COMMENT 'Fecha de inicio de vigencia',
    end_date DATE NOT NULL COMMENT 'Fecha límite de uso',
    billing_provider_uuid CHAR(36) NULL DEFAULT NULL COMMENT 'Proveedor tecnológico encargado',
    description TEXT NULL COMMENT 'Condiciones especiales o notas de uso',
    company_uuid CHAR(36) NOT NULL COMMENT 'Empresa propietaria de la resolución',
    is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Habilitación para emisión de documentos',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de creación del registro',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha y hora de última modificación',
    PRIMARY KEY (id),
    UNIQUE KEY uuid(uuid),
    KEY idx_company_uuid (company_uuid),
    KEY idx_resolution_type_code (resolution_type_code),
    KEY idx_billing_provider_uuid (billing_provider_uuid),
    CONSTRAINT fk_resolution_company FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE,
    CONSTRAINT fk_resolution_type FOREIGN KEY (resolution_type_code) REFERENCES billing_resolution_types (code) ON DELETE RESTRICT,
    CONSTRAINT fk_resolution_billing_provider FOREIGN KEY (billing_provider_uuid) REFERENCES billing_providers (uuid) ON DELETE SET NULL
) COMMENT = 'Resoluciones de facturación electrónica por empresa y tipo de documento';

-- =====================================================================================================================
-- MÓDULO 7 · FACTURACIÓN COMERCIAL (SIN Y CON FE) ( X )
-- =====================================================================================================================
CREATE TABLE IF NOT EXISTS fiscal_documents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 único del documento fiscal',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa emisora',
    third_party_uuid CHAR(36) NOT NULL COMMENT 'UUID del cliente o proveedor',
    doc_type ENUM(
        'FACTURA_VENTA',
        'NOTA_CREDITO',
        'NOTA_DEBITO',
        'FACTURA_COMPRA',
        'RECIBO_CAJA',
        'OTRO'
    ) NOT NULL COMMENT 'Tipo de documento según normativa DIAN',
    prefix VARCHAR(5) NULL COMMENT 'Prefijo de facturación asignado por resolución',
    consecutive INT UNSIGNED NOT NULL COMMENT 'Numeración consecutiva del documento',
    dian_number VARCHAR(30) NULL COMMENT 'CUFE (Factura) o CUDE (Nota) generado por la DIAN',
    issue_date DATE NOT NULL COMMENT 'Fecha de expedición del documento',
    due_date DATE NULL COMMENT 'Fecha de vencimiento o pago',
    payment_method_id BIGINT UNSIGNED NULL COMMENT 'ID del medio de pago DIAN utilizado',
    subtotal DECIMAL(18, 2) DEFAULT 0.00 COMMENT 'Subtotal antes de impuestos y descuentos',
    total_discount DECIMAL(18, 2) DEFAULT 0.00 COMMENT 'Suma de descuentos aplicados',
    vat_base DECIMAL(18, 2) DEFAULT 0.00 COMMENT 'Base gravable para IVA',
    vat_amount DECIMAL(18, 2) DEFAULT 0.00 COMMENT 'Monto total de IVA',
    inc_base DECIMAL(18, 2) DEFAULT 0.00 COMMENT 'Base gravable para INC',
    inc_amount DECIMAL(18, 2) DEFAULT 0.00 COMMENT 'Monto total de INC',
    ica_base DECIMAL(18, 2) DEFAULT 0.00 COMMENT 'Base gravable para ICA',
    ica_amount DECIMAL(18, 2) DEFAULT 0.00 COMMENT 'Monto total de ICA',
    withholding_tax_amount DECIMAL(18, 2) DEFAULT 0.00 COMMENT 'Total retenciones en la fuente',
    iva_withholding_amount DECIMAL(18, 2) DEFAULT 0.00 COMMENT 'Total retención de IVA',
    ica_withholding_amount DECIMAL(18, 2) DEFAULT 0.00 COMMENT 'Total retención de ICA',
    total DECIMAL(18, 2) NOT NULL DEFAULT 0.00 COMMENT 'Total a pagar o cobrar',
    dian_status ENUM(
        'BORRADOR',
        'ENVIADO',
        'ACEPTADO',
        'RECHAZADO',
        'ANULADO'
    ) DEFAULT 'BORRADOR' COMMENT 'Estado de validación ante la DIAN',
    dian_xml LONGTEXT NULL COMMENT 'XML original enviado/recibido de la DIAN',
    qr_code TEXT NULL COMMENT 'Código QR generado para la factura electrónica',
    observations TEXT NULL COMMENT 'Notas o condiciones comerciales',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de creación del registro',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha y hora de última actualización',
    PRIMARY KEY (id),
    UNIQUE KEY uk_uuid (uuid),
    UNIQUE KEY uk_doc_prefix_consecutive (
        company_uuid,
        prefix,
        consecutive
    ),
    CONSTRAINT fk_fiscal_company FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE,
    CONSTRAINT fk_fiscal_third FOREIGN KEY (third_party_uuid) REFERENCES third_parties (uuid) ON DELETE RESTRICT,
    CONSTRAINT fk_fiscal_payment FOREIGN KEY (payment_method_id) REFERENCES payment_methods (id) ON DELETE SET NULL
) COMMENT = 'Documentos fiscales electrónicos conforme a Resolución DIAN';

CREATE TABLE IF NOT EXISTS fiscal_document_details (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    document_id BIGINT UNSIGNED NOT NULL COMMENT 'ID del documento fiscal padre',
    line SMALLINT NOT NULL COMMENT 'Número de línea del detalle',
    description VARCHAR(500) NOT NULL COMMENT 'Descripción del producto o servicio',
    unit_measure VARCHAR(20) NULL COMMENT 'Unidad de medida DIAN (UN, KG, HR, GL, etc.)',
    quantity DECIMAL(14, 4) NOT NULL DEFAULT 1.0000 COMMENT 'Cantidad facturada',
    unit_price DECIMAL(18, 4) NOT NULL COMMENT 'Precio unitario antes de impuestos',
    discount_pct DECIMAL(5, 2) DEFAULT 0.00 COMMENT 'Porcentaje de descuento aplicado',
    discount_amount DECIMAL(18, 2) DEFAULT 0.00 COMMENT 'Monto del descuento',
    line_subtotal DECIMAL(18, 2) NOT NULL COMMENT 'Subtotal de la línea',
    tax_uuid CHAR(36) NULL COMMENT 'UUID del impuesto aplicado en la línea',
    vat_rate DECIMAL(5, 2) DEFAULT 0.00 COMMENT 'Tarifa IVA aplicada en la línea',
    vat_amount DECIMAL(18, 2) DEFAULT 0.00 COMMENT 'Monto IVA de la línea',
    line_total DECIMAL(18, 2) NOT NULL COMMENT 'Total de la línea con impuestos',
    account_uuid CHAR(36) NULL COMMENT 'UUID de la cuenta contable para generación automática del asiento',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de creación del registro',
    PRIMARY KEY (id),
    UNIQUE KEY uk_doc_line (document_id, line),
    CONSTRAINT fk_fiscal_detail_doc FOREIGN KEY (document_id) REFERENCES fiscal_documents (id) ON DELETE CASCADE,
    CONSTRAINT fk_fiscal_detail_tax FOREIGN KEY (tax_uuid) REFERENCES tax_types (uuid) ON DELETE SET NULL,
    CONSTRAINT fk_fiscal_detail_account FOREIGN KEY (account_uuid) REFERENCES chart_of_accounts (uuid) ON DELETE SET NULL
) COMMENT = 'Detalle de ítems en documentos fiscales DIAN';

CREATE TABLE IF NOT EXISTS commercial_invoices (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 único de la factura comercial',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa emisora',
    invoice_type ENUM(
        'COTIZACION',
        'REMISION',
        'FACTURA_VENTA'
    ) NOT NULL COMMENT 'Tipo de documento comercial',
    status ENUM(
        'BORRADOR',
        'ENVIADA',
        'PAGADA',
        'PAGADA_PARCIAL',
        'ANULADA',
        'CONVERTIDA_FISCAL'
    ) DEFAULT 'BORRADOR' COMMENT 'Estado comercial del documento',
    invoice_number VARCHAR(30) NOT NULL COMMENT 'Consecutivo interno comercial',
    issue_date DATE NOT NULL COMMENT 'Fecha de emisión',
    due_date DATE NULL COMMENT 'Fecha límite de pago',
    subtotal DECIMAL(18, 2) NOT NULL DEFAULT 0.00 COMMENT 'Subtotal comercial',
    total_discount DECIMAL(18, 2) NOT NULL DEFAULT 0.00 COMMENT 'Total descuentos',
    total_tax DECIMAL(18, 2) NOT NULL DEFAULT 0.00 COMMENT 'Total impuestos calculados',
    total_withholding DECIMAL(18, 2) NOT NULL DEFAULT 0.00 COMMENT 'Total retenciones estimadas',
    invoice_total DECIMAL(18, 2) NOT NULL DEFAULT 0.00 COMMENT 'Total a pagar',
    amount_paid DECIMAL(18, 2) NOT NULL DEFAULT 0.00 COMMENT 'Monto efectivamente recibido',
    third_party_uuid CHAR(36) NOT NULL COMMENT 'UUID del cliente',
    created_by_uuid CHAR(36) NULL COMMENT 'UUID del usuario que creó el documento',
    fiscal_document_id BIGINT UNSIGNED NULL COMMENT 'ID del documento fiscal DIAN generado (NULL = sin FE)',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de creación del registro',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha y hora de última actualización',
    PRIMARY KEY (id),
    UNIQUE KEY uk_uuid (uuid),
    INDEX idx_number (invoice_number),
    CONSTRAINT fk_ci_company FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE,
    CONSTRAINT fk_ci_third FOREIGN KEY (third_party_uuid) REFERENCES third_parties (uuid) ON DELETE RESTRICT,
    CONSTRAINT fk_ci_fiscal FOREIGN KEY (fiscal_document_id) REFERENCES fiscal_documents (id) ON DELETE SET NULL
) COMMENT = 'Documentos comerciales previos a la facturación electrónica';

CREATE TABLE IF NOT EXISTS commercial_invoice_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 único del ítem',
    invoice_uuid CHAR(36) NOT NULL COMMENT 'UUID de la factura comercial padre',
    description VARCHAR(250) NOT NULL COMMENT 'Descripción del bien o servicio',
    quantity DECIMAL(10, 2) NOT NULL COMMENT 'Cantidad cotizada/facturada',
    unit_price DECIMAL(18, 2) NOT NULL COMMENT 'Precio unitario',
    tax_rate DECIMAL(5, 2) NOT NULL DEFAULT 0.00 COMMENT 'Porcentaje de impuesto aplicado',
    tax_amount DECIMAL(18, 2) NOT NULL DEFAULT 0.00 COMMENT 'Monto impuesto calculado',
    discount_amount DECIMAL(18, 2) NOT NULL DEFAULT 0.00 COMMENT 'Monto descuento aplicado',
    line_total DECIMAL(18, 2) NOT NULL COMMENT 'Total línea con impuestos',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de creación del registro',
    PRIMARY KEY (id),
    UNIQUE KEY uk_uuid (uuid),
    CONSTRAINT fk_ci_item_invoice FOREIGN KEY (invoice_uuid) REFERENCES commercial_invoices (uuid) ON DELETE CASCADE
) COMMENT = 'Detalle de productos/servicios en facturas comerciales';

CREATE TABLE IF NOT EXISTS commercial_invoice_payments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 único del pago',
    invoice_uuid CHAR(36) NOT NULL COMMENT 'UUID de la factura comercial',
    amount DECIMAL(18, 2) NOT NULL COMMENT 'Monto recibido en el abono',
    payment_date DATE NOT NULL COMMENT 'Fecha efectiva del pago',
    payment_method VARCHAR(50) NOT NULL COMMENT 'Medio de pago utilizado',
    reference VARCHAR(100) NULL COMMENT 'Número de transferencia, cheque o comprobante',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de registro del pago',
    PRIMARY KEY (id),
    UNIQUE KEY uk_uuid (uuid),
    CONSTRAINT fk_ci_payment_invoice FOREIGN KEY (invoice_uuid) REFERENCES commercial_invoices (uuid) ON DELETE CASCADE
) COMMENT = 'Historial de pagos y abonos a facturas comerciales';

CREATE TABLE IF NOT EXISTS commercial_invoice_item_withholdings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 único de la retención comercial',
    invoice_item_uuid CHAR(36) NOT NULL COMMENT 'UUID del ítem de factura al que aplica',
    withholding_type VARCHAR(50) NOT NULL COMMENT 'Tipo de retención aplicada',
    withholding_rate DECIMAL(5, 2) NOT NULL COMMENT 'Porcentaje aplicado en la línea',
    amount DECIMAL(18, 2) NOT NULL COMMENT 'Monto retenido calculado',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de cálculo/registro',
    PRIMARY KEY (id),
    UNIQUE KEY uk_uuid (uuid),
    INDEX idx_item_uuid (invoice_item_uuid),
    CONSTRAINT fk_ci_wh_item FOREIGN KEY (invoice_item_uuid) REFERENCES commercial_invoice_items (uuid) ON DELETE CASCADE
) COMMENT = 'Retenciones calculadas por línea en documentos comerciales';

CREATE TABLE IF NOT EXISTS affiliate_admin_charges (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID v4 único del cargo administrativo',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa asociada (tenant)',
    payment_reference VARCHAR(50) NOT NULL COMMENT 'Referencia o número consecutivo de cobro',
    vehicle_uuid CHAR(36) NOT NULL COMMENT 'UUID del vehículo asociado al afiliado',
    charge_type ENUM(
        'CUOTA_ADMINISTRACION',
        'PAGO_MENSUALIDAD',
        'PAGO_CUPO'
    ) NOT NULL COMMENT 'Tipo de cargo administrativo aplicado',
    payment_method ENUM(
        'EFECTIVO',
        'TRANSFERENCIA',
        'CHEQUE',
        'TARJETA',
        'OTRO',
        'CORTESIA'
    ) NOT NULL COMMENT 'Método de pago',
    concept VARCHAR(255) NOT NULL COMMENT 'Concepto o descripción corta del cobro',
    amount DECIMAL(15, 2) NOT NULL COMMENT 'Monto total del cargo administrativo',
    currency_code CHAR(3) NOT NULL DEFAULT 'COP' COMMENT 'Código ISO de la moneda (predeterminado COP)',
    period_date DATE NOT NULL COMMENT 'Fecha correspondiente al periodo facturado',
    due_date DATE NOT NULL COMMENT 'Fecha límite de pago oportuno',
    next_payment_date DATE NOT NULL COMMENT 'Fecha programada para el siguiente pago',
    late_fee_percentage DECIMAL(5, 2) NOT NULL DEFAULT 0.00 COMMENT 'Porcentaje de recargo por mora aplicado',
    status ENUM(
        'PENDIENTE',
        'PAGADO',
        'VENCIDO',
        'EN_MORA',
        'ANULADO'
    ) NOT NULL DEFAULT 'PENDIENTE' COMMENT 'Estado del recaudo o cartera del cargo',
    payment_date DATE NULL COMMENT 'Fecha en la que el afiliado realizó el pago',
    bank_reference VARCHAR(100) NULL COMMENT 'Referencia de la transacción bancaria o pasarela',
    notes TEXT NULL COMMENT 'Observaciones o notas adicionales sobre el cargo',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de registro en el sistema',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha y hora de la última modificación',
    deleted_at DATETIME NULL COMMENT 'Fecha y hora de eliminación lógica (Soft Delete)',
    CHECK (due_date <= next_payment_date),
    FOREIGN KEY (vehicle_uuid) REFERENCES vehicles (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Cargos y cobros de administración aplicados a los vehículos afiliados';

-- =====================================================================================================================
-- MÓDULO 8 · FACTURACIÓN ELECTRÓNICA (RESPUESTA FACTUS / DIAN) ( X )
-- =====================================================================================================================
CREATE TABLE IF NOT EXISTS electronic_invoices (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 del registro respuesta',
    invoice_uuid CHAR(36) NOT NULL COMMENT 'Factura comercial asociada',
    status VARCHAR(20) NULL DEFAULT NULL COMMENT 'Estado Factus/DIAN',
    validated_at DATETIME NULL DEFAULT NULL COMMENT 'Fecha validación DIAN',
    cufe VARCHAR(100) NULL DEFAULT NULL COMMENT 'CUFE asignado',
    provider_document_id VARCHAR(100) NULL DEFAULT NULL COMMENT 'ID plataforma Factus',
    xml_url VARCHAR(500) NULL DEFAULT NULL COMMENT 'URL XML oficial',
    pdf_url VARCHAR(500) NULL DEFAULT NULL COMMENT 'URL PDF',
    errors JSON NULL COMMENT 'Errores/advertencias validación',
    raw_response JSON NULL COMMENT 'Payload API completo',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Marca de tiempo',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Marca de tiempo',
    PRIMARY KEY (id),
    UNIQUE KEY uk_uuid (uuid),
    UNIQUE KEY uq_invoice_uuid (invoice_uuid),
    CONSTRAINT fk_factus_response_invoice FOREIGN KEY (invoice_uuid) REFERENCES commercial_invoices (uuid) ON DELETE CASCADE
) COMMENT = 'Respuestas Factus y estado DIAN ventas';

CREATE TABLE IF NOT EXISTS invoice_related_documents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 del documento relacionado',
    invoice_uuid CHAR(36) NOT NULL COMMENT 'Factura que referencia',
    code VARCHAR(50) NOT NULL COMMENT 'Código DIAN antecedente',
    issue_date DATE NOT NULL COMMENT 'Fecha emisión antecedente',
    number VARCHAR(50) NOT NULL COMMENT 'Número oficial antecedente',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Marca de tiempo',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Marca de tiempo',
    PRIMARY KEY (id),
    UNIQUE KEY uk_uuid (uuid),
    KEY idx_invoice_uuid (invoice_uuid),
    CONSTRAINT fk_reldoc_invoice FOREIGN KEY (invoice_uuid) REFERENCES commercial_invoices (uuid) ON DELETE CASCADE
) COMMENT = 'Documentos antecedentes referenciados en facturas electrónicas';

-- =====================================================================================================================
-- MÓDULO 9 · NOTAS CRÉDITO Y DÉBITO ELECTRÓNICAS ( X )
-- =====================================================================================================================
CREATE TABLE IF NOT EXISTS credit_notes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 para trazabilidad',
    reference_code CHAR(36) NOT NULL COMMENT 'Campo obligatorio Factus API (poblar con uuid)',
    credit_note_number VARCHAR(30) NOT NULL COMMENT 'Número completo Factus',
    prefix VARCHAR(10) NULL DEFAULT NULL COMMENT 'Prefijo extraído',
    consecutive_number BIGINT NULL DEFAULT NULL COMMENT 'Consecutivo extraído',
    issue_date DATE NOT NULL COMMENT 'Fecha emisión',
    issue_time TIME NOT NULL COMMENT 'Hora exacta',
    company_uuid CHAR(36) NOT NULL COMMENT 'Empresa emisora',
    correction_concept_code VARCHAR(2) NOT NULL COMMENT 'Código DIAN corrección',
    customization_id VARCHAR(2) NOT NULL DEFAULT '20' COMMENT 'Tipo operación',
    bill_id INT NULL DEFAULT NULL COMMENT 'ID factura original Factus',
    numbering_range_id INT NULL DEFAULT NULL COMMENT 'ID rango numeración',
    observation VARCHAR(250) NULL DEFAULT NULL COMMENT 'Motivo visible',
    send_email TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Envío automático correo',
    third_party_uuid CHAR(36) NULL DEFAULT NULL COMMENT 'Cliente receptor',
    subtotal_without_tax DECIMAL(18, 2) NOT NULL DEFAULT 0 COMMENT 'Suma bases',
    total_discount DECIMAL(18, 2) NOT NULL DEFAULT 0 COMMENT 'Suma descuentos',
    total_tax DECIMAL(18, 2) NOT NULL DEFAULT 0 COMMENT 'Suma impuestos',
    total_withholding DECIMAL(18, 2) NOT NULL DEFAULT 0 COMMENT 'Suma retenciones',
    total DECIMAL(18, 2) NOT NULL DEFAULT 0 COMMENT 'Total a ajustar',
    payment_method_uuid CHAR(36) NULL DEFAULT NULL COMMENT 'Método pago',
    payment_form_code VARCHAR(2) NOT NULL DEFAULT '1' COMMENT 'Forma pago',
    payment_due_date DATE NULL DEFAULT NULL COMMENT 'Fecha límite crédito',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Marca de tiempo',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Marca de tiempo',
    PRIMARY KEY (id),
    UNIQUE KEY uk_uuid (uuid),
    KEY idx_company_uuid (company_uuid),
    KEY idx_third_party_uuid (third_party_uuid),
    CONSTRAINT fk_credit_note_company FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE,
    CONSTRAINT fk_credit_note_payment_method FOREIGN KEY (payment_method_uuid) REFERENCES payment_methods (uuid) ON DELETE SET NULL,
    CONSTRAINT fk_credit_note_third_party FOREIGN KEY (third_party_uuid) REFERENCES third_parties (uuid) ON DELETE RESTRICT
) COMMENT = 'Notas crédito electrónicas';

CREATE TABLE IF NOT EXISTS credit_note_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 de la línea',
    credit_note_uuid CHAR(36) NOT NULL COMMENT 'Nota crédito padre',
    quantity DECIMAL(10, 2) NOT NULL DEFAULT 1 COMMENT 'Cantidad a ajustar',
    unit_measure_id BIGINT UNSIGNED NOT NULL COMMENT 'Unidad medida DIAN',
    unit_price DECIMAL(18, 2) NOT NULL COMMENT 'Precio unitario base',
    is_excluded TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Excluido IVA',
    tribute_id BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'Tributo aplicable',
    tax_rate VARCHAR(10) NULL DEFAULT NULL COMMENT 'Porcentaje impuesto',
    discount_percentage DECIMAL(5, 2) NOT NULL DEFAULT 0 COMMENT '% descuento',
    discount_amount DECIMAL(18, 2) NOT NULL DEFAULT 0 COMMENT 'Monto descuento',
    standard_code_id INT NULL DEFAULT NULL COMMENT 'Código UNSPSC/GTIN',
    line_subtotal DECIMAL(18, 2) GENERATED ALWAYS AS (quantity * unit_price) STORED COMMENT 'Cantidad × precio',
    line_taxable_base DECIMAL(18, 2) GENERATED ALWAYS AS (
        line_subtotal - discount_amount
    ) STORED COMMENT 'Base gravable',
    line_tax_amount DECIMAL(18, 2) NOT NULL DEFAULT 0 COMMENT 'Monto impuesto',
    line_withholding_amount DECIMAL(18, 2) NOT NULL DEFAULT 0 COMMENT 'Monto retenciones',
    line_total DECIMAL(18, 2) GENERATED ALWAYS AS (
        line_taxable_base + line_tax_amount - line_withholding_amount
    ) STORED COMMENT 'Total línea',
    note TEXT NULL COMMENT 'Aclaración',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Marca de tiempo',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Marca de tiempo',
    PRIMARY KEY (id),
    UNIQUE KEY uk_uuid (uuid),
    KEY idx_credit_note_uuid (credit_note_uuid),
    KEY idx_tribute_id (tribute_id),
    CONSTRAINT fk_credit_note_item_credit_note FOREIGN KEY (credit_note_uuid) REFERENCES credit_notes (uuid) ON DELETE CASCADE,
    CONSTRAINT fk_credit_note_item_unit_measure FOREIGN KEY (unit_measure_id) REFERENCES measurement_units (id) ON DELETE RESTRICT,
    CONSTRAINT fk_credit_note_item_tribute FOREIGN KEY (tribute_id) REFERENCES tributes (id) ON DELETE SET NULL
) COMMENT = 'Detalle líneas nota crédito';

CREATE TABLE IF NOT EXISTS credit_note_item_withholdings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 de la retención',
    credit_note_item_uuid CHAR(36) NOT NULL COMMENT 'Ítem padre',
    withholding_code VARCHAR(20) NOT NULL COMMENT 'Código DIAN retención',
    withholding_tax_rate DECIMAL(5, 2) NOT NULL COMMENT 'Porcentaje aplicado',
    amount DECIMAL(18, 2) NOT NULL COMMENT 'Monto retenido',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Marca de tiempo',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Marca de tiempo',
    PRIMARY KEY (id),
    UNIQUE KEY uk_uuid (uuid),
    KEY idx_item_uuid (credit_note_item_uuid),
    CONSTRAINT fk_credit_note_withholding_item FOREIGN KEY (credit_note_item_uuid) REFERENCES credit_note_items (uuid) ON DELETE CASCADE
) COMMENT = 'Retenciones por ítem NC';

CREATE TABLE IF NOT EXISTS credit_note_responses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 respuesta',
    credit_note_uuid CHAR(36) NOT NULL COMMENT 'Nota crédito asociada',
    status VARCHAR(20) NULL DEFAULT NULL COMMENT 'Estado Factus',
    validated_at DATETIME NULL DEFAULT NULL COMMENT 'Fecha validación',
    cufe VARCHAR(100) NULL DEFAULT NULL COMMENT 'CUFE',
    provider_document_id VARCHAR(100) NULL DEFAULT NULL COMMENT 'ID Factus',
    xml_url VARCHAR(500) NULL DEFAULT NULL COMMENT 'URL XML',
    pdf_url VARCHAR(500) NULL DEFAULT NULL COMMENT 'URL PDF',
    errors JSON NULL COMMENT 'Errores',
    raw_response JSON NULL COMMENT 'Payload API',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Marca de tiempo',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Marca de tiempo',
    PRIMARY KEY (id),
    UNIQUE KEY uk_uuid (uuid),
    UNIQUE KEY uq_credit_note_uuid (credit_note_uuid),
    CONSTRAINT fk_factus_credit_note_response FOREIGN KEY (credit_note_uuid) REFERENCES credit_notes (uuid) ON DELETE CASCADE
) COMMENT = 'Respuestas Factus para NC';

CREATE TABLE IF NOT EXISTS debit_notes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 trazabilidad',
    reference_code CHAR(36) NOT NULL COMMENT 'Campo obligatorio Factus API',
    debit_note_number VARCHAR(30) NOT NULL COMMENT 'Número completo Factus',
    prefix VARCHAR(10) NULL DEFAULT NULL COMMENT 'Prefijo',
    consecutive_number BIGINT NULL DEFAULT NULL COMMENT 'Consecutivo',
    issue_date DATE NOT NULL COMMENT 'Fecha emisión',
    issue_time TIME NOT NULL COMMENT 'Hora exacta',
    company_uuid CHAR(36) NOT NULL COMMENT 'Empresa emisora',
    correction_concept_code VARCHAR(2) NOT NULL COMMENT 'Código DIAN corrección',
    customization_id VARCHAR(2) NOT NULL DEFAULT '30' COMMENT 'Tipo operación',
    bill_id INT NOT NULL COMMENT 'ID factura original Factus',
    numbering_range_id INT NULL DEFAULT NULL COMMENT 'ID rango numeración',
    observation VARCHAR(250) NULL DEFAULT NULL COMMENT 'Motivo visible',
    send_email TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Envío automático',
    third_party_uuid CHAR(36) NULL DEFAULT NULL COMMENT 'Cliente receptor',
    subtotal_without_tax DECIMAL(18, 2) NOT NULL DEFAULT 0 COMMENT 'Suma bases',
    total_discount DECIMAL(18, 2) NOT NULL DEFAULT 0 COMMENT 'Suma descuentos',
    total_tax DECIMAL(18, 2) NOT NULL DEFAULT 0 COMMENT 'Suma impuestos',
    total_withholding DECIMAL(18, 2) NOT NULL DEFAULT 0 COMMENT 'Suma retenciones',
    total DECIMAL(18, 2) NOT NULL DEFAULT 0 COMMENT 'Total adicional',
    payment_method_uuid CHAR(36) NULL DEFAULT NULL COMMENT 'Método pago',
    payment_form_code VARCHAR(2) NOT NULL DEFAULT '1' COMMENT 'Forma pago',
    payment_due_date DATE NULL DEFAULT NULL COMMENT 'Fecha límite',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Marca de tiempo',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Marca de tiempo',
    PRIMARY KEY (id),
    UNIQUE KEY uk_uuid (uuid),
    KEY idx_company_uuid (company_uuid),
    KEY idx_third_party_uuid (third_party_uuid),
    CONSTRAINT fk_debit_note_company FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE,
    CONSTRAINT fk_debit_note_payment_method FOREIGN KEY (payment_method_uuid) REFERENCES payment_methods (uuid) ON DELETE SET NULL,
    CONSTRAINT fk_debit_note_third_party FOREIGN KEY (third_party_uuid) REFERENCES third_parties (uuid) ON DELETE RESTRICT
) COMMENT = 'Notas débito electrónicas';

CREATE TABLE IF NOT EXISTS debit_note_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 línea',
    debit_note_uuid CHAR(36) NOT NULL COMMENT 'Nota débito padre',
    quantity DECIMAL(10, 2) NOT NULL DEFAULT 1 COMMENT 'Cantidad ajustar',
    unit_measure_id BIGINT UNSIGNED NOT NULL COMMENT 'Unidad medida DIAN',
    unit_price DECIMAL(18, 2) NOT NULL COMMENT 'Precio unitario',
    is_excluded TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Excluido IVA',
    tribute_id BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'Tributo aplicable',
    tax_rate VARCHAR(10) NULL DEFAULT NULL COMMENT 'Porcentaje',
    discount_percentage DECIMAL(5, 2) NOT NULL DEFAULT 0 COMMENT '% descuento',
    discount_amount DECIMAL(18, 2) NOT NULL DEFAULT 0 COMMENT 'Monto descuento',
    standard_code_id INT NULL DEFAULT NULL COMMENT 'Código UNSPSC/GTIN',
    line_subtotal DECIMAL(18, 2) GENERATED ALWAYS AS (quantity * unit_price) STORED COMMENT 'Subtotal',
    line_taxable_base DECIMAL(18, 2) GENERATED ALWAYS AS (
        line_subtotal - discount_amount
    ) STORED COMMENT 'Base gravable',
    line_tax_amount DECIMAL(18, 2) NOT NULL DEFAULT 0 COMMENT 'Impuesto',
    line_withholding_amount DECIMAL(18, 2) NOT NULL DEFAULT 0 COMMENT 'Retenciones',
    line_total DECIMAL(18, 2) GENERATED ALWAYS AS (
        line_taxable_base + line_tax_amount - line_withholding_amount
    ) STORED COMMENT 'Total',
    note TEXT NULL COMMENT 'Aclaración',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Marca de tiempo',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Marca de tiempo',
    PRIMARY KEY (id),
    UNIQUE KEY uk_uuid (uuid),
    KEY idx_debit_note_uuid (debit_note_uuid),
    KEY idx_tribute_id (tribute_id),
    CONSTRAINT fk_debit_note_item_debit_note FOREIGN KEY (debit_note_uuid) REFERENCES debit_notes (uuid) ON DELETE CASCADE,
    CONSTRAINT fk_debit_note_item_unit_measure FOREIGN KEY (unit_measure_id) REFERENCES measurement_units (id) ON DELETE RESTRICT,
    CONSTRAINT fk_debit_note_item_tribute FOREIGN KEY (tribute_id) REFERENCES tributes (id) ON DELETE SET NULL
) COMMENT = 'Detalle líneas ND';

CREATE TABLE IF NOT EXISTS debit_note_item_withholdings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 retención',
    debit_note_item_uuid CHAR(36) NOT NULL COMMENT 'Ítem padre',
    withholding_code VARCHAR(20) NOT NULL COMMENT 'Código DIAN',
    withholding_tax_rate DECIMAL(5, 2) NOT NULL COMMENT 'Porcentaje',
    amount DECIMAL(18, 2) NOT NULL COMMENT 'Monto',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Marca de tiempo',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Marca de tiempo',
    PRIMARY KEY (id),
    UNIQUE KEY uk_uuid (uuid),
    KEY idx_item_uuid (debit_note_item_uuid),
    CONSTRAINT fk_debit_note_withholding_item FOREIGN KEY (debit_note_item_uuid) REFERENCES debit_note_items (uuid) ON DELETE CASCADE
) COMMENT = 'Retenciones ND';

CREATE TABLE IF NOT EXISTS debit_note_responses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 respuesta',
    debit_note_uuid CHAR(36) NOT NULL COMMENT 'Nota débito asociada',
    status VARCHAR(20) NULL DEFAULT NULL COMMENT 'Estado Factus',
    validated_at DATETIME NULL DEFAULT NULL COMMENT 'Fecha validación',
    cufe VARCHAR(100) NULL DEFAULT NULL COMMENT 'CUFE',
    provider_document_id VARCHAR(100) NULL DEFAULT NULL COMMENT 'ID Factus',
    xml_url VARCHAR(500) NULL DEFAULT NULL COMMENT 'URL XML',
    pdf_url VARCHAR(500) NULL DEFAULT NULL COMMENT 'URL PDF',
    errors JSON NULL COMMENT 'Errores',
    raw_response JSON NULL COMMENT 'Payload API',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Marca de tiempo',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Marca de tiempo',
    PRIMARY KEY (id),
    UNIQUE KEY uk_uuid (uuid),
    UNIQUE KEY uq_debit_note_uuid (debit_note_uuid),
    CONSTRAINT fk_factus_debit_note_response FOREIGN KEY (debit_note_uuid) REFERENCES debit_notes (uuid) ON DELETE CASCADE
) COMMENT = 'Respuestas Factus ND';

-- =====================================================================================================================
-- MÓDULO 10 · COMPRAS ( X )
-- =====================================================================================================================
CREATE TABLE IF NOT EXISTS purchase_orders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 para integraciones',
    company_uuid CHAR(36) NOT NULL COMMENT 'Empresa emisora',
    order_number VARCHAR(20) NOT NULL COMMENT 'Número legible con secuencia',
    order_date DATE NOT NULL COMMENT 'Fecha de emisión',
    expected_date DATE NULL DEFAULT NULL COMMENT 'Fecha estimada de entrega',
    supplier_uuid CHAR(36) NOT NULL COMMENT 'Tercero proveedor',
    warehouse_uuid CHAR(36) NOT NULL COMMENT 'Bodega destino',
    status ENUM(
        'BORRADOR',
        'ENVIADA',
        'RECIBIDA_PARCIAL',
        'RECIBIDA_COMPLETA',
        'CANCELADA'
    ) NOT NULL DEFAULT 'BORRADOR' COMMENT 'Estado comercial',
    subtotal DECIMAL(18, 2) NOT NULL DEFAULT 0 COMMENT 'Suma antes de impuestos',
    total_vat DECIMAL(18, 2) NOT NULL DEFAULT 0 COMMENT 'Total IVA',
    total DECIMAL(18, 2) NOT NULL DEFAULT 0 COMMENT 'Total monetario',
    cost_center_uuid CHAR(36) NULL DEFAULT NULL COMMENT 'Centro de costos asociado',
    notes TEXT NULL COMMENT 'Instrucciones internas',
    created_by_uuid CHAR(36) NULL DEFAULT NULL COMMENT 'Usuario creador',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Marca de tiempo',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Marca de tiempo',
    PRIMARY KEY (id),
    UNIQUE KEY uk_uuid (uuid),
    KEY idx_company_uuid (company_uuid),
    KEY idx_supplier_uuid (supplier_uuid),
    KEY idx_warehouse_uuid (warehouse_uuid),
    KEY idx_cost_center_uuid (cost_center_uuid),
    CONSTRAINT fk_po_company FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE,
    CONSTRAINT fk_po_supplier FOREIGN KEY (supplier_uuid) REFERENCES third_parties (uuid) ON DELETE RESTRICT,
    CONSTRAINT fk_po_warehouse FOREIGN KEY (warehouse_uuid) REFERENCES warehouses (uuid) ON DELETE RESTRICT,
    CONSTRAINT fk_po_cost_center FOREIGN KEY (cost_center_uuid) REFERENCES cost_centers (uuid) ON DELETE SET NULL
) COMMENT = 'Orden de compra comercial';

CREATE TABLE IF NOT EXISTS purchase_order_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 de la línea',
    purchase_order_uuid CHAR(36) NOT NULL COMMENT 'Orden padre',
    variant_uuid CHAR(36) NOT NULL COMMENT 'Variante solicitada',
    line_description VARCHAR(250) NULL DEFAULT NULL COMMENT 'Descripción personalizada',
    quantity_ordered DECIMAL(18, 4) NOT NULL COMMENT 'Cantidad solicitada',
    quantity_received DECIMAL(18, 4) NOT NULL DEFAULT 0 COMMENT 'Cantidad recepcionada',
    unit_price DECIMAL(18, 2) NOT NULL COMMENT 'Precio negociado',
    discount_pct DECIMAL(5, 2) NOT NULL DEFAULT 0 COMMENT 'Descuento %',
    vat_percentage DECIMAL(5, 2) NOT NULL DEFAULT 0 COMMENT 'Tarifa IVA',
    subtotal DECIMAL(18, 2) NOT NULL COMMENT 'Base imponible',
    vat_amount DECIMAL(18, 2) NOT NULL DEFAULT 0 COMMENT 'Monto IVA',
    total DECIMAL(18, 2) NOT NULL COMMENT 'Valor total línea',
    line_order SMALLINT NOT NULL DEFAULT 0 COMMENT 'Índice visual',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Marca de tiempo',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Marca de tiempo',
    PRIMARY KEY (id),
    UNIQUE KEY uk_uuid (uuid),
    KEY idx_order_uuid (purchase_order_uuid),
    KEY idx_variant_uuid (variant_uuid),
    CONSTRAINT fk_poi_order FOREIGN KEY (purchase_order_uuid) REFERENCES purchase_orders (uuid) ON DELETE CASCADE,
    CONSTRAINT fk_poi_variant FOREIGN KEY (variant_uuid) REFERENCES product_variants (uuid) ON DELETE RESTRICT
) COMMENT = 'Detalle de líneas de orden de compra';

CREATE TABLE IF NOT EXISTS purchases (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 único de la compra/factura de proveedor',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa compradora',
    supplier_uuid CHAR(36) NOT NULL COMMENT 'UUID del proveedor (tercero)',
    purchase_order_uuid CHAR(36) NULL DEFAULT NULL COMMENT 'UUID de la orden de compra origen (si aplica)',
    document_number VARCHAR(50) NOT NULL COMMENT 'Número de la factura del proveedor',
    issue_date DATE NOT NULL COMMENT 'Fecha de emisión de la factura',
    due_date DATE NULL COMMENT 'Fecha de vencimiento del pago',
    subtotal DECIMAL(18, 2) NOT NULL DEFAULT 0.00 COMMENT 'Subtotal antes de impuestos',
    total_vat DECIMAL(18, 2) NOT NULL DEFAULT 0.00 COMMENT 'Total IVA',
    total_withholding DECIMAL(18, 2) NOT NULL DEFAULT 0.00 COMMENT 'Total retenciones aplicadas',
    total DECIMAL(18, 2) NOT NULL DEFAULT 0.00 COMMENT 'Total a pagar',
    status ENUM(
        'BORRADOR',
        'RECIBIDA',
        'PAGADA',
        'PAGADA_PARCIAL',
        'ANULADA'
    ) NOT NULL DEFAULT 'BORRADOR' COMMENT 'Estado comercial de la compra',
    notes TEXT NULL COMMENT 'Notas u observaciones',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Marca de tiempo de creación',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Marca de tiempo de última modificación',
    PRIMARY KEY (id),
    UNIQUE KEY uk_uuid (uuid),
    KEY idx_company_uuid (company_uuid),
    KEY idx_supplier_uuid (supplier_uuid),
    CONSTRAINT fk_purchase_company FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE,
    CONSTRAINT fk_purchase_supplier FOREIGN KEY (supplier_uuid) REFERENCES third_parties (uuid) ON DELETE RESTRICT,
    CONSTRAINT fk_purchase_order FOREIGN KEY (purchase_order_uuid) REFERENCES purchase_orders (uuid) ON DELETE SET NULL
) COMMENT = 'Facturas de compra y recepciones de mercancía de proveedores';

CREATE TABLE IF NOT EXISTS support_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID v4 del documento de soporte',
    company_uuid CHAR(36) NOT NULL COMMENT 'Empresa propietaria',
    document_number VARCHAR(50) NOT NULL COMMENT 'Número de documento de soporte',
    third_party_name VARCHAR(255) NOT NULL COMMENT 'Nombre del tercero',
    third_party_nit VARCHAR(20) NOT NULL COMMENT 'NIT del tercero',
    subtotal DECIMAL(18, 2) NOT NULL DEFAULT 0.00 COMMENT 'Subtotal del documento de soporte',
    total_amount DECIMAL(18, 2) NOT NULL DEFAULT 0.00 COMMENT 'Total del documento de soporte',
    issue_date DATE NOT NULL COMMENT 'Fecha de emisión del documento de soporte',
    notes TEXT NULL COMMENT 'Notas del documento de soporte',
    status ENUM(
        'Draft',
        'Approved',
        'Paid',
        'Cancelled'
    ) DEFAULT 'Draft' COMMENT 'Estado del documento de soporte',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Marca de tiempo de creación',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Marca de tiempo de última modificación',
    INDEX idx_company (company_uuid),
    INDEX idx_date (issue_date),
    CONSTRAINT fk_sd_company FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE
) COMMENT = 'Documentos de soporte electrónicos a proveedores no obligados a facturar';

CREATE TABLE IF NOT EXISTS adjustment_notes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID v4 de la nota de ajuste',
    note_number VARCHAR(50) NOT NULL COMMENT 'Número de la nota de ajuste',
    note_type ENUM('CreditNote', 'DebitNote') NOT NULL COMMENT 'Tipo de nota de ajuste',
    origin_purchase_uuid CHAR(36) NULL COMMENT 'Compra/factura proveedor origen',
    adjustment_reason VARCHAR(255) NULL COMMENT 'Razón de la nota de ajuste',
    total_adjustment DECIMAL(18, 2) NOT NULL COMMENT 'Valor total ajustado',
    issue_date DATE NOT NULL COMMENT 'Fecha de emisión',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Marca de tiempo de creación',
    CONSTRAINT fk_adj_purchase FOREIGN KEY (origin_purchase_uuid) REFERENCES purchases (uuid) ON DELETE SET NULL
) COMMENT = 'Notas de ajuste (crédito/débito) sobre compras a proveedores';

CREATE TABLE IF NOT EXISTS tax_periods (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador interno autoincremental',
    period_code VARCHAR(10) NOT NULL UNIQUE COMMENT 'Código del periodo',
    start_date DATE NOT NULL COMMENT 'Fecha de inicio del periodo',
    end_date DATE NOT NULL COMMENT 'Fecha de fin del periodo',
    status ENUM('Open', 'Closed') DEFAULT 'Open' COMMENT 'Estado del periodo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Marca de tiempo de creación'
) COMMENT = 'Periodos fiscales para declaraciones tributarias';

-- =====================================================================================================================
-- MÓDULO 11 · GESTIÓN DE FLOTA
-- =====================================================================================================================

CREATE TABLE IF NOT EXISTS electronic_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador interno autoincremental',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID v4 del documento electrónico',
    parent_uuid CHAR(36) NOT NULL COMMENT 'UUID del documento comercial (factura, soporte, nota)',
    document_type ENUM(
        'FacturaElectronica',
        'DocumentoSoporte',
        'NotaCredito',
        'NotaDebito'
    ) NOT NULL COMMENT 'Tipo de documento electrónico',
    dian_resolution VARCHAR(100) NOT NULL COMMENT 'Número de resolución DIAN que autoriza la numeración',
    cufe_cuds VARCHAR(96) NOT NULL UNIQUE COMMENT 'CUFE (factura/nota) o CUDS (documento soporte)',
    qr_code_url TEXT NULL COMMENT 'URL del código QR para consulta en el portal DIAN',
    xml_signed LONGTEXT NOT NULL COMMENT 'Archivo XML firmado electrónicamente enviado a la DIAN',
    xml_response LONGTEXT NULL COMMENT 'Respuesta XML de la DIAN (Aceptado/Rechazado)',
    validation_status ENUM(
        'Pendiente',
        'Aceptado',
        'Rechazado'
    ) NOT NULL DEFAULT 'Pendiente' COMMENT 'Estado de validación DIAN',
    validation_date DATETIME NULL COMMENT 'Fecha y hora de validación DIAN',
    software_id VARCHAR(36) NOT NULL COMMENT 'UUID del software de facturación registrado ante la DIAN',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Última modificación',
    INDEX idx_parent (parent_uuid),
    CONSTRAINT fk_ed_parent FOREIGN KEY (parent_uuid) REFERENCES purchases (uuid) ON DELETE CASCADE
) COMMENT = 'Metadatos técnicos de facturación electrónica exigidos por la DIAN';

CREATE TABLE IF NOT EXISTS vehicles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único de la empresa',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID único universal de la empresa',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal de la empresa',
    third_party_uuid CHAR(36) NULL COMMENT 'UUID único universal del tercero',
    vehicle_license_plate VARCHAR(20) NOT NULL COMMENT 'Placa del vehículo',
    transit_license_number VARCHAR(20) NOT NULL COMMENT 'Número de la licencia de tránsito',
    type_of_service ENUM('PUBLICO', 'PARTICULAR') NOT NULL DEFAULT 'PUBLICO' COMMENT 'Tipo de servicio del vehículo',
    vehicle_class_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal de la clase de vehículo',
    brand_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal de la marca',
    line VARCHAR(50) NOT NULL COMMENT 'Línea del vehículo',
    model VARCHAR(10) NOT NULL COMMENT 'Modelo del vehículo',
    color VARCHAR(30) NOT NULL COMMENT 'Color del vehículo',
    serial_number VARCHAR(50) NOT NULL COMMENT 'Número de serie del vehículo',
    engine_number VARCHAR(50) NOT NULL COMMENT 'Número de motor del vehículo',
    chassis_number VARCHAR(50) NOT NULL COMMENT 'Número de chasis del vehículo',
    vin_number VARCHAR(50) NOT NULL COMMENT 'Número de VIN del vehículo',
    engine_displacement VARCHAR(20) NOT NULL COMMENT 'Cilindraje del vehículo',
    body_type VARCHAR(30) NOT NULL COMMENT 'Tipo de carrocería del vehículo',
    fuel_type ENUM(
        'GASOLINA',
        'DIESEL',
        'GAS',
        'ELECTRICIDAD',
        'HIBRIDO',
        'OTRO'
    ) NOT NULL COMMENT 'Tipo de combustible del vehículo',
    registration_date DATE NOT NULL COMMENT 'Fecha de registro del vehículo',
    transit_authority VARCHAR(50) NOT NULL COMMENT 'Autoridad de tránsito',
    doors INT NOT NULL COMMENT 'Número de puertas del vehículo',
    load_capacity INT NOT NULL COMMENT 'Capacidad de carga del vehículo',
    gross_vehicle_weight INT NOT NULL COMMENT 'Peso bruto vehicular',
    passenger_capacity INT NOT NULL COMMENT 'Capacidad de pasajeros del vehículo',
    seated_passenger_capacity INT NOT NULL COMMENT 'Capacidad de pasajeros sentados',
    number_of_axles INT NOT NULL COMMENT 'Número de ejes del vehículo',
    exact_payment TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Pago exacto',
    internal_number VARCHAR(20) NULL COMMENT 'Número interno del vehículo',
    address_type VARCHAR(100) NOT NULL COMMENT 'Tipo de dirección',
    steering_type VARCHAR(30) NULL COMMENT 'Tipo de dirección',
    transmission_type VARCHAR(30) NULL COMMENT 'Tipo de transmisión',
    number_of_speeds VARCHAR(30) NULL COMMENT 'Número de velocidades',
    bearing_type VARCHAR(30) NULL COMMENT 'Tipo de rodamiento',
    rear_suspension VARCHAR(30) NULL COMMENT 'Tipo de suspensión trasera',
    number_of_tires VARCHAR(20) NULL COMMENT 'Número de llantas',
    rim_size VARCHAR(20) NULL COMMENT 'Tamaño del rin',
    rim_material VARCHAR(20) NULL COMMENT 'Material del rin',
    front_brake_type VARCHAR(30) NULL COMMENT 'Tipo de freno delantero',
    rear_brake_type VARCHAR(30) NULL COMMENT 'Tipo de freno trasero',
    number_of_windows VARCHAR(20) NULL COMMENT 'Número de ventanas',
    is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Estado del vehículo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última modificación',
    FOREIGN KEY (brand_uuid) REFERENCES brands (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (vehicle_class_uuid) REFERENCES vehicle_class (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (third_party_uuid) REFERENCES third_parties (uuid) ON DELETE SET NULL ON UPDATE CASCADE
) COMMENT 'vehicles';

CREATE TABLE IF NOT EXISTS vehicles_branches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único de la sucursal del vehículo',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID único universal de la sucursal del vehículo',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal de la empresa',
    vehicle_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal del vehículo',
    branch_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal de la sucursal',
    entry_date DATE NOT NULL COMMENT 'Fecha de entrada',
    exit_date DATE NOT NULL COMMENT 'Fecha de salida',
    exit_type ENUM(
        'ENTRADA',
        'SALIDA',
        'PRESTAMO'
    ) NOT NULL COMMENT 'Tipo de sucursal',
    is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Estado de la sucursal',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última modificación',
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (vehicle_uuid) REFERENCES vehicles (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (branch_uuid) REFERENCES branches (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) COMMENT = 'Sucursales de los vehículos';

CREATE TABLE IF NOT EXISTS owners (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único del propietario',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID único universal del propietario',
    third_party_uuid CHAR(36) NULL COMMENT 'UUID único universal del tercero',
    vehicle_uuid CHAR(36) NULL COMMENT 'UUID único universal del vehículo',
    document_type_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal del tipo de documento',
    owner_name VARCHAR(255) NOT NULL COMMENT 'Nombre del propietario',
    document_number VARCHAR(20) NOT NULL COMMENT 'Número de documento',
    verification_digit VARCHAR(1) COMMENT 'Dígito de verificación',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última modificación',
    FOREIGN KEY (third_party_uuid) REFERENCES third_parties (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (vehicle_uuid) REFERENCES vehicles (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (document_type_uuid) REFERENCES type_of_documents (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) COMMENT = 'Propietarios';

CREATE TABLE IF NOT EXISTS vehicle_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único de la empresa',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID único universal del documento',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal de la empresa',
    vehicle_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal del vehículo',
    document_type ENUM('SOAT', 'RCE', 'RCC', 'RTM') NOT NULL COMMENT 'Tipo de documento',
    policy_number VARCHAR(200) NOT NULL COMMENT 'Número de póliza',
    issue_date DATE NOT NULL COMMENT 'Fecha de emisión',
    effective_date DATE NULL COMMENT 'Fecha de inicio de vigencia',
    expiry_date DATE NOT NULL COMMENT 'Fecha de vencimiento',
    issuing_entity VARCHAR(200) NOT NULL COMMENT 'Entidad emisora',
    tariff_code CHAR(5) NULL COMMENT 'Código de tarifa',
    taker VARCHAR(200) NULL COMMENT 'Tomador del seguro',
    status ENUM(
        'SI',
        'NO',
        'VIGENTE',
        'INACTIVA',
        'NO VIGENTE'
    ) NOT NULL COMMENT 'Estado del documento',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última modificación',
    FOREIGN KEY (vehicle_uuid) REFERENCES vehicles (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) COMMENT = 'Documentos de Vehículos';

CREATE TABLE IF NOT EXISTS operation_cards (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único de la empresa',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID único universal de la empresa',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal de la empresa',
    vehicle_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal del vehículo',
    affiliated_company VARCHAR(255) NOT NULL COMMENT 'Empresa afiliada',
    area_of_coverage VARCHAR(255) NOT NULL DEFAULT 'NACIONAL' COMMENT 'Área de cobertura',
    service_type VARCHAR(255) NOT NULL COMMENT 'Tipo de servicio',
    transport_mode VARCHAR(255) NOT NULL COMMENT 'Modo de transporte',
    issue_date DATE NOT NULL COMMENT 'Fecha de emisión',
    expiration_date DATE NOT NULL COMMENT 'Fecha de expiración',
    operating_card_number VARCHAR(20) NOT NULL COMMENT 'Número de tarjeta de operación',
    status TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Estado de la tarjeta de operación',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última modificación',
    FOREIGN KEY (vehicle_uuid) REFERENCES vehicles (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) COMMENT = 'Tarjeta de Operación';

CREATE TABLE IF NOT EXISTS business_collaboration_agreements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único de la empresa',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID único universal de la empresa',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal de la empresa',
    vehicle_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal del vehículo',
    resolution_number VARCHAR(50) NOT NULL COMMENT 'Número de resolución',
    agreement_internal_id VARCHAR(50) UNIQUE NOT NULL COMMENT 'ID interno del acuerdo',
    contracting_entity_nit VARCHAR(20) NOT NULL COMMENT 'NIT de la entidad contratante',
    contracting_entity_name VARCHAR(255) NOT NULL COMMENT 'Nombre de la entidad contratante',
    effective_date DATE NOT NULL COMMENT 'Fecha de inicio de vigencia',
    expiry_date DATE NOT NULL COMMENT 'Fecha de expiración',
    rep_name VARCHAR(150) NOT NULL COMMENT 'Nombre del representante',
    rep_document_id VARCHAR(20) NOT NULL COMMENT 'Documento del representante',
    transport_modality ENUM(
        'CARGA',
        'ESPECIAL',
        'PASAJEROS',
        'MIXTO'
    ) NOT NULL COMMENT 'Modalidad de transporte',
    max_fleet_capacity INT DEFAULT 0 COMMENT 'Capacidad máxima de la flota',
    status TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Estado del acuerdo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última modificación',
    FOREIGN KEY (vehicle_uuid) REFERENCES vehicles (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) COMMENT = 'Convenio de Colaboración Empresarial';

CREATE TABLE IF NOT EXISTS maintenance (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único del mantenimiento',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID único universal del mantenimiento',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal de la empresa',
    vehicle_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal del vehículo',
    maintenance_type ENUM(
        'PREVENTIVA',
        'CORRECTIVA',
        'OTRO'
    ) NOT NULL COMMENT 'Tipo de mantenimiento',
    mileage INT UNSIGNED NOT NULL COMMENT 'Kilometraje del vehículo',
    service_description TEXT NOT NULL COMMENT 'Descripción del servicio',
    mechanic_name VARCHAR(150) NULL COMMENT 'Nombre del mecánico',
    workshop_name VARCHAR(150) NULL COMMENT 'Nombre del taller',
    maintenance_date DATE NOT NULL COMMENT 'Fecha del mantenimiento',
    labor_cost DECIMAL(12, 2) UNSIGNED NULL DEFAULT 0 COMMENT 'Costo de la mano de obra',
    parts_cost DECIMAL(12, 2) UNSIGNED NULL DEFAULT 0 COMMENT 'Costo de los repuestos',
    invoice_number VARCHAR(50) NULL COMMENT 'Número de la factura',
    next_maintenance_date DATE NULL COMMENT 'Fecha del próximo mantenimiento',
    notes TEXT NULL COMMENT 'Notas adicionales',
    status ENUM(
        'Pendiente',
        'Finalizado',
        'Anulado'
    ) NOT NULL DEFAULT 'Pendiente' COMMENT 'Estado del mantenimiento',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última modificación',
    FOREIGN KEY (vehicle_uuid) REFERENCES vehicles (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) COMMENT = 'Registro de mantenimientos preventivos y correctivos';

CREATE TABLE IF NOT EXISTS maintenance_parts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único del repuesto',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID único universal del repuesto',
    maintenance_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal del mantenimiento',
    part_name VARCHAR(150) NOT NULL COMMENT 'Nombre del repuesto',
    part_code VARCHAR(50) NULL COMMENT 'Código del repuesto',
    quantity DECIMAL(12, 2) UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Cantidad del repuesto',
    unit_cost DECIMAL(12, 2) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Costo unitario del repuesto',
    total_cost DECIMAL(12, 2) GENERATED ALWAYS AS (quantity * unit_cost) STORED,
    supplier_uuid CHAR(36) NULL COMMENT 'UUID único universal del proveedor',
    notes TEXT NULL COMMENT 'Notas adicionales',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última modificación',
    FOREIGN KEY (maintenance_uuid) REFERENCES maintenance (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (supplier_uuid) REFERENCES third_parties (uuid) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_maintenance_part (maintenance_uuid, part_code)
) COMMENT = 'Detalle de repuestos utilizados en mantenimientos';

CREATE TABLE vehicle_inspections (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único de la inspección',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID único universal de la inspección',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal de la empresa',
    vehicle_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal del vehículo',
    inspection_date DATE NOT NULL COMMENT 'Fecha de la inspección',
    inspector_name VARCHAR(150) NULL COMMENT 'Nombre del inspector',
    mileage INT UNSIGNED NOT NULL COMMENT 'Kilometraje del vehículo',
    driver_uuid CHAR(36) NULL COMMENT 'UUID único universal del conductor',
    notes TEXT NULL COMMENT 'Notas adicionales',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última modificación',
    FOREIGN KEY (vehicle_uuid) REFERENCES vehicles (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (driver_uuid) REFERENCES third_parties (uuid) ON DELETE SET NULL ON UPDATE CASCADE
) COMMENT = 'Inspección de vehículos';

CREATE TABLE IF NOT EXISTS inspection_results (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único del resultado de la inspección',
    inspection_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal del resultado de la inspección',
    item_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal del item de inspección',
    is_selected TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Indica si el item fue seleccionado',
    status ENUM(
        'APROBADO',
        'NO_APROBADO',
        'NO_APLICA'
    ) NOT NULL DEFAULT 'APROBADO' COMMENT 'Estado del resultado de la inspección',
    observations TEXT NULL COMMENT 'Observaciones del resultado de la inspección',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última modificación',
    FOREIGN KEY (inspection_uuid) REFERENCES vehicle_inspections (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (item_uuid) REFERENCES inspection_items (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) COMMENT = 'Resultados de la inspección';

CREATE TABLE IF NOT EXISTS driver_licenses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único de la licencia',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID único universal de la licencia',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal de la empresa',
    third_party_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal del tercero',
    number VARCHAR(50) NOT NULL UNIQUE COMMENT 'Número de la licencia',
    category ENUM('C1', 'C2', 'C3') NOT NULL COMMENT 'Categoría de la licencia',
    issue_date DATE NOT NULL COMMENT 'Fecha de expedición',
    expiration_date DATE NOT NULL COMMENT 'Fecha de expiración',
    restrictions VARCHAR(255) COMMENT 'Restricciones de la licencia',
    status ENUM(
        'ACTIVA',
        'SUSPENDIDA',
        'VENCIDA',
        'CANCELADA'
    ) DEFAULT 'ACTIVA' COMMENT 'Estado de la licencia',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última modificación',
    FOREIGN KEY (third_party_uuid) REFERENCES third_parties (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) COMMENT = 'Licencias de conductores';

CREATE TABLE IF NOT EXISTS owners_drivers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único del conductor',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID único universal del conductor',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal de la empresa',
    driver_license_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal de la licencia',
    third_party_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal del tercero',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última modificación',
    FOREIGN KEY (third_party_uuid) REFERENCES third_parties (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) COMMENT = 'Conductores de propietarios';

CREATE TABLE IF NOT EXISTS social_security_contributions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único del aporte',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID único universal del aporte',
    third_party_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal del tercero',
    billing_period DATE NOT NULL COMMENT 'Periodo de facturación',
    pila_pin VARCHAR(50) NOT NULL COMMENT 'PIN de PILA',
    contribution_type ENUM('E', 'Y', 'I', 'S') DEFAULT 'E' COMMENT 'Tipo de aporte',
    ibc_amount DECIMAL(15, 2) COMMENT 'Valor del IBC',
    health_paid TINYINT(1) DEFAULT 1 COMMENT 'Aporte a salud',
    pension_paid TINYINT(1) DEFAULT 1 COMMENT 'Aporte a pensión',
    risk_labor_paid TINYINT(1) DEFAULT 1 COMMENT 'Aporte a riesgos laborales',
    compensation_fund_paid TINYINT(1) DEFAULT 1 COMMENT 'Aporte a fondo de compensación',
    eps_name VARCHAR(100) COMMENT 'Nombre de la EPS',
    pension_name VARCHAR(100) COMMENT 'Nombre de la pensión',
    risk_labor_name VARCHAR(100) COMMENT 'Nombre del fondo de riesgos laborales',
    compensation_fund_name VARCHAR(100) COMMENT 'Nombre del fondo de compensación',
    payment_date DATE COMMENT 'Fecha de pago',
    status ENUM(
        'PENDIENTE',
        'CUMPLIDO',
        'EN MORA'
    ) DEFAULT 'PENDIENTE' COMMENT 'Estado del aporte',
    notes TEXT NULL COMMENT 'Notas adicionales',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última modificación',
    FOREIGN KEY (third_party_uuid) REFERENCES third_parties (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) COMMENT = 'Aportes a seguridad social';

-- =====================================================================================================================
-- MÓDULO 12 · GESTIÓN DE TRÁMITES
-- =====================================================================================================================
CREATE TABLE IF NOT EXISTS territorial_directors (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único del director territorial',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID único universal del director territorial',
    name VARCHAR(255) NOT NULL COMMENT 'Nombre del director territorial',
    territorial_director VARCHAR(255) NOT NULL COMMENT 'Director territorial',
    is_active BOOLEAN DEFAULT TRUE COMMENT 'Estado del director territorial',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última modificación'
) COMMENT = 'Directores territoriales';

CREATE TABLE IF NOT EXISTS procedures (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único del procedimiento',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID único universal del procedimiento',
    link_type ENUM(
        'CAMBIO_DE_EMPRESA',
        'NUEVO_VEHICULO'
    ) NULL COMMENT 'Tipo de enlace',
    company_uuid CHAR(36) NULL COMMENT 'UUID único universal de la empresa',
    third_party_uuid CHAR(36) NULL COMMENT 'UUID único universal del tercero',
    vehicle_uuid CHAR(36) NULL COMMENT 'UUID único universal del vehículo',
    procedure_code VARCHAR(50) NOT NULL COMMENT 'Código único universal del procedimiento',
    filed_number VARCHAR(50) NULL COMMENT 'Número de radicación',
    procedure_type ENUM(
        'CARTA_DE_ACEPTACION',
        'CAPACIDAD_TRANSPORTADORA',
        'INCLUSION_DE_POLIZAS',
        'TARJETA_DE_OPERACION',
        'DESVINCULACION',
    ) NOT NULL COMMENT 'Tipo de procedimiento',
    date_of_creation DATE NOT NULL COMMENT 'Fecha de creación del procedimiento',
    city_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal de la ciudad',
    subject VARCHAR(255) NULL COMMENT 'Asunto del procedimiento',
    territorial_director_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal del director territorial',
    status ENUM(
        'RECIBIDO',
        'EN_PROCESO',
        'COMPLETADO',
        'CANCELADO',
    ) NOT NULL DEFAULT 'RECIBIDO' COMMENT 'Estado del procedimiento',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última modificación',
    FOREIGN KEY (city_uuid) REFERENCES cities (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (territorial_director_uuid) REFERENCES territorial_directors (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (third_party_uuid) REFERENCES third_parties (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (vehicle_uuid) REFERENCES vehicles (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) COMMENT = 'Trámites';

CREATE TABLE IF NOT EXISTS fleet_service_contracts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único del contrato',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID único universal del contrato',
    procedure_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal del procedimiento',
    item INT UNSIGNED NOT NULL COMMENT 'Identificador único del item',
    type_of_action ENUM('C', 'M', 'E') NOT NULL COMMENT 'Tipo de acción: C=Creación, M=Modificación, E=Eliminación',
    issue_date DATE NOT NULL COMMENT 'Fecha de expedición',
    start_date DATE NOT NULL COMMENT 'Fecha de inicio',
    end_date DATE NOT NULL COMMENT 'Fecha de fin',
    duration INT UNSIGNED NOT NULL COMMENT 'Duración del contrato',
    contract_type ENUM('1', '2') NOT NULL COMMENT 'Tipo de contrato, 1 = Por vinculación y administración de flotas 2 = Por prestación de servicio',
    contract_number VARCHAR(50) NOT NULL COMMENT 'Número de contrato',
    signature_validation CHAR(5) DEFAULT 'S' NULL COMMENT 'Validación de la firma',
    valuation_amount DECIMAL(10, 2) NOT NULL COMMENT 'Valor de la tasación',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última modificación',
) COMMENT = 'Contratos de gestión de flota';

CREATE TABLE IF NOT EXISTS capacity_inventory (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único del inventario',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID único universal del inventario',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal de la empresa',
    conveyor_capacity_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal de la capacidad',
    procedure_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal del procedimiento',
    used_conveyor_capacity INT UNSIGNED NOT NULL COMMENT 'Número de capacidad',
    used_operating_cards INT UNSIGNED NOT NULL COMMENT 'Cantidad de capacidad',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última modificación',
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (procedure_uuid) REFERENCES procedures (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) COMMENT = 'Inventario de capacidad';

-- =====================================================================================================================
-- MÓDULO 13 · DESPACHOS
-- =====================================================================================================================
CREATE TABLE IF NOT EXISTS objects_contracts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único del objeto de contrato',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID único universal del objeto de contrato',
    name VARCHAR(255) NOT NULL COMMENT 'Nombre del objeto de contrato',
    description TEXT NOT NULL COMMENT 'Descripción del objeto de contrato',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última modificación'
) COMMENT = 'Objetos de contratos';

CREATE TABLE IF NOT EXISTS contractors (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único del contratista',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID único universal del contratista',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal de la empresa',
    document_type_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal del tipo de documento',
    document_number VARCHAR(20) NOT NULL COMMENT 'Número de documento del contratista',
    company_name VARCHAR(255) NOT NULL COMMENT 'Nombre o razón social del contratista',
    address VARCHAR(255) NOT NULL COMMENT 'Dirección del contratista',
    telephone VARCHAR(20) NOT NULL COMMENT 'Teléfono del contratista',
    contract_number VARCHAR(20) NOT NULL COMMENT 'Número de contrato del contratista',
    contracting_party_city VARCHAR(255) NOT NULL COMMENT 'Ciudad de contratación del contratista',
    vehicle_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal del vehículo',
    responsible_name VARCHAR(255) NOT NULL COMMENT 'Nombre del responsable del contratista',
    responsible_document VARCHAR(20) NOT NULL COMMENT 'Número de documento del responsable del contratista',
    responsible_phone VARCHAR(20) NOT NULL COMMENT 'Teléfono del responsable del contratista',
    responsible_address VARCHAR(255) NOT NULL COMMENT 'Dirección del responsable del contratista',
    status TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Estado del contratista',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última modificación',
    INDEX idx_contractors_company_uuid (company_uuid),
    INDEX idx_contractors_document_type_uuid (document_type_uuid),
    INDEX idx_contractors_vehicle_uuid (vehicle_uuid),
    INDEX idx_contractors_document_number (document_number),
    INDEX idx_contractors_status (status),
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (document_type_uuid) REFERENCES type_of_documents (uuid) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (vehicle_uuid) REFERENCES vehicles (uuid) ON DELETE RESTRICT ON UPDATE CASCADE
) COMMENT = 'Contratistas';

CREATE TABLE IF NOT EXISTS fuec (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único del FUEC',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID único universal del FUEC',
    issue_date DATE NOT NULL COMMENT 'Fecha de expedición del FUEC',
    request_number VARCHAR(25) NOT NULL UNIQUE COMMENT 'Número de solicitud del FUEC',
    number_fuec VARCHAR(4) NOT NULL UNIQUE COMMENT 'Número del FUEC',
    contract_number_display VARCHAR(4) NOT NULL COMMENT 'Número de contrato del FUEC',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal de la empresa',
    contractor_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal del contratista',
    vehicle_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal del vehículo',
    effective_date DATE NOT NULL COMMENT 'Fecha de inicio del FUEC',
    expiration_date DATE NOT NULL COMMENT 'Fecha de fin del FUEC',
    origin_route VARCHAR(255) NOT NULL COMMENT 'Ruta de origen del FUEC',
    destination_route VARCHAR(255) NOT NULL COMMENT 'Ruta de destino del FUEC',
    object_contract_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal del objeto de contrato',
    main_conductor_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal del conductor principal',
    secondary_conductor_uuid CHAR(36) NULL COMMENT 'UUID único universal del conductor secundario',
    tertiary_conductor_uuid CHAR(36) NULL COMMENT 'UUID único universal del conductor terciario',
    verification_code VARCHAR(100) NOT NULL COMMENT 'Código de verificación del FUEC',
    status ENUM(
        'ACTIVO',
        'CERRADO',
        'ANULADO'
    ) NOT NULL DEFAULT 'ACTIVO' COMMENT 'Estado del FUEC',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última modificación',
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (contractor_uuid) REFERENCES contractors (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (vehicle_uuid) REFERENCES vehicles (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (object_contract_uuid) REFERENCES objects_contracts (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (main_conductor_uuid) REFERENCES third_parties (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (secondary_conductor_uuid) REFERENCES third_parties (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (tertiary_conductor_uuid) REFERENCES third_parties (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) COMMENT = 'FUEC';

CREATE TABLE IF NOT EXISTS fuec_passengers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único del pasajero del FUEC',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID único universal del pasajero del FUEC',
    fuec_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal del FUEC',
    type_of_document_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal del tipo de documento',
    document_number VARCHAR(20) NOT NULL COMMENT 'Número de documento del pasajero del FUEC',
    first_and_last_name VARCHAR(255) NOT NULL COMMENT 'Nombre y apellido del pasajero del FUEC',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última modificación',
    INDEX idx_fuec_passengers_fuec_uuid (fuec_uuid),
    INDEX idx_fuec_passengers_document_number (document_number),
    FOREIGN KEY (fuec_uuid) REFERENCES fuec (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (type_of_document_uuid) REFERENCES type_of_documents (uuid) ON DELETE RESTRICT ON UPDATE CASCADE
) COMMENT = 'Pasajeros del FUEC';

CREATE TABLE IF NOT EXISTS service_control_sheet (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único del passengers',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID único universal del passengers',
    daily_route VARCHAR(255) NOT NULL COMMENT 'Ruta diaria',
    start_time TIME NOT NULL COMMENT 'Hora de inicio',
    initial_break TIME NOT NULL COMMENT 'Descanso inicial',
    final_break TIME NOT NULL COMMENT 'Descanso final',
    final_time TIME NOT NULL COMMENT 'Hora final',
    total_hours_worked TIME NOT NULL COMMENT 'Total horas trabajadas',
    | third_party_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal del conductor',
    officials_signature VARCHAR(255) NOT NULL COMMENT 'Firma del funcionario',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal de la empresa',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última modificación',
) COMMENT = 'Pasajeros del FUEC';

CREATE TABLE IF NOT EXISTS signatures (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único de la firma',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID único universal de la firma',
    entity_type VARCHAR(255) NOT NULL COMMENT 'Tipo de entidad',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal de la empresa',
    ip_address VARCHAR(45) NULL COMMENT 'Dirección IP de la firma',
    latitude VARCHAR(255) NULL COMMENT 'Latitud de la firma',
    longitude VARCHAR(255) NULL COMMENT 'Longitud de la firma',
    path VARCHAR(255) NOT NULL COMMENT 'Ruta del archivo de la firma',
    disk VARCHAR(50) DEFAULT 'public' NOT NULL COMMENT 'Disco del archivo de la firma',
    mime_type VARCHAR(50) DEFAULT 'image/png' NOT NULL COMMENT 'Tipo de archivo',
    size_bytes BIGINT UNSIGNED NULL COMMENT 'Tamaño del archivo',
    speed VARCHAR(50) NULL COMMENT 'Velocidad de la firma',
    state ENUM('DETENIDO', 'EN MOVIMIENTO') DEFAULT 'DETENIDO' NOT NULL COMMENT 'Estado de la firma',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última modificación',
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) COMMENT = 'Firmas';

CREATE TABLE IF NOT EXISTS service_delivery_control_sheet (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único del registro',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID único universal del registro',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal de la empresa',
    official_name_and_surname VARCHAR(255) NULL COMMENT 'Nombre y apellido del funcionario',
    service_date DATE NOT NULL COMMENT 'Fecha del servicio',
    daily_route VARCHAR(255) NULL COMMENT 'Ruta diaria',
    start_time TIME NULL COMMENT 'Hora de inicio',
    end_time TIME NULL COMMENT 'Hora final',
    total_hours TIME NULL COMMENT 'Total horas',
    starting_kilometer VARCHAR(10) NULL COMMENT 'Kilometraje inicial',
    ending_kilometer VARCHAR(10) NULL COMMENT 'Kilometraje final',
    number_of_tolls INT UNSIGNED NULL COMMENT 'Numero de peajes',
    total_toll_value DECIMAL(10, 2) NULL COMMENT 'Valor total peajes',
    type_of_control_sheet ENUM(
        'DIRECTO_CON_LA_EMPRESA',
        'SUBCONTRATADO'
    ) NULL COMMENT 'Tipo de control de hoja',
    is_active BOOLEAN DEFAULT TRUE COMMENT 'Estado activo',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última modificación',
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) COMMENT = 'Hoja de control de entrega de servicios';

CREATE TABLE IF NOT EXISTS service_internal_controls (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único del estado',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID único universal del estado',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal de la empresa',
    vehicle_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal del vehículo',
    third_party_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal del conductor',
    fuec_uuid CHAR(36) NULL COMMENT 'UUID único universal del FUEC',
    service_delivery_control_sheet_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal de la hoja de control de entrega de servicios',
    is_active BOOLEAN DEFAULT TRUE COMMENT 'Estado activo',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última modificación',
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (vehicle_uuid) REFERENCES vehicles (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (third_party_uuid) REFERENCES third_parties (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (fuec_uuid) REFERENCES fuecs (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (
        service_delivery_control_sheet_uuid
    ) REFERENCES service_delivery_control_sheet (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) COMMENT = 'Estado de la hoja de control de entrega de servicios para servicios directos con la empresa';

CREATE TABLE IF NOT EXISTS service_internal_controls_subcontracted (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único del estado',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID único universal del estado',
    vehicle_class_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal de la clase del vehículo',
    service_delivery_control_sheet_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal de la hoja de control de entrega de servicios',
    vehicle_license_plate VARCHAR(20) NOT NULL COMMENT 'Placa del vehículo',
    driver_name_and_surname VARCHAR(255) NOT NULL COMMENT 'Nombre y apellido del conductor',
    driver_license_number VARCHAR(20) NOT NULL COMMENT 'Número de licencia del conductor',
    is_active BOOLEAN DEFAULT TRUE COMMENT 'Estado activo',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última modificación',
    FOREIGN KEY (vehicle_class_uuid) REFERENCES vehicle_classes (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (
        service_delivery_control_sheet_uuid
    ) REFERENCES service_delivery_control_sheet (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) COMMENT = 'Estado de la hoja de control de entrega de servicios para servicios subcontratados';

CREATE TABLE IF NOT EXISTS control_sheets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único del control sheet',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID único universal del control sheet',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal de la empresa',
    vehicle_uuid CHAR(36) NOT NULL COMMENT 'UUID único universal del vehículo',
    observations TEXT NULL COMMENT 'Observaciones',
    is_active BOOLEAN DEFAULT TRUE COMMENT 'Estado activo',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última modificación',
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (vehicle_uuid) REFERENCES vehicles (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) COMMENT = 'Hoja de control de entrega de servicios';
-- =====================================================================================================================
-- MÓDULO DEL ADMINISTRADOR DEL SISTEMAS
-- =====================================================================================================================
-- MODULE 1: PLANES Y SUSCRIPCIONES
-- =====================================================================================================================

CREATE TABLE IF NOT EXISTS plans (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental.',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 del plan.',
    name VARCHAR(100) NOT NULL COMMENT 'Nombre comercial del plan.',
    description TEXT NULL COMMENT 'Detalle de alcance y beneficios.',
    monthly_price DECIMAL(10, 2) NOT NULL COMMENT 'Costo mensual en moneda base.',
    annual_price DECIMAL(10, 2) NOT NULL COMMENT 'Costo anual.',
    monthly_invoice_limit INT UNSIGNED NOT NULL DEFAULT 100 COMMENT 'Límite máximo de documentos electrónicos por mes.',
    features JSON NULL COMMENT 'Funcionalidades habilitadas.',
    is_public TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Disponibilidad para contratación pública.',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Marca de tiempo de creación.',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Marca de tiempo de última modificación.',
    PRIMARY KEY (id),
    UNIQUE KEY uk_uuid (uuid)
) COMMENT = 'Catálogo de planes y tarifas de la plataforma SaaS.';

CREATE TABLE IF NOT EXISTS subscriptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental.',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 de la suscripción.',
    company_uuid CHAR(36) NOT NULL COMMENT 'Empresa contratante.',
    plan_uuid CHAR(36) NOT NULL COMMENT 'Plan tarifario contratado.',
    start_date DATE NOT NULL COMMENT 'Fecha de activación.',
    end_date DATE NOT NULL COMMENT 'Fecha de vencimiento.',
    next_billing_date DATE NULL DEFAULT NULL COMMENT 'Próxima fecha de cobro o renovación.',
    status ENUM(
        'ACTIVO',
        'INACTIVO',
        'EXPIRADO',
        'SUSPENDIDO'
    ) NOT NULL DEFAULT 'ACTIVO' COMMENT 'Estado operativo.',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Marca de tiempo de creación.',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Marca de tiempo de última modificación.',
    PRIMARY KEY (id),
    UNIQUE KEY uk_uuid (uuid),
    KEY idx_company_uuid (company_uuid),
    KEY idx_plan_uuid (plan_uuid),
    CONSTRAINT fk_subscription_company FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE,
    CONSTRAINT fk_subscription_plan FOREIGN KEY (plan_uuid) REFERENCES plans (uuid) ON DELETE RESTRICT
) COMMENT = 'Suscripciones activas o históricas de las empresas.';

CREATE TABLE IF NOT EXISTS subscription_payments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental.',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 del pago.',
    subscription_uuid CHAR(36) NOT NULL COMMENT 'Suscripción asociada al pago.',
    company_uuid CHAR(36) NOT NULL COMMENT 'Empresa que realiza el pago.',
    amount DECIMAL(18, 2) NOT NULL COMMENT 'Monto pagado.',
    currency_code CHAR(3) NOT NULL DEFAULT 'COP' COMMENT 'Moneda del pago (ISO 4217).',
    payment_date DATE NOT NULL COMMENT 'Fecha de ejecución del pago.',
    payment_method VARCHAR(50) NULL DEFAULT NULL COMMENT 'Medio utilizado.',
    reference VARCHAR(100) NULL DEFAULT NULL COMMENT 'Número de referencia bancaria.',
    status ENUM(
        'PENDIENTE',
        'PAGADO',
        'FALLIDO',
        'REEMBOLSADO'
    ) NOT NULL DEFAULT 'PENDIENTE' COMMENT 'Estado financiero.',
    notes TEXT NULL COMMENT 'Observaciones administrativas.',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Marca de tiempo de creación.',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Marca de tiempo de última modificación.',
    PRIMARY KEY (id),
    UNIQUE KEY uk_uuid (uuid),
    KEY idx_subscription_uuid (subscription_uuid),
    KEY idx_company_uuid (company_uuid),
    CONSTRAINT fk_sub_payment_subscription FOREIGN KEY (subscription_uuid) REFERENCES subscriptions (uuid) ON DELETE CASCADE,
    CONSTRAINT fk_sub_payment_company FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE
) COMMENT = 'Historial de pagos de suscripciones SaaS.';

-- #############################################################
-- NIVEL 6: SOPORTE Y ATENCIÓN AL CLIENTE
-- #############################################################

CREATE TABLE support_tickets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK autoincremental.',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 global del ticket.',
    ticket_number VARCHAR(20) NOT NULL COMMENT 'Número legible para el cliente, ej: TKT-000042.',
    company_uuid CHAR(36) NULL DEFAULT NULL COMMENT 'UUID v4 de la empresa que reporta el inconveniente.',
    assignee_uuid CHAR(36) NULL DEFAULT NULL COMMENT 'UUID v4 del agente asignado actualmente. NULL = sin asignar.',
    team_uuid CHAR(36) NULL DEFAULT NULL COMMENT 'UUID v4 del equipo de soporte responsable.',
    subject VARCHAR(255) NOT NULL COMMENT 'Asunto / título del ticket.',
    description TEXT NOT NULL COMMENT 'Descripción inicial del problema (soporta Markdown).',
    type ENUM(
        'CONSULTA GENERAL',
        'INCIDENCIA',
        'REPORTE DE ERROR',
        'TAREA'
    ) NOT NULL DEFAULT 'CONSULTA GENERAL' COMMENT 'Tipo de ticket.',
    status ENUM(
        'NUEVO',
        'EN ATENCIÓN',
        'ESPERANDO RESPUESTA DEL CLIENTE',
        'EN ESPERA INTERNA',
        'RESUELTO',
        'CERRADO'
    ) NOT NULL DEFAULT 'NUEVO' COMMENT 'Estado del ciclo de vida.',
    priority ENUM(
        'BAJA',
        'NORMAL',
        'ALTA',
        'URGENTE'
    ) NOT NULL DEFAULT 'NORMAL' COMMENT 'Prioridad del ticket.',
    channel ENUM(
        'WEB',
        'EMAIL',
        'API',
        'CHAT',
        'PHONE'
    ) NOT NULL DEFAULT 'WEB' COMMENT 'Canal de origen del ticket.',
    sla_policy_id BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'Política SLA aplicada.',
    due_at DATETIME NULL DEFAULT NULL COMMENT 'Fecha límite de resolución según SLA.',
    first_response_at DATETIME NULL DEFAULT NULL COMMENT 'Fecha de la primera respuesta del agente.',
    solved_at DATETIME NULL DEFAULT NULL COMMENT 'Fecha en que el ticket se marcó como resuelto.',
    closed_at DATETIME NULL DEFAULT NULL COMMENT 'Fecha de cierre definitivo.',
    comments_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total de comentarios públicos.',
    attachments_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total de archivos adjuntos.',
    reopens_count SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Veces que el ticket fue reabierto.',
    satisfaction ENUM('ENVIADA', 'BUENA', 'MALA') NULL DEFAULT NULL COMMENT 'Resultado de encuesta de satisfacción (CSAT).',
    satisfaction_comment TEXT NULL DEFAULT NULL COMMENT 'Comentario libre del cliente sobre la atención.',
    ip_address VARCHAR(45) NULL DEFAULT NULL COMMENT 'IP del solicitante al crear el ticket (IPv4/IPv6).',
    deleted_at DATETIME NULL DEFAULT NULL COMMENT 'Soft delete: fecha de eliminación lógica.',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación.',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última actualización.',
    PRIMARY KEY (id),
    UNIQUE KEY uq_ticket_uuid (uuid),
    UNIQUE KEY uq_ticket_number (ticket_number),
    CONSTRAINT fk_ticket_company FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE,
    CONSTRAINT fk_ticket_assignee FOREIGN KEY (assignee_uuid) REFERENCES third_parties (uuid) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Tickets de soporte al cliente.';

CREATE TABLE support_ticket_comments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK autoincremental.',
    uuid CHAR(36) NOT NULL COMMENT 'UUID v4 global del comentario.',
    ticket_uuid CHAR(36) NOT NULL COMMENT 'UUID del ticket de soporte asociado.',
    agent_id BIGINT UNSIGNED NOT NULL COMMENT 'ID del usuario/agente que creó el comentario.',
    parent_id BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'ID del comentario padre para hilos anidados.',
    body TEXT NOT NULL COMMENT 'Cuerpo del comentario (soporta Markdown).',
    type ENUM(
        'NOTA GENERAL',
        'RESPUESTA',
        'CAMBIO DE ESTADO',
        'REASIGNACIÓN',
        'GENERADO AUTOMÁTICAMENTE'
    ) NOT NULL DEFAULT 'NOTA GENERAL' COMMENT 'Tipo de comentario.',
    status ENUM(
        'ACTIVO',
        'EDITADO',
        'ELIMINADO'
    ) NOT NULL DEFAULT 'ACTIVO' COMMENT 'Estado del comentario.',
    is_internal TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = nota interna (solo agentes), 0 = visible para el cliente.',
    attachments_count SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Número de archivos adjuntos.',
    ip_address VARCHAR(45) NULL DEFAULT NULL COMMENT 'IP del autor al momento de crear el comentario (IPv4/IPv6).',
    user_agent VARCHAR(512) NULL DEFAULT NULL COMMENT 'User-agent del navegador/cliente.',
    edited_at DATETIME NULL DEFAULT NULL COMMENT 'Fecha de última edición del cuerpo.',
    deleted_at DATETIME NULL DEFAULT NULL COMMENT 'Soft delete: fecha de eliminación lógica.',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación.',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última actualización.',
    PRIMARY KEY (id),
    UNIQUE KEY uq_comment_uuid (uuid),
    KEY idx_comment_ticket (ticket_uuid),
    KEY idx_comment_agent (agent_id),
    KEY idx_comment_parent (parent_id),
    KEY idx_comment_type_status (type, status),
    KEY idx_comment_is_internal (is_internal),
    KEY idx_comment_created (created_at),
    KEY idx_comment_deleted (deleted_at),
    CONSTRAINT fk_tc_ticket FOREIGN KEY (ticket_uuid) REFERENCES support_tickets (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_tc_agent FOREIGN KEY (agent_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_tc_parent FOREIGN KEY (parent_id) REFERENCES support_ticket_comments (id) ON DELETE SET NULL ON UPDATE CASCADE
) COMMENT = 'Comentarios y notas internas de tickets de soporte.';

-- =====================================================================================================================
-- SPATIE MEDIA LIBRARY · Almacenamiento polimórfico de archivos e imágenes
-- Usada por cualquier modelo que implemente HasMedia (Company, TaxInformation, etc.)
-- Generada desde: database/migrations/2026_05_13_214028_create_media_table.php
-- =====================================================================================================================

CREATE TABLE IF NOT EXISTS media (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador interno autoincremental.',
    model_type VARCHAR(255) NOT NULL COMMENT 'Clase del modelo propietario del archivo (morph).',
    model_id BIGINT UNSIGNED NOT NULL COMMENT 'ID del modelo propietario del archivo (morph).',
    uuid CHAR(36) NULL DEFAULT NULL UNIQUE COMMENT 'UUID v4 único del archivo en Spatie.',
    collection_name VARCHAR(255) NOT NULL COMMENT 'Nombre de la colección (LOGO, FIRMA, FOTO_PERFIL, etc.).',
    name VARCHAR(255) NOT NULL COMMENT 'Nombre lógico del archivo.',
    file_name VARCHAR(255) NOT NULL COMMENT 'Nombre físico del archivo en disco.',
    mime_type VARCHAR(255) NULL DEFAULT NULL COMMENT 'Tipo MIME del archivo (image/png, application/pdf, etc.).',
    disk VARCHAR(255) NOT NULL COMMENT 'Disco de almacenamiento configurado en filesystems.php.',
    conversions_disk VARCHAR(255) NULL DEFAULT NULL COMMENT 'Disco donde se guardan las conversiones de imagen.',
    size BIGINT UNSIGNED NOT NULL COMMENT 'Tamaño del archivo en bytes.',
    manipulations JSON NOT NULL COMMENT 'Transformaciones pendientes o aplicadas (Spatie).',
    custom_properties JSON NOT NULL COMMENT 'Propiedades personalizadas (expiry_date, is_primary, etc.).',
    generated_conversions JSON NOT NULL COMMENT 'Conversiones generadas (thumbnails, webp, etc.).',
    responsive_images JSON NOT NULL COMMENT 'Metadatos de imágenes responsivas generadas.',
    order_column INT UNSIGNED NULL DEFAULT NULL COMMENT 'Columna de ordenamiento dentro de la colección.',
    created_at DATETIME NULL DEFAULT NULL COMMENT 'Fecha de creación del registro.',
    updated_at DATETIME NULL DEFAULT NULL COMMENT 'Fecha de última modificación del registro.',
    PRIMARY KEY (id),
    UNIQUE KEY uq_media_uuid (uuid),
    KEY idx_media_model (model_type, model_id),
    KEY idx_media_collection (collection_name),
    KEY idx_media_order (order_column)
) COMMENT = 'Tabla polimórfica de Spatie Media Library para almacenamiento de archivos e imágenes.';

-- =============================================
-- Módulo: Next Assistant v2.1
-- Descripción: Tablas para gestión de chat, historial y contexto.
-- =============================================
-- 1. Sesiones de conversación
CREATE TABLE IF NOT EXISTS chat_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'Identificador único público de la sesión',
    user_uuid CHAR(36) NOT NULL COMMENT 'UUID del usuario',
    company_uuid CHAR(36) NULL COMMENT 'UUID de la empresa',
    title VARCHAR(255) NULL COMMENT 'Título generado o asignado a la conversación',
    module ENUM(
        'transporte',
        'facturacion',
        'inventario',
        'general'
    ) NULL COMMENT 'Módulo ERP asociado',
    status ENUM('ACTIVA', 'CERRADA') DEFAULT 'ACTIVA' COMMENT 'Estado actual de la sesión',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación.',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última actualización.',
    INDEX idx_user_module (user_uuid, module),
    INDEX idx_uuid (uuid),
    CONSTRAINT fk_chat_sessions_user FOREIGN KEY (user_uuid) REFERENCES users (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_chat_sessions_company FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) COMMENT = 'Sesiones de conversación para el asistente';

-- 2. Mensajes individuales
CREATE TABLE IF NOT EXISTS chat_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'Identificador único público del mensaje',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa',
    session_id BIGINT UNSIGNED NOT NULL COMMENT 'ID interno de la sesión',
    role ENUM(
        'USUARIO',
        'ASISTENTE',
        'SISTEMA'
    ) NOT NULL COMMENT 'Rol del emisor',
    content LONGTEXT NOT NULL COMMENT 'Contenido textual del mensaje',
    tokens_used INT UNSIGNED NULL COMMENT 'Cantidad de tokens consumidos',
    response_time FLOAT NULL COMMENT 'Tiempo de respuesta en segundos',
    feedback TINYINT NULL COMMENT 'Calificación: 1=útil, -1=no útil',
    metadata JSON NULL COMMENT 'Datos adicionales: entidades, resultados ERP, errores',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación.',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última actualización.',
    INDEX idx_session_created (session_id, created_at),
    CONSTRAINT fk_chat_messages_session FOREIGN KEY (session_id) REFERENCES chat_sessions (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_chat_messages_company FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) COMMENT = 'Mensajes individuales del asistente';

-- 3. Cache de contexto por usuario
CREATE TABLE IF NOT EXISTS chat_context_cache (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_uuid CHAR(36) NOT NULL COMMENT 'UUID del usuario',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa',
    context JSON NOT NULL COMMENT 'Preferencias o datos persistentes',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación.',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última actualización.',
    UNIQUE KEY uq_user_company (user_uuid, company_uuid),
    CONSTRAINT fk_chat_context_user FOREIGN KEY (user_uuid) REFERENCES users (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_chat_context_company FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) COMMENT = 'Cache de contexto por usuario y empresa';

-- 4. Respuestas rápidas (FAQ)
CREATE TABLE IF NOT EXISTS chat_quick_replies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID de la respuesta',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa',
    trigger VARCHAR(255) NOT NULL COMMENT 'Palabra clave o frase activadora',
    response TEXT NOT NULL COMMENT 'Respuesta predefinida',
    module ENUM('TRANSPORTES') NULL COMMENT 'Módulo aplicable',
    uses INT UNSIGNED DEFAULT 0 COMMENT 'Veces utilizada',
    active BOOLEAN DEFAULT TRUE COMMENT 'Si está habilitada',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación.',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última actualización.',
    INDEX idx_trigger (trigger),
    CONSTRAINT fk_chat_quick_replies_company FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) COMMENT = 'Respuestas rápidas para el asistente';