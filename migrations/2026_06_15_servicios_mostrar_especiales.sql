-- Agrega bandera para separar Servicios, Especiales y Empresas sin inferir por texto.
SET @column_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'servicios'
    AND COLUMN_NAME = 'mostrar_especiales'
);

SET @ddl := IF(
  @column_exists = 0,
  'ALTER TABLE servicios ADD COLUMN mostrar_especiales TINYINT(1) NOT NULL DEFAULT 0 AFTER mostrar_servicios',
  'SELECT 1'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'servicios'
    AND INDEX_NAME = 'idx_servicios_especiales'
);

SET @idx_ddl := IF(
  @index_exists = 0,
  'CREATE INDEX idx_servicios_especiales ON servicios (activo, mostrar_especiales, orden, id)',
  'SELECT 1'
);

PREPARE stmt FROM @idx_ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
