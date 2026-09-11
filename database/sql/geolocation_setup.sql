-- phpMyAdmin SQL Script for Geolocation System
-- Host: Shared Hosting - Manual import via phpMyAdmin > Import
-- Database: soluci15_nexttransporte-db

-- --------------------------------------------------------
-- Table: geofences
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `geofences` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `company_uuid` char(36) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text NULL,
  `type` enum('circle','polygon') NOT NULL DEFAULT 'circle',
  `center_lat` decimal(10,8) NULL,
  `center_lng` decimal(11,8) NULL,
  `radius_meters` int UNSIGNED NULL,
  `polygon_points` json NULL,
  `alert_on_enter` tinyint(1) NOT NULL DEFAULT 1,
  `alert_on_exit` tinyint(1) NOT NULL DEFAULT 1,
  `max_speed_kmh` int UNSIGNED NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uuid` (`uuid`),
  KEY `idx_geofences_company` (`company_uuid`),
  CONSTRAINT `fk_geofences_company` FOREIGN KEY (`company_uuid`) REFERENCES `companies`(`uuid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: driver_locations
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `driver_locations` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `company_uuid` char(36) NOT NULL,
  `third_party_uuid` char(36) NOT NULL,
  `vehicle_uuid` char(36) NULL,
  `project_uuid` char(36) NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `altitude` decimal(10,2) NULL,
  `speed` decimal(6,2) NOT NULL DEFAULT 0,
  `heading` decimal(5,2) NULL,
  `accuracy` decimal(8,2) NULL,
  `battery_level` tinyint UNSIGNED NULL,
  `is_moving` tinyint(1) NOT NULL DEFAULT 0,
  `source` enum('gps','network','fused') NOT NULL DEFAULT 'gps',
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uuid` (`uuid`),
  KEY `idx_dl_company_recorded` (`company_uuid`, `recorded_at`),
  KEY `idx_dl_driver_recorded` (`third_party_uuid`, `recorded_at`),
  KEY `idx_dl_vehicle` (`vehicle_uuid`),
  KEY `idx_dl_recorded_at` (`recorded_at`),
  CONSTRAINT `fk_dl_company` FOREIGN KEY (`company_uuid`) REFERENCES `companies`(`uuid`) ON DELETE CASCADE,
  CONSTRAINT `fk_dl_driver` FOREIGN KEY (`third_party_uuid`) REFERENCES `third_parties`(`uuid`) ON DELETE CASCADE,
  CONSTRAINT `fk_dl_vehicle` FOREIGN KEY (`vehicle_uuid`) REFERENCES `vehicles`(`uuid`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: driver_location_sessions
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `driver_location_sessions` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `company_uuid` char(36) NOT NULL,
  `third_party_uuid` char(36) NOT NULL,
  `vehicle_uuid` char(36) NULL,
  `project_uuid` char(36) NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ended_at` timestamp NULL DEFAULT NULL,
  `status` enum('active','paused','ended') NOT NULL DEFAULT 'active',
  `total_distance_km` decimal(10,2) NOT NULL DEFAULT 0,
  `total_points` int UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uuid` (`uuid`),
  KEY `idx_dls_company_status` (`company_uuid`, `status`),
  KEY `idx_dls_driver_status` (`third_party_uuid`, `status`),
  CONSTRAINT `fk_dls_company` FOREIGN KEY (`company_uuid`) REFERENCES `companies`(`uuid`) ON DELETE CASCADE,
  CONSTRAINT `fk_dls_driver` FOREIGN KEY (`third_party_uuid`) REFERENCES `third_parties`(`uuid`) ON DELETE CASCADE,
  CONSTRAINT `fk_dls_vehicle` FOREIGN KEY (`vehicle_uuid`) REFERENCES `vehicles`(`uuid`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: driver_location_alerts
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `driver_location_alerts` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `company_uuid` char(36) NOT NULL,
  `third_party_uuid` char(36) NOT NULL,
  `driver_location_uuid` char(36) NULL,
  `alert_type` enum('geofence_enter','geofence_exit','overspeed','idle','panic') NOT NULL,
  `geofence_uuid` char(36) NULL,
  `message` text NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uuid` (`uuid`),
  KEY `idx_dla_company_read` (`company_uuid`, `is_read`),
  KEY `idx_dla_driver` (`third_party_uuid`),
  KEY `idx_dla_created_at` (`created_at`),
  CONSTRAINT `fk_dla_company` FOREIGN KEY (`company_uuid`) REFERENCES `companies`(`uuid`) ON DELETE CASCADE,
  CONSTRAINT `fk_dla_driver` FOREIGN KEY (`third_party_uuid`) REFERENCES `third_parties`(`uuid`) ON DELETE CASCADE,
  CONSTRAINT `fk_dla_location` FOREIGN KEY (`driver_location_uuid`) REFERENCES `driver_locations`(`uuid`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Index optimizations
-- NOTA: MySQL no soporta "CREATE INDEX IF NOT EXISTS".
-- Ejecutar estos índices UNA sola vez (o ignorarlos si las
-- tablas son nuevas, ya que los índices compuestos básicos
-- se definieron dentro de cada CREATE TABLE).
-- --------------------------------------------------------

-- CREATE INDEX `idx_dl_vehicle_active` ON `driver_locations`(`vehicle_uuid`, `is_moving`, `recorded_at`);
-- CREATE INDEX `idx_dl_driver_last` ON `driver_locations`(`third_party_uuid`, `recorded_at`);
-- CREATE INDEX `idx_geofences_active` ON `geofences`(`is_active`, `company_uuid`);

-- --------------------------------------------------------
-- Sample initial data - Geofences for demo companies
-- Los UUIDs de empresa se resuelven dinámicamente para no
-- romper las llaves foráneas (ejecutar tras tener empresas).
-- --------------------------------------------------------

INSERT INTO `geofences` (`uuid`, `company_uuid`, `name`, `description`, `type`, `center_lat`, `center_lng`, `radius_meters`, `alert_on_enter`, `alert_on_exit`, `is_active`, `created_at`, `updated_at`) VALUES
('gf-001', (SELECT `uuid` FROM `companies` ORDER BY `created_at` ASC LIMIT 1), 'Zona Centro', 'Zona central de la ciudad', 'circle', 4.710993, -74.072068, 500, 1, 1, 1, NOW(), NOW()),
('gf-002', (SELECT `uuid` FROM `companies` ORDER BY `created_at` ASC LIMIT 1), 'Zona Norte', 'Zona norte de operaciones', 'circle', 4.720993, -74.082068, 800, 1, 1, 1, NOW(), NOW()),
('gf-003', (SELECT `uuid` FROM `companies` ORDER BY `created_at` ASC LIMIT 1), 'Almacén Principal', 'Zona de carga y descarga', 'polygon', NULL, NULL, NULL, 1, 1, 1, NOW(), NOW()),
('gf-004', (SELECT `uuid` FROM `companies` ORDER BY `created_at` ASC LIMIT 1 OFFSET 1), 'Oficina Matriz', 'Oficina principal empresa 2', 'circle', 6.2442, -75.5789, 1000, 1, 1, 1, NOW(), NOW());

-- --------------------------------------------------------
-- Permisos de Geolocalización
-- --------------------------------------------------------

INSERT INTO `permissions` (`name`, `guard_name`, `module`, `description`, `created_at`, `updated_at`) VALUES
('locations.view',      'api', 'Geolocalización', 'Ver mapa de conductores en tiempo real', NOW(), NOW()),
('locations.track',     'api', 'Geolocalización', 'Enviar ubicación GPS (conductor)', NOW(), NOW()),
('locations.history',   'api', 'Geolocalización', 'Ver historial de rutas de conductores', NOW(), NOW()),
('locations.geofences', 'api', 'Geolocalización', 'Gestionar geocercas (crear, editar, eliminar)', NOW(), NOW()),
('locations.alerts',    'api', 'Geolocalización', 'Ver y gestionar alertas de ubicación', NOW(), NOW());

-- Asignación de permisos a roles (Spatie)
-- CONDUCTOR: solo puede enviar su ubicación GPS
INSERT IGNORE INTO `role_has_permissions` (`permission_id`, `role_id`)
SELECT p.id, r.id FROM `permissions` p, `roles` r
WHERE p.name = 'locations.track' AND r.name = 'CONDUCTOR' AND r.guard_name = 'api';

-- ADMIN_EMPRESA: todos los permisos de geolocalización
INSERT IGNORE INTO `role_has_permissions` (`permission_id`, `role_id`)
SELECT p.id, r.id FROM `permissions` p, `roles` r
WHERE p.name IN ('locations.view', 'locations.track', 'locations.history', 'locations.geofences', 'locations.alerts')
  AND r.name = 'ADMIN_EMPRESA' AND r.guard_name = 'api';

-- SUPERADMIN: acceso total a la geolocalización (mismo listado que ADMIN_EMPRESA)
INSERT IGNORE INTO `role_has_permissions` (`permission_id`, `role_id`)
SELECT p.id, r.id FROM `permissions` p, `roles` r
WHERE p.name IN ('locations.view', 'locations.track', 'locations.history', 'locations.geofences', 'locations.alerts')
  AND r.name = 'SUPERADMIN' AND r.guard_name = 'api';