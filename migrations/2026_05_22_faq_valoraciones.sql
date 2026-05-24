-- CQuezadaSkin - FAQ and client reviews/testimonials
-- Safe additive migration. Keeps existing faq/testimonio data.

CREATE TABLE IF NOT EXISTS faq (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pregunta VARCHAR(255) NOT NULL,
    respuesta TEXT NOT NULL,
    categoria VARCHAR(100) NULL,
    orden INT DEFAULT 0,
    activo TINYINT(1) DEFAULT 1,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @faq_categoria_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'faq' AND COLUMN_NAME = 'categoria'
);
SET @sql := IF(@faq_categoria_exists = 0,
    'ALTER TABLE faq ADD COLUMN categoria VARCHAR(100) NULL AFTER respuesta',
    'SELECT "faq.categoria already exists"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @faq_updated_at_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'faq' AND COLUMN_NAME = 'updated_at'
);
SET @sql := IF(@faq_updated_at_exists = 0,
    'ALTER TABLE faq ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER creado_en',
    'SELECT "faq.updated_at already exists"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS testimonio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NULL,
    texto TEXT NULL,
    foto_url VARCHAR(255) NULL,
    activo TINYINT(1) DEFAULT 1,
    orden INT UNSIGNED DEFAULT 0,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @testimonio_cliente_id_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'testimonio' AND COLUMN_NAME = 'cliente_id'
);
SET @sql := IF(@testimonio_cliente_id_exists = 0,
    'ALTER TABLE testimonio ADD COLUMN cliente_id INT NULL AFTER id',
    'SELECT "testimonio.cliente_id already exists"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @testimonio_cita_id_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'testimonio' AND COLUMN_NAME = 'cita_id'
);
SET @sql := IF(@testimonio_cita_id_exists = 0,
    'ALTER TABLE testimonio ADD COLUMN cita_id INT NULL AFTER cliente_id',
    'SELECT "testimonio.cita_id already exists"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @testimonio_nombre_mostrado_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'testimonio' AND COLUMN_NAME = 'nombre_mostrado'
);
SET @sql := IF(@testimonio_nombre_mostrado_exists = 0,
    'ALTER TABLE testimonio ADD COLUMN nombre_mostrado VARCHAR(120) NULL AFTER cita_id',
    'SELECT "testimonio.nombre_mostrado already exists"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @testimonio_comentario_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'testimonio' AND COLUMN_NAME = 'comentario'
);
SET @sql := IF(@testimonio_comentario_exists = 0,
    'ALTER TABLE testimonio ADD COLUMN comentario TEXT NULL AFTER nombre_mostrado',
    'SELECT "testimonio.comentario already exists"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @testimonio_puntuacion_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'testimonio' AND COLUMN_NAME = 'puntuacion'
);
SET @sql := IF(@testimonio_puntuacion_exists = 0,
    'ALTER TABLE testimonio ADD COLUMN puntuacion TINYINT NULL AFTER comentario',
    'SELECT "testimonio.puntuacion already exists"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @testimonio_estado_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'testimonio' AND COLUMN_NAME = 'estado'
);
SET @sql := IF(@testimonio_estado_exists = 0,
    'ALTER TABLE testimonio ADD COLUMN estado ENUM(''pendiente'',''aprobado'',''rechazado'') NOT NULL DEFAULT ''pendiente'' AFTER puntuacion',
    'SELECT "testimonio.estado already exists"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @testimonio_visible_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'testimonio' AND COLUMN_NAME = 'visible'
);
SET @sql := IF(@testimonio_visible_exists = 0,
    'ALTER TABLE testimonio ADD COLUMN visible TINYINT(1) NOT NULL DEFAULT 0 AFTER estado',
    'SELECT "testimonio.visible already exists"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @testimonio_respuesta_admin_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'testimonio' AND COLUMN_NAME = 'respuesta_admin'
);
SET @sql := IF(@testimonio_respuesta_admin_exists = 0,
    'ALTER TABLE testimonio ADD COLUMN respuesta_admin TEXT NULL AFTER visible',
    'SELECT "testimonio.respuesta_admin already exists"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @testimonio_creado_en_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'testimonio' AND COLUMN_NAME = 'creado_en'
);
SET @sql := IF(@testimonio_creado_en_exists = 0,
    'ALTER TABLE testimonio ADD COLUMN creado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER orden',
    'SELECT "testimonio.creado_en already exists"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @testimonio_updated_at_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'testimonio' AND COLUMN_NAME = 'updated_at'
);
SET @sql := IF(@testimonio_updated_at_exists = 0,
    'ALTER TABLE testimonio ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER creado_en',
    'SELECT "testimonio.updated_at already exists"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE testimonio
SET
    nombre_mostrado = COALESCE(nombre_mostrado, nombre),
    comentario = COALESCE(comentario, texto),
    puntuacion = COALESCE(puntuacion, 5),
    estado = CASE WHEN activo = 1 THEN 'aprobado' ELSE estado END,
    visible = CASE WHEN activo = 1 THEN 1 ELSE visible END
WHERE cliente_id IS NULL;
