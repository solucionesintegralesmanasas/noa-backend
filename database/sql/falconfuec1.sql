SET FOREIGN_KEY_CHECKS = 0;

-- ===================================================================================================================
-- 1. TABLAS BASE Y CATÁLOGOS
-- ===================================================================================================================
CREATE TABLE IF NOT EXISTS departments (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT COMMENT 'Identificador único del departamento',
    uuid CHAR(36) UNIQUE NOT NULL COMMENT 'UUID único universal del departamento',
    dane_code VARCHAR(5) NOT NULL UNIQUE COMMENT 'Código oficial DANE del departamento',
    name VARCHAR(100) NOT NULL COMMENT 'Nombre del departamento',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cities (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT COMMENT 'Identificador único del municipio',
    uuid CHAR(36) UNIQUE NOT NULL COMMENT 'UUID único universal del municipio',
    department_uuid CHAR(36) NOT NULL COMMENT 'UUID del departamento al que pertenece',
    dane_code VARCHAR(8) NOT NULL COMMENT 'Código oficial DANE del municipio',
    name VARCHAR(100) NOT NULL COMMENT 'Nombre del municipio',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_uuid) REFERENCES departments (uuid) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS type_of_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único del tipo de documento',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID único universal del tipo de documento',
    name VARCHAR(255) NOT NULL COMMENT 'Nombre del tipo de documento',
    prefix VARCHAR(255) NOT NULL COMMENT 'Prefijo asociado al tipo de documento',
    status TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Estado del tipo de documento (1=activo, 0=inactivo)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tax_regimes (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT COMMENT 'Identificador único del régimen fiscal',
    uuid CHAR(36) UNIQUE NOT NULL COMMENT 'UUID único universal del régimen fiscal',
    code VARCHAR(10) NOT NULL UNIQUE COMMENT 'Código oficial del régimen fiscal',
    name VARCHAR(100) NOT NULL COMMENT 'Nombre del régimen fiscal',
    description VARCHAR(255) COMMENT 'Descripción del régimen fiscal',
    is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Indica si el régimen fiscal está activo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vehicle_class (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT COMMENT 'Identificador único de la clase de vehículo',
    uuid CHAR(36) UNIQUE NOT NULL COMMENT 'UUID único universal de la clase de vehículo',
    class_code_class CHAR(5) NOT NULL COMMENT 'Código corto de la clase de vehículo',
    description VARCHAR(200) NOT NULL COMMENT 'Descripción de la clase de vehículo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS brands (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT COMMENT 'Identificador único de la marca',
    uuid CHAR(36) UNIQUE NOT NULL COMMENT 'UUID único universal de la marca',
    description VARCHAR(200) NOT NULL COMMENT 'Nombre o descripción de la marca',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tax_responsibilities (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT COMMENT 'Identificador único de la responsabilidad tributaria',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID único universal de la responsabilidad tributaria',
    code VARCHAR(5) NOT NULL UNIQUE COMMENT 'Código de la responsabilidad tributaria',
    name VARCHAR(255) NOT NULL COMMENT 'Nombre o descripción de la responsabilidad',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO tax_responsibilities (uuid, code, name) VALUES
('d8a2da7b-0021-4a86-8d4f-951d2e2c8e6b', '05', 'Impuesto de renta y complementario régimen ordinario'),
('76afb9d9-521f-4e14-9f6b-738d216a8c3d', '07', 'Retención en la fuente a título de renta'),
('9f2172f2-947a-4c3d-a92c-9a01d14a58a1', '14', 'Informante de exógena'),
('c401fffa-4b81-456d-b4f0-6e7d9a857441', '15', 'Autorretenedor'),
('f26f8510-1d53-4c3d-9f82-0f8b9777306e', '33', 'Impuesto nacional al consumo'),
('e5a6c41c-3670-4594-b3da-0b5b3b4e565b', '37', 'Obligado a facturar electrónicamente'),
('b275c482-5c7d-454b-b7d1-3b7b4e2c8a45', '47', 'Régimen Simple de Tributación'),
('6946a187-5d8b-4f54-ab2d-7c5b8d76944e', '48', 'Responsable del IVA'),
('2c1843b8-81c8-469d-8b64-0c4a5b8d7441', '49', 'No responsable de IVA'),
('1a893d01-9c5d-4d74-9f16-1d9c8b8d5412', '52', 'Facturador electrónico');

-- ===================================================================================================================
-- 2. EMPRESAS Y ENTIDADES OPERATIVAS
-- ===================================================================================================================
CREATE TABLE IF NOT EXISTS companies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único de la empresa',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID único universal de la empresa',
    name VARCHAR(255) NOT NULL COMMENT 'Nombre o razón social principal',
    person_type ENUM('PERSONA NATURAL', 'PERSONA JURIDICA') NOT NULL COMMENT 'Tipo de persona',
    type_of_company ENUM('PUBLICO', 'PRIVADO') NOT NULL COMMENT 'Tipo de compañía',
    economic_sector VARCHAR(255) NULL COMMENT 'Sector económico',
    legal_structure ENUM(
        'SOCIEDAD POR ACCIONES SIMPLIFICADA - SAS',
        'SOCIEDAD DE RESPONSABILIDAD LIMITADA - LTDA',
        'SOCIEDAD POR ACCIONES - SPA',
        'SOCIEDAD ANONIMA - SA',
        'UNIÓN TEMPORAL - UT',
        'ENTIDAD SIN ÁNIMO DE LUCRO - ESAL'
    ) NULL COMMENT 'Estructura legal',
    document_type_uuid CHAR(36) NOT NULL COMMENT 'UUID del tipo de documento',
    document_number VARCHAR(20) NOT NULL COMMENT 'Número de documento',
    verification_digit VARCHAR(1) COMMENT 'Dígito de verificación',
    business_name VARCHAR(200) NOT NULL COMMENT 'Razón social',
    trade_name VARCHAR(100) COMMENT 'Nombre comercial',
    commercial_registration VARCHAR(50) COMMENT 'Matrícula mercantil',
    municipality_uuid CHAR(36) NOT NULL COMMENT 'UUID del municipio',
    address VARCHAR(200) NOT NULL COMMENT 'Dirección física',
    postal_code VARCHAR(10) COMMENT 'Código postal',
    phone VARCHAR(20) COMMENT 'Teléfono',
    email VARCHAR(100) NOT NULL COMMENT 'Correo electrónico',
    tax_regime_uuid CHAR(36) NOT NULL COMMENT 'UUID del régimen fiscal',
    currency_code CHAR(3) DEFAULT 'COP' COMMENT 'Código ISO moneda',
    approximate_number_of_employees CHAR(10) NULL COMMENT 'Número aproximado de empleados',
    web_page VARCHAR(255) NOT NULL COMMENT 'Página web',
    country_code CHAR(2) DEFAULT 'CO' COMMENT 'Código ISO país',
    legal_representative_name VARCHAR(255) NOT NULL COMMENT 'Nombre representante legal',
    legal_representative_last_name VARCHAR(255) NOT NULL COMMENT 'Apellido representante legal',
    legal_representative_document_type VARCHAR(20) NOT NULL COMMENT 'Tipo doc representante',
    legal_representative_document_number VARCHAR(20) NOT NULL COMMENT 'Número doc representante',
    legal_representative_nationality VARCHAR(20) NULL COMMENT 'Nacionalidad',
    legal_representative_document_issue_date DATE NULL COMMENT 'Fecha expedición',
    is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Estado empresa',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (document_type_uuid) REFERENCES type_of_documents (uuid) ON UPDATE CASCADE,
    FOREIGN KEY (tax_regime_uuid) REFERENCES tax_regimes (uuid) ON UPDATE CASCADE,
    FOREIGN KEY (municipality_uuid) REFERENCES cities (uuid) ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tax_information (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID único de la información tributaria',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa',
    is_withholding_agent_exempt TINYINT(1) NULL COMMENT 'Indica si es agente de retención exento',
    tax_special_regime VARCHAR(255) NULL COMMENT 'Ley o régimen tributario especial',
    company_size VARCHAR(50) NULL COMMENT 'Tamaño de la empresa (Mipyme, grande, etc.)',
    financial_statements_path VARCHAR(255) NULL COMMENT 'Ruta del archivo de estados financieros del último año',
    company_size_certificate_path VARCHAR(255) NULL COMMENT 'Ruta del certificado de tamaño de empresa',
    remarks TEXT NULL COMMENT 'Observaciones generales',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE
) COMMENT = 'Tabla de información tributaria y fiscal';

CREATE TABLE IF NOT EXISTS company_tax_responsibilities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único de la responsabilidad tributaria de la empresa',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID único universal de la responsabilidad tributaria de la empresa',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa',
    tax_responsibility_uuid CHAR(36) NOT NULL COMMENT 'UUID de la responsabilidad tributaria',
    tax_information_uuid CHAR(36) NOT NULL COMMENT 'UUID de la información tributaria',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON UPDATE CASCADE,
    FOREIGN KEY (tax_responsibility_uuid) REFERENCES tax_responsibilities (uuid) ON UPDATE CASCADE,
    FOREIGN KEY (tax_information_uuid) REFERENCES tax_information (uuid) ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS certificate_of_existence (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único del certificado de existencia',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID único universal del certificado de existencia',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa',
    issue_date DATE NOT NULL COMMENT 'Fecha de expedición',
    company_incorporation_date DATE NOT NULL COMMENT 'Fecha de constitución',
    company_validity DATE NOT NULL COMMENT 'Fecha de validez',
    city_of_registration_uuid CHAR(36) NOT NULL COMMENT 'UUID de la ciudad de registro',
    is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Estado',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (city_of_registration_uuid) REFERENCES cities (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shareholdings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único de la participación',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID único universal de la participación',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa',
    partner_name VARCHAR(255) NOT NULL COMMENT 'Nombre del socio',
    document_type_uuid CHAR(36) NOT NULL COMMENT 'UUID del tipo de documento',
    identity_document VARCHAR(50) UNIQUE NOT NULL COMMENT 'Número de documento de identidad',
    participation_percentage DECIMAL(5, 2) COMMENT 'Porcentaje de participación accionaria',
    nationality VARCHAR(100) COMMENT 'Nacionalidad del socio',
    person_type ENUM('PERSONA NATURAL', 'PERSONA JURIDICA') NOT NULL COMMENT 'Tipo de persona',
    related_party_type VARCHAR(100) NULL COMMENT 'Tipo de parte relacionada',
    is_pep TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Indica si es Persona Expuesta Políticamente (PEP)',
    is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Indica si está activo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (document_type_uuid) REFERENCES type_of_documents (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contacts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID único universal del contacto',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa',
    shareholding_id BIGINT UNSIGNED NULL COMMENT 'ID de la participación accionaria asociada',
    first_name VARCHAR(100) COMMENT 'Nombres del contacto',
    last_name VARCHAR(100) COMMENT 'Apellidos del contacto',
    representative_type ENUM(
        'COMERCIAL', 'REPRESENTANTE_LEGAL', 'TECNICO', 'CARTERA', 'COMPRAS',
        'CONTACTO_FACTURACION', 'CUMPLIMIENTO', 'FINANCIERO', 'HSE', 'JURIDICO',
        'REPRESENTANTE_LEGAL_SUPLENTE'
    ) NOT NULL COMMENT 'Tipo de representante',
    country VARCHAR(100) COMMENT 'País del contacto',
    municipality_uuid CHAR(36) NULL COMMENT 'UUID del municipio',
    email VARCHAR(150) COMMENT 'Correo electrónico de contacto',
    phone_number VARCHAR(50) COMMENT 'Número de teléfono de contacto',
    remarks TEXT COMMENT 'Observaciones adicionales',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_contacts_company (company_uuid),
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (shareholding_id) REFERENCES shareholdings (id) ON DELETE CASCADE
) COMMENT = 'Tabla de contactos asociados a la participación';

CREATE TABLE IF NOT EXISTS branches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único de la sucursal',
    uuid CHAR(36) UNIQUE NOT NULL COMMENT 'UUID único universal de la sucursal',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa',
    name VARCHAR(100) NOT NULL COMMENT 'Nombre sucursal',
    address VARCHAR(200) NOT NULL COMMENT 'Dirección sucursal',
    municipality_uuid CHAR(36) NOT NULL COMMENT 'UUID municipio',
    is_primary TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Sucursal principal',
    status TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Estado',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE,
    FOREIGN KEY (municipality_uuid) REFERENCES cities (uuid) ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS economic_activities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID único universal de la actividad económica',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa',
    activity_code VARCHAR(20) NOT NULL COMMENT 'Código de la actividad económica (ej. código CIIU)',
    activity_description VARCHAR(255) COMMENT 'Descripción de la actividad económica',
    is_main_activity TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Indica si es la actividad principal',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE
) COMMENT = 'Tabla de actividades económicas de la empresa';

CREATE TABLE IF NOT EXISTS bank_details (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa',
    bank_name VARCHAR(100) COMMENT 'Nombre del banco',
    branch_office VARCHAR(100) COMMENT 'Nombre de la sucursal bancaria',
    account_type VARCHAR(50) COMMENT 'Tipo de cuenta (ej. ahorros, corriente)',
    account_number VARCHAR(50) NOT NULL COMMENT 'Número de cuenta bancaria',
    account_holder VARCHAR(255) COMMENT 'Nombre del titular de la cuenta',
    is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Indica si la cuenta está activa',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de registro',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Tabla de información bancaria de la empresa';

CREATE TABLE IF NOT EXISTS experiences (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID único universal de la experiencia',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa',
    customer_name VARCHAR(255) NOT NULL COMMENT 'Razón social o nombre del cliente',
    value_before_tax DECIMAL(18, 2) COMMENT 'Valor del contrato antes de IVA',
    currency VARCHAR(10) DEFAULT 'COP' COMMENT 'Moneda utilizada (ej. COP, USD)',
    start_date DATE COMMENT 'Fecha de inicio de la experiencia',
    end_date DATE COMMENT 'Fecha de finalización (o fecha estimada si es vigente)',
    is_ongoing TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Indica si la experiencia se encuentra vigente',
    remarks TEXT COMMENT 'Observaciones adicionales sobre la experiencia',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Tabla de experiencias comerciales del socio';

CREATE TABLE IF NOT EXISTS financial_statements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID único universal de los estados financieros',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa',
    fiscal_year INT NOT NULL COMMENT 'Año del ejercicio fiscal',
    currency VARCHAR(10) DEFAULT 'COP' COMMENT 'Moneda en la que se reportan los valores',
    current_assets DECIMAL(18, 2) COMMENT 'Activo corriente',
    inventory DECIMAL(18, 2) COMMENT 'Inventario',
    total_assets DECIMAL(18, 2) COMMENT 'Activo total',
    current_liabilities DECIMAL(18, 2) COMMENT 'Pasivo corriente',
    financial_obligations DECIMAL(18, 2) COMMENT 'Obligaciones financieras',
    total_liabilities DECIMAL(18, 2) COMMENT 'Pasivo total',
    retained_earnings DECIMAL(18, 2) COMMENT 'Utilidades retenidas o acumuladas',
    equity DECIMAL(18, 2) COMMENT 'Patrimonio total',
    operational_income DECIMAL(18, 2) COMMENT 'Ventas o ingresos operacionales',
    operating_profit_before_tax DECIMAL(18, 2) COMMENT 'Utilidad operacional antes de impuestos',
    net_income_period DECIMAL(18, 2) COMMENT 'Utilidad neta del periodo',
    depreciation_amortization DECIMAL(18, 2) COMMENT 'Depreciación y amortización',
    financial_expenses DECIMAL(18, 2) COMMENT 'Gastos financieros o intereses',
    remarks TEXT COMMENT 'Observaciones generales',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de registro',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Tabla de estados financieros anuales';

CREATE TABLE IF NOT EXISTS tax_declarations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID único universal de la declaración de renta',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa',
    fiscal_year INT NOT NULL COMMENT 'Año gravable de la declaración',
    gross_assets DECIMAL(18, 2) COMMENT 'Patrimonio bruto',
    net_assets DECIMAL(18, 2) COMMENT 'Patrimonio líquido',
    total_gross_income DECIMAL(18, 2) COMMENT 'Total ingresos brutos',
    ordinary_net_income DECIMAL(18, 2) COMMENT 'Renta líquida ordinaria del ejercicio',
    pre_tax_net_profit DECIMAL(18, 2) COMMENT 'Utilidad neta antes de impuestos',
    total_operating_non_operating_income DECIMAL(18, 2) COMMENT 'Total ingresos operacionales y no operacionales',
    remarks TEXT COMMENT 'Observaciones generales',
    status VARCHAR(50) COMMENT 'Estado de la declaración (ej. presentada, en borrador)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de registro',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Tabla de declaraciones de renta anuales';

CREATE TABLE IF NOT EXISTS rates (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT COMMENT 'Identificador único',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID único',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID empresa',
    initial_passenger_capacity INT UNSIGNED NOT NULL COMMENT 'Capacidad inicial',
    final_passenger_capacity INT UNSIGNED NOT NULL COMMENT 'Capacidad final',
    rate_value DECIMAL(10, 2) NOT NULL COMMENT 'Valor tarifa',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS enabling_resolutions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador resolución',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID resolución',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID empresa',
    resolution_number VARCHAR(20) NOT NULL COMMENT 'Número resolución',
    number_fuec VARCHAR(20) NOT NULL COMMENT 'Número FUEC',
    territorial_code VARCHAR(20) NOT NULL COMMENT 'Código territorial',
    resolution_date DATE NOT NULL COMMENT 'Fecha emisión',
    status TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Estado',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS occupational_safety_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID único',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa',
    operation_year INT UNSIGNED NOT NULL COMMENT 'Año de operación',
    fatalities INT DEFAULT 0 COMMENT 'Número de fatalidades',
    incapacitating_accidents_count INT DEFAULT 0 COMMENT 'Número de accidentes incapacitantes',
    total_incidents_count INT DEFAULT 0 COMMENT 'Número total de incidentes',
    lost_days_count INT DEFAULT 0 COMMENT 'Total de días de incapacidad',
    average_workers_count INT DEFAULT 0 COMMENT 'Número promedio de trabajadores',
    hours_worked DECIMAL(18, 2) COMMENT 'Total de horas hombre trabajadas',
    arl_accident_certificate_date DATE COMMENT 'Fecha de expedición certificado accidentalidad ARL',
    risk_level VARCHAR(50) COMMENT 'Nivel de riesgo',
    arl_affiliation_certificate_date DATE COMMENT 'Fecha expedición certificado afiliación ARL',
    sgsst_rating VARCHAR(50) COMMENT 'Calificación del SG-SST',
    sgsst_evaluation_date DATE COMMENT 'Fecha de evaluación del SG-SST',
    sgsst_certificate_path VARCHAR(255) COMMENT 'Ruta del archivo certificado de resultado SG-SST',
    remarks TEXT COMMENT 'Observaciones generales',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de registro',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conveyor_capacity (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador capacidad',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID capacidad',
    enabling_resolution_uuid CHAR(36) NOT NULL COMMENT 'UUID resolución',
    vehicle_type VARCHAR(20) NOT NULL COMMENT 'Tipo vehículo',
    authorized_capacity INT NOT NULL COMMENT 'Capacidad autorizada',
    current_capacity INT NOT NULL COMMENT 'Capacidad actual',
    minimum_own_capacity INT NOT NULL COMMENT 'Capacidad mínima propia',
    status TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Estado',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (enabling_resolution_uuid) REFERENCES enabling_resolutions (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fire_ranges (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador rango',
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'UUID rango',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID empresa',
    initial_range INT NOT NULL COMMENT 'Número inicial',
    type_range ENUM('FUEC', 'CONTRATOS', 'FACTURAS') NOT NULL COMMENT 'Tipo rango',
    final_range INT NOT NULL COMMENT 'Número final',
    current_number INT NOT NULL COMMENT 'Último consecutivo asignado',
    year INT NOT NULL COMMENT 'Año vigencia',
    status TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Estado',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ===================================================================================================================
-- 3. TERCEROS Y GESTIÓN DE FLOTA
-- ===================================================================================================================
CREATE TABLE IF NOT EXISTS third_parties (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    type_person ENUM('NATURAL', 'JURIDICA') NOT NULL,
    company_uuid CHAR(36) NOT NULL,
    third_party_type ENUM('CLIENTE', 'PROVEEDOR', 'AFILIADO', 'CONDUCTOR', 'AMBOS') NOT NULL,
    document_type_uuid CHAR(36) NOT NULL,
    document_number VARCHAR(20) NOT NULL,
    verification_digit CHAR(1) NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    trade_name VARCHAR(100) NULL,
    email VARCHAR(100) NULL,
    phone VARCHAR(20) NULL,
    address VARCHAR(200) NULL,
    municipality_uuid CHAR(36) NULL,
    tax_regime_uuid CHAR(36) NULL,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_third_parties_document (document_type_uuid, document_number),
    FOREIGN KEY (document_type_uuid) REFERENCES type_of_documents (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (municipality_uuid) REFERENCES cities (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (tax_regime_uuid) REFERENCES tax_regimes (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS driver_licenses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa',
    third_party_uuid CHAR(36) NOT NULL,
    number VARCHAR(50) NOT NULL UNIQUE,
    category ENUM('C1', 'C2', 'C3') NOT NULL,
    issue_date DATE NOT NULL,
    expiration_date DATE NOT NULL,
    restrictions VARCHAR(255),
    status ENUM('ACTIVA', 'SUSPENDIDA', 'VENCIDA', 'CANCELADA') DEFAULT 'ACTIVA',
    document_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (third_party_uuid) REFERENCES third_parties (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS drivers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa',
    driver_license_uuid CHAR(36) NOT NULL,
    affiliate_uuid CHAR(36) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (driver_license_uuid) REFERENCES driver_licenses (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (affiliate_uuid) REFERENCES third_parties (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS social_security_contributions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    third_party_uuid CHAR(36) NOT NULL,
    billing_period DATE NOT NULL,
    pila_pin VARCHAR(50) NOT NULL,
    contribution_type ENUM('E', 'Y', 'I', 'S') DEFAULT 'E',
    ibc_amount DECIMAL(15, 2),
    health_paid TINYINT(1) DEFAULT 1,
    pension_paid TINYINT(1) DEFAULT 1,
    risk_labor_paid TINYINT(1) DEFAULT 1,
    compensation_fund_paid TINYINT(1) DEFAULT 1,
    payment_date DATE,
    attachment_url VARCHAR(512),
    status ENUM('PENDIENTE', 'CUMPLIDO', 'EN MORA') DEFAULT 'PENDIENTE',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (third_party_uuid) REFERENCES third_parties (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vehicles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    company_uuid CHAR(36) NOT NULL,
    third_party_uuid CHAR(36) NOT NULL,
    vehicle_license_plate VARCHAR(20) NOT NULL,
    transit_license_number VARCHAR(20) NOT NULL,
    type_of_service ENUM('PUBLICO', 'PARTICULAR') NOT NULL DEFAULT 'PUBLICO',
    vehicle_class_uuid CHAR(36) NOT NULL,
    brand_uuid CHAR(36) NOT NULL,
    line VARCHAR(50) NOT NULL,
    model VARCHAR(10) NOT NULL,
    color VARCHAR(30) NOT NULL,
    serial_number VARCHAR(50) NOT NULL,
    engine_number VARCHAR(50) NOT NULL,
    chassis_number VARCHAR(50) NOT NULL,
    vin_number VARCHAR(50) NOT NULL,
    engine_displacement VARCHAR(20) NOT NULL,
    body_type VARCHAR(30) NOT NULL,
    fuel_type ENUM('GASOLINA', 'DIESEL', 'GAS', 'ELECTRICIDAD', 'HIBRIDO', 'OTRO') NOT NULL,
    registration_date DATE NOT NULL,
    transit_authority VARCHAR(50) NOT NULL,
    doors INT NOT NULL,
    load_capacity INT NOT NULL,
    gross_vehicle_weight INT NOT NULL,
    passenger_capacity INT NOT NULL,
    seated_passenger_capacity INT NOT NULL,
    number_of_axles INT NOT NULL,
    branch_uuid CHAR(36) NULL,
    exact_payment TINYINT(1) NOT NULL DEFAULT 0,
    internal_number VARCHAR(20) NULL,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_vehicle_plate (vehicle_license_plate),
    INDEX idx_vehicle_internal (internal_number),
    FOREIGN KEY (third_party_uuid) REFERENCES third_parties (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (brand_uuid) REFERENCES brands (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (vehicle_class_uuid) REFERENCES vehicle_class (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (branch_uuid) REFERENCES branches (uuid) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vehicle_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    company_uuid CHAR(36) NOT NULL,
    vehicle_uuid CHAR(36) NOT NULL,
    document_type ENUM('SOAT', 'RCE', 'RCC', 'RTM') NOT NULL,
    policy_number VARCHAR(200) NOT NULL,
    issue_date DATE NOT NULL,
    effective_date DATE NULL,
    expiry_date DATE NOT NULL,
    issuing_entity VARCHAR(200) NOT NULL,
    tariff_code CHAR(5) NULL,
    taker VARCHAR(200) NULL,
    status ENUM('SI', 'NO', 'VIGENTE', 'INACTIVA', 'NO VIGENTE') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CHECK (effective_date IS NULL OR effective_date <= expiry_date),
    FOREIGN KEY (vehicle_uuid) REFERENCES vehicles (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS operation_cards (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    company_uuid CHAR(36) NOT NULL,
    vehicle_uuid CHAR(36) NOT NULL,
    affiliated_company VARCHAR(255) NOT NULL,
    area_of_coverage VARCHAR(255) NOT NULL DEFAULT 'NACIONAL',
    service_type VARCHAR(255) NOT NULL,
    transport_mode VARCHAR(255) NOT NULL,
    issue_date DATE NOT NULL,
    expiration_date DATE NOT NULL,
    operating_card_number VARCHAR(20) NOT NULL,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CHECK (issue_date < expiration_date),
    FOREIGN KEY (vehicle_uuid) REFERENCES vehicles (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS business_collaboration_agreements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    company_uuid CHAR(36) NOT NULL,
    vehicle_uuid CHAR(36) NOT NULL,
    resolution_number VARCHAR(50) NOT NULL,
    agreement_internal_id VARCHAR(50) UNIQUE NOT NULL,
    contracting_entity_nit VARCHAR(20) NOT NULL,
    contracting_entity_name VARCHAR(255) NOT NULL,
    effective_date DATE NOT NULL,
    expiry_date DATE NOT NULL,
    rep_name VARCHAR(150) NOT NULL,
    rep_document_id VARCHAR(20) NOT NULL,
    transport_modality ENUM('CARGA', 'ESPECIAL', 'PASAJEROS', 'MIXTO') NOT NULL,
    max_fleet_capacity INT DEFAULT 0,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CHECK (effective_date < expiry_date),
    FOREIGN KEY (vehicle_uuid) REFERENCES vehicles (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS maintenance (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    company_uuid CHAR(36) NOT NULL,
    vehicle_uuid CHAR(36) NOT NULL,
    maintenance_type ENUM('PREVENTIVA', 'CORRECTIVA', 'OTRO') NOT NULL,
    mileage INT NOT NULL,
    service_description TEXT NOT NULL,
    replaced_parts TEXT NULL,
    mechanic_name VARCHAR(150) NULL,
    workshop_name VARCHAR(150) NULL,
    maintenance_date DATE NOT NULL,
    labor_cost DECIMAL(12, 2) NULL DEFAULT 0,
    parts_cost DECIMAL(12, 2) NULL DEFAULT 0,
    invoice_number VARCHAR(50) NULL,
    next_maintenance_date DATE NULL,
    invoice_url VARCHAR(255) NULL,
    notes TEXT NULL,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_uuid) REFERENCES vehicles (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ===================================================================================================================
-- 4. GESTIÓN COMERCIAL
-- ===================================================================================================================
CREATE TABLE IF NOT EXISTS products (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    company_uuid CHAR(36) NOT NULL,
    dian_code VARCHAR(20) NOT NULL UNIQUE,
    service_name VARCHAR(150) NOT NULL,
    description TEXT,
    unit_of_measure VARCHAR(5) NOT NULL DEFAULT '94',
    base_price DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    vat_percentage DECIMAL(5, 2) NOT NULL DEFAULT 1.00,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS billing_resolutions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    company_uuid CHAR(36) NOT NULL,
    resolution_name VARCHAR(100) NOT NULL,
    resolution_number VARCHAR(50) NOT NULL,
    prefix VARCHAR(10) NOT NULL,
    start_number BIGINT NOT NULL,
    end_number BIGINT NOT NULL,
    next_number BIGINT NOT NULL,
    valid_from DATE NOT NULL,
    valid_until DATE NOT NULL,
    technical_key VARCHAR(255) NOT NULL,
    document_type ENUM('FACTURA', 'NOTA_CREDITO', 'NOTA_DEBITO') NOT NULL DEFAULT 'FACTURA',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CHECK (
        start_number < end_number
        AND next_number BETWEEN start_number AND end_number
        AND valid_from < valid_until
    ),
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    company_uuid CHAR(36) NOT NULL,
    third_party_uuid CHAR(36) NOT NULL,
    resolution_uuid CHAR(36) NOT NULL,
    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    cufe VARCHAR(255) UNIQUE,
    qr_url TEXT,
    issue_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    due_date DATE NOT NULL,
    payment_method ENUM('CONTADO', 'CREDITO') NOT NULL,
    payment_means ENUM('EFECTIVO', 'TRANSFERENCIA', 'CONSIGNACION', 'PAGO_ELECTRONICO') NOT NULL DEFAULT 'EFECTIVO',
    currency CHAR(3) NOT NULL DEFAULT 'COP',
    subtotal DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    total_vat DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    total_withholdings DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    total_discounts DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    type_invoice ENUM('FACTURA', 'REMISION') NOT NULL DEFAULT 'FACTURA',
    dian_status ENUM('BORRADOR', 'ENVIADA', 'ACEPTADA', 'RECHAZADA', 'CONTINGENCIA') NOT NULL DEFAULT 'BORRADOR',
    dian_response_code VARCHAR(20),
    dian_response_message TEXT,
    xml_content LONGTEXT,
    xml_signed LONGTEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CHECK (issue_date <= due_date),
    INDEX idx_invoice_issue_date (issue_date),
    INDEX idx_invoice_company_status (company_uuid, dian_status),
    FOREIGN KEY (third_party_uuid) REFERENCES third_parties (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (resolution_uuid) REFERENCES billing_resolutions (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_line_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    invoice_uuid CHAR(36) NOT NULL,
    product_uuid CHAR(36) NOT NULL,
    line_number SMALLINT NOT NULL DEFAULT 1,
    trip_description TEXT NOT NULL,
    origin_city VARCHAR(100),
    destination_city VARCHAR(100),
    vehicle_plate VARCHAR(15),
    waybill_number VARCHAR(50),
    quantity DECIMAL(12, 4) NOT NULL DEFAULT 1.0000,
    unit_price DECIMAL(15, 2) NOT NULL,
    discount_percentage DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    line_subtotal DECIMAL(15, 2),
    vat_amount DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    line_total DECIMAL(15, 2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_invoice_line_invoice (invoice_uuid),
    FOREIGN KEY (invoice_uuid) REFERENCES invoices (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (product_uuid) REFERENCES products (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_taxes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    invoice_uuid CHAR(36) NOT NULL,
    tax_code ENUM('IVA', 'INC', 'RETEFUENTE', 'RETEIVA', 'RETEICA') NOT NULL,
    tax_name VARCHAR(50) NOT NULL,
    tax_percentage DECIMAL(6, 4) NOT NULL,
    tax_base_amount DECIMAL(15, 2) NOT NULL,
    tax_amount DECIMAL(15, 2) NOT NULL,
    is_withholding TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_invoice_tax_code UNIQUE (invoice_uuid, tax_code),
    FOREIGN KEY (invoice_uuid) REFERENCES invoices (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS adjustment_notes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    original_invoice_uuid CHAR(36) NOT NULL,
    resolution_uuid CHAR(36) NOT NULL,
    note_number VARCHAR(50) NOT NULL UNIQUE,
    cude VARCHAR(255) UNIQUE,
    note_type ENUM('CREDITO', 'DEBITO') NOT NULL,
    concept_code VARCHAR(5) NOT NULL,
    concept_description VARCHAR(255),
    issue_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    subtotal DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    total_vat DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(15, 2) NOT NULL,
    dian_status ENUM('BORRADOR', 'ENVIADA', 'ACEPTADA', 'RECHAZADA') NOT NULL DEFAULT 'BORRADOR',
    xml_content LONGTEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (original_invoice_uuid) REFERENCES invoices (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (resolution_uuid) REFERENCES billing_resolutions (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ===================================================================================================================
-- 5. GESTIÓN FINANCIERA Y ADMINISTRATIVA
-- ===================================================================================================================
CREATE TABLE IF NOT EXISTS affiliate_admin_charges (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    company_uuid CHAR(36) NOT NULL,
    payment_reference VARCHAR(50) NOT NULL,
    vehicle_uuid CHAR(36) NOT NULL,
    charge_type ENUM('CUOTA_ADMINISTRACION', 'PAGO_MENSUALIDAD') NOT NULL,
    concept VARCHAR(255) NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    currency_code CHAR(3) NOT NULL DEFAULT 'COP',
    period_date DATE NOT NULL,
    due_date DATE NOT NULL,
    next_payment_date DATE NOT NULL,
    late_fee_percentage DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
    status ENUM('PENDIENTE', 'PAGADO', 'VENCIDO', 'EN_MORA', 'ANULADO') NOT NULL DEFAULT 'PENDIENTE',
    payment_date DATE NULL,
    bank_reference VARCHAR(100) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    CHECK (due_date <= next_payment_date),
    FOREIGN KEY (vehicle_uuid) REFERENCES vehicles (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS operational_expenses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    company_uuid CHAR(36) NOT NULL,
    third_party_uuid CHAR(36) NOT NULL,
    expense_date DATE NOT NULL,
    category ENUM(
        'COMBUSTIBLE', 'PEAJES', 'MANTENIMIENTO_VEHICULO', 'ARRIENDO', 'SERVICIOS_PUBLICOS',
        'PAPELERIA', 'ASEO', 'TECNOLOGIA', 'LEGAL_CONTABLE', 'PUBLICIDAD', 'OTRO'
    ) NOT NULL,
    concept VARCHAR(255) NOT NULL,
    supplier_uuid CHAR(36) NULL,
    amount DECIMAL(15, 2) NOT NULL,
    vat_amount DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    payment_method ENUM('EFECTIVO', 'TRANSFERENCIA', 'CONSIGNACION', 'TARJETA', 'CAJA_MENOR') NOT NULL,
    invoice_number VARCHAR(100),
    receipt_url VARCHAR(500),
    vehicle_uuid CHAR(36) NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (third_party_uuid) REFERENCES third_parties (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (supplier_uuid) REFERENCES third_parties (uuid) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (vehicle_uuid) REFERENCES vehicles (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS expense_vouchers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    company_uuid CHAR(36) NOT NULL,
    voucher_number VARCHAR(50) NOT NULL UNIQUE,
    voucher_date DATE NOT NULL,
    beneficiary_name VARCHAR(200) NOT NULL,
    beneficiary_id_number VARCHAR(30),
    amount DECIMAL(15, 2) NOT NULL,
    payment_concept TEXT NOT NULL,
    payment_method ENUM('EFECTIVO', 'TRANSFERENCIA', 'CHEQUE', 'CONSIGNACION') NOT NULL,
    bank_reference VARCHAR(100),
    expense_type ENUM('PROVEEDOR', 'NOMINA', 'AFILIADO', 'IMPUESTO', 'SERVICIO', 'OTRO') NOT NULL,
    source_document_url VARCHAR(500),
    is_voided TINYINT(1) NOT NULL DEFAULT 0,
    void_reason VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ===================================================================================================================
-- 6. OPERACIONES (FUEC)
-- ===================================================================================================================
CREATE TABLE IF NOT EXISTS objects_contracts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contractors (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    company_uuid CHAR(36) NOT NULL,
    document_type_uuid CHAR(36) NOT NULL,
    document_number VARCHAR(20) NOT NULL,
    company_name VARCHAR(255) NOT NULL,
    address VARCHAR(255) NULL,
    telephone VARCHAR(20) NULL,
    contract_number VARCHAR(20) NOT NULL,
    contracting_party_city VARCHAR(255) NULL,
    vehicle_uuid CHAR(36) NOT NULL,
    responsible_name VARCHAR(255) NOT NULL,
    responsible_document VARCHAR(20) NOT NULL,
    responsible_phone VARCHAR(20) NOT NULL,
    responsible_address VARCHAR(255) NOT NULL,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_contractors_company_uuid (company_uuid),
    INDEX idx_contractors_document_type_uuid (document_type_uuid),
    INDEX idx_contractors_vehicle_uuid (vehicle_uuid),
    INDEX idx_contractors_document_number (document_number),
    INDEX idx_contractors_status (status),
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (document_type_uuid) REFERENCES type_of_documents (uuid) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (vehicle_uuid) REFERENCES vehicles (uuid) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fuec (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    issue_date DATE NOT NULL,
    request_number VARCHAR(50) NOT NULL UNIQUE,
    number_fuec VARCHAR(50) NOT NULL UNIQUE,
    contract_number_display VARCHAR(50) NOT NULL,
    company_uuid CHAR(36) NOT NULL,
    contractor_uuid CHAR(36) NOT NULL,
    vehicle_uuid CHAR(36) NOT NULL,
    effective_date DATE NOT NULL,
    expiration_date DATE NOT NULL,
    origin_route VARCHAR(255) NOT NULL,
    destination_route VARCHAR(255) NOT NULL,
    object_contract_uuid CHAR(36) NOT NULL,
    main_conductor_uuid CHAR(36) NOT NULL,
    secondary_conductor_uuid CHAR(36) NULL,
    tertiary_conductor_uuid CHAR(36) NULL,
    verification_code VARCHAR(100) NOT NULL,
    status ENUM('ACTIVO', 'CERRADO', 'ANULADO') NOT NULL DEFAULT 'ACTIVO',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CHECK (
        effective_date < expiration_date
        AND issue_date <= effective_date
    ),
    INDEX idx_fuec_company_dates (company_uuid, effective_date, expiration_date),
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (contractor_uuid) REFERENCES contractors (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (vehicle_uuid) REFERENCES vehicles (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (object_contract_uuid) REFERENCES objects_contracts (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (main_conductor_uuid) REFERENCES third_parties (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (secondary_conductor_uuid) REFERENCES third_parties (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (tertiary_conductor_uuid) REFERENCES third_parties (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fuec_passengers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    fuec_uuid CHAR(36) NOT NULL,
    type_of_document_uuid CHAR(36) NOT NULL,
    document_number VARCHAR(20) NOT NULL,
    first_and_last_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_fuec_passengers_fuec_uuid (fuec_uuid),
    INDEX idx_fuec_passengers_document_number (document_number),
    FOREIGN KEY (fuec_uuid) REFERENCES fuec (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (type_of_document_uuid) REFERENCES type_of_documents (uuid) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ===================================================================================================================
-- 7. GESTIÓN DE TRÁMITES Y ARCHIVOS
-- ===================================================================================================================
CREATE TABLE IF NOT EXISTS operation_card_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    operation_card_type ENUM('NUEVA', 'RENOVACION', 'CAMBIO_DE_EMPRESA', 'DUPLICADO', 'MODIFICACION') NOT NULL,
    vehicle_uuid CHAR(36) NOT NULL,
    request_date DATE NOT NULL,
    filing_date DATE,
    filing_number VARCHAR(100),
    approval_date DATE,
    issue_date DATE,
    expiry_date DATE,
    notes TEXT,
    status ENUM(
        'EN_PROCESO', 'DOCUMENTACION_COMPLETA', 'RADICADO', 'EN_REVISION', 'APROBADO',
        'EXPEDIDO', 'RECHAZADO', 'VENCIDO', 'RENOVACION_PENDIENTE'
    ) NOT NULL DEFAULT 'EN_PROCESO',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_uuid) REFERENCES vehicles (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS request_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    request_uuid CHAR(36) NOT NULL,
    document_type ENUM(
        'CARTA_ACEPTACION', 'CAPACIDAD_TRANSPORTADORA', 'SOLICITUD_POLIZA_RCC',
        'SOLICITUD_POLIZA_RCE', 'TARJETA_OPERACION', 'PODER_REPRESENTACION', 'OTRO'
    ) NOT NULL,
    file_name VARCHAR(255) NULL,
    file_url VARCHAR(500) NULL,
    file_size_kb INT,
    mime_type VARCHAR(100) NULL DEFAULT 'application/pdf',
    is_valid TINYINT(1) NULL DEFAULT 1,
    invalidation_reason VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (request_uuid) REFERENCES operation_card_requests (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS files (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    fileable_type VARCHAR(100) NOT NULL,
    fileable_uuid CHAR(36) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    path VARCHAR(500) NOT NULL,
    disk VARCHAR(50) DEFAULT 'public',
    mime_type VARCHAR(100) NOT NULL,
    extension VARCHAR(10) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    file_type ENUM(
        'LOGO', 'SIGNATURE', 'PROFILE_PHOTO', 'VEHICLE_PHOTO', 'DOCUMENTO', 'SOAT', 'RTM',
        'LICENCIA', 'RESOLUCIÓN', 'OPERATION_CARD', 'MAINTENANCE_PHOTO', 'FACTURA', 'RECIBO',
        'CONTRACT', 'FUEC', 'OTRO'
    ) DEFAULT 'DOCUMENTO',
    display_order TINYINT UNSIGNED DEFAULT 1,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    meta JSON NULL,
    uploaded_by_uuid CHAR(36) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_fileable (fileable_type, fileable_uuid),
    INDEX idx_file_type (file_type),
    INDEX idx_created_at (created_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS licenses (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL UNIQUE,
    license_key VARCHAR(64) UNIQUE NOT NULL,
    company_uuid CHAR(36) NOT NULL,
    license_type VARCHAR(50) NOT NULL,
    period_type ENUM('VITALICIA', 'MENSUAL', 'TRIMESTRAL', 'ANUAL') NOT NULL DEFAULT 'MENSUAL',
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    max_devices INT DEFAULT 1,
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    app_version VARCHAR(20) DEFAULT '1.0.0',
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    id CHAR(36) PRIMARY KEY COMMENT 'UUID de la notificación',
    company_uuid CHAR(36) NOT NULL COMMENT 'UUID de la empresa para multitenancy',
    type VARCHAR(255) NOT NULL COMMENT 'Tipo de notificación (clase)',
    notifiable_type VARCHAR(255) NOT NULL COMMENT 'Tipo de modelo notificado',
    notifiable_id CHAR(36) NOT NULL COMMENT 'UUID del modelo notificado',
    data TEXT NOT NULL COMMENT 'Datos JSON de la notificación',
    read_at TIMESTAMP NULL COMMENT 'Fecha de lectura',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_notifications_company_uuid (company_uuid),
    INDEX idx_notifications_notifiable (notifiable_type, notifiable_id),
    FOREIGN KEY (company_uuid) REFERENCES companies (uuid) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;