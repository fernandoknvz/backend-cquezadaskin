-- CQuezadaSkin - Admin clientes/reservas support
-- Safe additive migration. Does not delete rows or modify password hashes.

SET @clientes_notas_admin_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'clientes'
      AND COLUMN_NAME = 'notas_admin'
);
SET @sql := IF(
    @clientes_notas_admin_exists = 0,
    'ALTER TABLE clientes ADD COLUMN notas_admin TEXT NULL AFTER acepta_promociones',
    'SELECT "clientes.notas_admin already exists"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @clientes_activo_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'clientes'
      AND COLUMN_NAME = 'activo'
);
SET @sql := IF(
    @clientes_activo_exists = 0,
    'ALTER TABLE clientes ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1 AFTER notas_admin',
    'SELECT "clientes.activo already exists"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @clientes_updated_at_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'clientes'
      AND COLUMN_NAME = 'updated_at'
);
SET @sql := IF(
    @clientes_updated_at_exists = 0,
    'ALTER TABLE clientes ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER activo',
    'SELECT "clientes.updated_at already exists"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE citas
    MODIFY estado ENUM('solicitada','pendiente','confirmada','cancelada','completada','reagendada')
    NULL DEFAULT 'pendiente';

SET @citas_observacion_admin_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'citas'
      AND COLUMN_NAME = 'observacion_admin'
);
SET @sql := IF(
    @citas_observacion_admin_exists = 0,
    'ALTER TABLE citas ADD COLUMN observacion_admin TEXT NULL AFTER estado',
    'SELECT "citas.observacion_admin already exists"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @citas_updated_at_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'citas'
      AND COLUMN_NAME = 'updated_at'
);
SET @sql := IF(
    @citas_updated_at_exists = 0,
    'ALTER TABLE citas ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER observacion_admin',
    'SELECT "citas.updated_at already exists"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
