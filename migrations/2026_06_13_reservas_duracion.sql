-- CQuezadaSkin - Store appointment duration in a single reservation row.
-- Safe additive migration. Existing rows keep the previous 30 minute meaning.

SET @citas_duracion_min_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'citas'
      AND COLUMN_NAME = 'duracion_min'
);
SET @sql := IF(
    @citas_duracion_min_exists = 0,
    'ALTER TABLE citas ADD COLUMN duracion_min INT NOT NULL DEFAULT 30 AFTER hora',
    'SELECT "citas.duracion_min already exists"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
