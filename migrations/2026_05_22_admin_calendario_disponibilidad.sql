-- CQuezadaSkin - Admin calendar/availability metadata
-- Safe additive migration. Uses horarios_disponibles as availability and blocking source.

SET @horarios_tipo_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'horarios_disponibles'
      AND COLUMN_NAME = 'tipo'
);
SET @sql := IF(
    @horarios_tipo_exists = 0,
    'ALTER TABLE horarios_disponibles ADD COLUMN tipo ENUM(''disponible'',''bloqueo'') NOT NULL DEFAULT ''disponible'' AFTER activo',
    'SELECT "horarios_disponibles.tipo already exists"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @horarios_motivo_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'horarios_disponibles'
      AND COLUMN_NAME = 'motivo'
);
SET @sql := IF(
    @horarios_motivo_exists = 0,
    'ALTER TABLE horarios_disponibles ADD COLUMN motivo VARCHAR(255) NULL AFTER tipo',
    'SELECT "horarios_disponibles.motivo already exists"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @horarios_updated_at_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'horarios_disponibles'
      AND COLUMN_NAME = 'updated_at'
);
SET @sql := IF(
    @horarios_updated_at_exists = 0,
    'ALTER TABLE horarios_disponibles ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER motivo',
    'SELECT "horarios_disponibles.updated_at already exists"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
