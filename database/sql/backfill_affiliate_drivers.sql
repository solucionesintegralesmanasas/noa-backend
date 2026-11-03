-- =============================================================================
-- Relleno de affiliate_drivers desde owners_drivers (legado)
-- =============================================================================
-- Uso: ejecutar DESPUÉS de importar el dump de producción, para que
--      owners_drivers y driver_licenses ya tengan sus datos.
--
--   mysql -u root -p nombre_bd < database/sql/backfill_affiliate_drivers.sql
--
-- Es idempotente: se puede ejecutar varias veces sin duplicar filas.
-- No borra ni modifica third_parties, owners_drivers ni driver_licenses.
-- Requiere que la tabla affiliate_drivers exista (migraciones 2026_11_01_*).
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 1. Insertar todos los pares conductor-afiliado del legado.
--    Cada licencia enlaza su conductor (driver_licenses.third_party_uuid) con
--    su propietario (owners_drivers.third_party_uuid). INSERT IGNORE descarta
--    los pares que ya existen por la llave única.
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO affiliate_drivers
    (uuid, company_uuid, driver_uuid, affiliate_uuid, is_primary, is_active, created_at, updated_at)
SELECT
    UUID(),
    od.company_uuid,
    dl.third_party_uuid,
    od.third_party_uuid,
    0,
    1,
    NOW(),
    NOW()
FROM owners_drivers od
INNER JOIN driver_licenses dl ON dl.uuid = od.driver_license_uuid
WHERE dl.third_party_uuid IS NOT NULL
  AND od.third_party_uuid IS NOT NULL
GROUP BY od.company_uuid, dl.third_party_uuid, od.third_party_uuid;

-- -----------------------------------------------------------------------------
-- 2. Marcar un único propietario principal por conductor.
--    Es el afiliado de la licencia más reciente (vencimiento, luego id), igual
--    que la migración 2026_11_01_000002. Se limpia antes para que el resultado
--    no dependa del estado previo.
-- -----------------------------------------------------------------------------
UPDATE affiliate_drivers SET is_primary = 0;

UPDATE affiliate_drivers a
INNER JOIN (
    SELECT x.company_uuid, x.driver_uuid, x.affiliate_uuid
    FROM (
        SELECT
            od.company_uuid,
            dl.third_party_uuid AS driver_uuid,
            od.third_party_uuid AS affiliate_uuid,
            ROW_NUMBER() OVER (
                PARTITION BY dl.third_party_uuid
                ORDER BY dl.expiration_date DESC, dl.id DESC
            ) AS rn
        FROM owners_drivers od
        INNER JOIN driver_licenses dl ON dl.uuid = od.driver_license_uuid
        WHERE dl.third_party_uuid IS NOT NULL
          AND od.third_party_uuid IS NOT NULL
    ) x
    WHERE x.rn = 1
) p
    ON  p.company_uuid   = a.company_uuid
    AND p.driver_uuid    = a.driver_uuid
    AND p.affiliate_uuid = a.affiliate_uuid
SET a.is_primary = 1,
    a.updated_at = NOW();

-- -----------------------------------------------------------------------------
-- 3. Verificación (todo debe dar 0 salvo la última columna informativa).
-- -----------------------------------------------------------------------------
SELECT 'vinculos_totales' AS control, COUNT(*) AS valor FROM affiliate_drivers
UNION ALL
SELECT 'conductores_distintos', COUNT(DISTINCT driver_uuid) FROM affiliate_drivers
UNION ALL
SELECT 'primarios_activos', COUNT(*) FROM affiliate_drivers WHERE is_primary = 1 AND is_active = 1
UNION ALL
SELECT 'conductores_con_mas_de_un_primario', COUNT(*) FROM (
    SELECT driver_uuid
    FROM affiliate_drivers
    WHERE is_primary = 1 AND is_active = 1
    GROUP BY driver_uuid
    HAVING COUNT(*) > 1
) d
UNION ALL
SELECT 'conductores_sin_primario', COUNT(*) FROM (
    SELECT a.driver_uuid
    FROM affiliate_drivers a
    LEFT JOIN affiliate_drivers p
           ON p.driver_uuid = a.driver_uuid
          AND p.is_primary = 1
          AND p.is_active = 1
    WHERE a.is_active = 1 AND p.driver_uuid IS NULL
    GROUP BY a.driver_uuid
) d
UNION ALL
SELECT 'pares_legado_faltantes', COUNT(*) FROM (
    SELECT od.company_uuid, dl.third_party_uuid AS driver_uuid, od.third_party_uuid AS affiliate_uuid
    FROM owners_drivers od
    INNER JOIN driver_licenses dl ON dl.uuid = od.driver_license_uuid
    WHERE dl.third_party_uuid IS NOT NULL
      AND od.third_party_uuid IS NOT NULL
    GROUP BY od.company_uuid, dl.third_party_uuid, od.third_party_uuid
) l
WHERE NOT EXISTS (
    SELECT 1 FROM affiliate_drivers a
    WHERE a.company_uuid   = l.company_uuid
      AND a.driver_uuid    = l.driver_uuid
      AND a.affiliate_uuid = l.affiliate_uuid
);
