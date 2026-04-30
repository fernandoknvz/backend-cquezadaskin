-- RUT obligatorio para nuevos clientes autenticados.
-- Se agrega NULL para no romper clientes historicos; MySQL permite multiples NULL en UNIQUE.

DROP PROCEDURE IF EXISTS add_clientes_rut_unique;

DELIMITER //
CREATE PROCEDURE add_clientes_rut_unique()
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'clientes'
      AND COLUMN_NAME = 'rut'
  ) THEN
    ALTER TABLE clientes
      ADD COLUMN rut varchar(12) NULL AFTER nombre;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'clientes'
      AND INDEX_NAME = 'uniq_clientes_rut'
  ) THEN
    ALTER TABLE clientes
      ADD UNIQUE KEY uniq_clientes_rut (rut);
  END IF;
END//
DELIMITER ;

CALL add_clientes_rut_unique();

DROP PROCEDURE IF EXISTS add_clientes_rut_unique;
