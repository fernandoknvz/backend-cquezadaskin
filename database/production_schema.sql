-- CQuezadaSkin production schema for a clean Railway MySQL database.
-- Base reviewed: c2701519_cqs.sql.
-- Incorporated migrations:
--   migrations/2026_04_26_client_mvp.sql
--   migrations/2026_04_27_client_privacy_law_19628.sql
--   migrations/2026_04_27_client_rut_unique.sql
--   migrations/2026_05_21_admin_email_auth.sql
--   migrations/2026_05_22_admin_calendario_disponibilidad.sql
--   migrations/2026_05_22_admin_clientes_reservas.sql
--   migrations/2026_05_22_faq_valoraciones.sql
--
-- This file intentionally does not create the database and does not insert
-- development admin users, customer data, appointments, refresh tokens, or
-- password reset tokens.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;

START TRANSACTION;

CREATE TABLE `about_content` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `titulo` VARCHAR(120) DEFAULT 'Sobre mi',
  `texto` TEXT,
  `imagen_url` VARCHAR(255) DEFAULT NULL,
  `activo` TINYINT(1) DEFAULT 1,
  `actualizado_en` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `categorias_servicio` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(100) NOT NULL,
  `descripcion` TEXT,
  `imagen_url` VARCHAR(255) DEFAULT NULL,
  `activo` TINYINT(1) DEFAULT 1,
  `orden` INT DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `clientes` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(100) NOT NULL,
  `rut` VARCHAR(12) DEFAULT NULL,
  `correo` VARCHAR(100) DEFAULT NULL,
  `telefono` VARCHAR(20) DEFAULT NULL,
  `password_hash` VARCHAR(255) DEFAULT NULL,
  `acepta_politica` TINYINT(1) NOT NULL DEFAULT 0,
  `fecha_aceptacion` DATETIME DEFAULT NULL,
  `acepta_promociones` TINYINT(1) NOT NULL DEFAULT 0,
  `notas_admin` TEXT,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `creado_en` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_clientes_correo` (`correo`),
  UNIQUE KEY `uniq_clientes_rut` (`rut`),
  KEY `idx_clientes_acepta_politica` (`acepta_politica`),
  KEY `idx_clientes_activo` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `contacto` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(120) NOT NULL,
  `telefono` VARCHAR(20) DEFAULT NULL,
  `email` VARCHAR(254) DEFAULT NULL,
  `mensaje` TEXT NOT NULL,
  `recibido_en` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `gestionado` TINYINT(1) DEFAULT 0,
  `notas_gestion` TEXT,
  PRIMARY KEY (`id`),
  KEY `idx_contacto_recibido_en` (`recibido_en`),
  KEY `idx_contacto_gestionado` (`gestionado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `faq` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `pregunta` VARCHAR(255) NOT NULL,
  `respuesta` TEXT NOT NULL,
  `categoria` VARCHAR(100) DEFAULT NULL,
  `orden` INT DEFAULT 0,
  `activo` TINYINT(1) DEFAULT 1,
  `creado_en` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_faq_public` (`activo`, `orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `home_content` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `titulo` VARCHAR(180) DEFAULT NULL,
  `subtitulo` VARCHAR(240) DEFAULT NULL,
  `imagen_url` VARCHAR(255) DEFAULT NULL,
  `video_embed` VARCHAR(255) DEFAULT NULL,
  `actualizado_en` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `horarios_disponibles` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `fecha` DATE NOT NULL,
  `hora` TIME NOT NULL,
  `activo` TINYINT(1) DEFAULT 1,
  `tipo` ENUM('disponible','bloqueo') NOT NULL DEFAULT 'disponible',
  `motivo` VARCHAR(255) DEFAULT NULL,
  `creado_en` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_horario_fecha_hora` (`fecha`, `hora`),
  KEY `idx_horarios_fecha_activo` (`fecha`, `activo`),
  KEY `idx_horarios_tipo` (`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `instagram_post` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `embed_url` VARCHAR(255) DEFAULT NULL,
  `activo` TINYINT(1) DEFAULT 1,
  `orden` INT UNSIGNED DEFAULT 0,
  `actualizado_en` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_instagram_public` (`activo`, `orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `post_care_tip` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `icono` VARCHAR(16) DEFAULT NULL,
  `titulo` VARCHAR(80) DEFAULT NULL,
  `texto` VARCHAR(240) DEFAULT NULL,
  `activo` TINYINT(1) DEFAULT 1,
  `orden` INT UNSIGNED DEFAULT 0,
  `actualizado_en` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_post_care_public` (`activo`, `orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `site_config` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `whatsapp` VARCHAR(20) DEFAULT NULL,
  `whatsapp_message` VARCHAR(200) DEFAULT NULL,
  `instagram_profile_url` VARCHAR(255) DEFAULT NULL,
  `actualizado_en` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `usuarios_admin` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL,
  `email` VARCHAR(190) DEFAULT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `rol` ENUM('admin','superadmin') DEFAULT 'admin',
  `creado_en` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `usuarios_admin_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `servicios` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `categoria_id` INT DEFAULT NULL,
  `nombre` VARCHAR(150) NOT NULL,
  `etiqueta` VARCHAR(60) DEFAULT NULL,
  `subtitulo` VARCHAR(150) DEFAULT NULL,
  `descripcion` TEXT,
  `beneficios` TEXT,
  `imagen_url` VARCHAR(255) DEFAULT NULL,
  `precio` DECIMAL(10,2) DEFAULT NULL,
  `cta_primary_label` VARCHAR(120) DEFAULT NULL,
  `cta_primary_url` VARCHAR(255) DEFAULT NULL,
  `cta_secondary_label` VARCHAR(120) DEFAULT NULL,
  `cta_secondary_url` VARCHAR(255) DEFAULT NULL,
  `mostrar_servicios` TINYINT(1) DEFAULT 0,
  `mostrar_empresas` TINYINT(1) DEFAULT 0,
  `activo` TINYINT(1) DEFAULT 1,
  `orden` INT DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `categoria_id` (`categoria_id`),
  KEY `idx_servicios_public` (`activo`, `orden`),
  CONSTRAINT `servicios_ibfk_1` FOREIGN KEY (`categoria_id`) REFERENCES `categorias_servicio` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `citas` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `cliente_id` INT NOT NULL,
  `servicio_id` INT NOT NULL,
  `fecha` DATETIME NOT NULL,
  `hora` TIME DEFAULT NULL,
  `estado` ENUM('solicitada','pendiente','confirmada','cancelada','completada','reagendada') DEFAULT 'pendiente',
  `observacion_admin` TEXT,
  `creado_en` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `cliente_id` (`cliente_id`),
  KEY `servicio_id` (`servicio_id`),
  KEY `idx_citas_fecha_estado` (`fecha`, `estado`),
  CONSTRAINT `citas_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `citas_ibfk_2` FOREIGN KEY (`servicio_id`) REFERENCES `servicios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `password_resets` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `token_hash` VARCHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `usuarios_admin` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `refresh_tokens` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `token` VARCHAR(120) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `creado_en` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `refresh_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `usuarios_admin` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `testimonio` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `cliente_id` INT DEFAULT NULL,
  `cita_id` INT DEFAULT NULL,
  `nombre_mostrado` VARCHAR(120) DEFAULT NULL,
  `comentario` TEXT,
  `puntuacion` TINYINT DEFAULT NULL,
  `estado` ENUM('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'pendiente',
  `visible` TINYINT(1) NOT NULL DEFAULT 0,
  `respuesta_admin` TEXT,
  `nombre` VARCHAR(120) DEFAULT NULL,
  `texto` TEXT,
  `foto_url` VARCHAR(255) DEFAULT NULL,
  `activo` TINYINT(1) DEFAULT 1,
  `orden` INT UNSIGNED DEFAULT 0,
  `creado_en` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `actualizado_en` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_testimonio_public` (`visible`, `estado`, `activo`, `orden`),
  KEY `idx_testimonio_cliente` (`cliente_id`),
  KEY `idx_testimonio_cita` (`cita_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `about_content` (`id`, `titulo`, `texto`, `imagen_url`, `activo`) VALUES
(1, 'Sobre mi', 'Contenido inicial editable desde el panel de administracion.', NULL, 1);

INSERT INTO `categorias_servicio` (`id`, `nombre`, `descripcion`, `imagen_url`, `activo`, `orden`) VALUES
(1, 'Limpieza Facial', 'Tratamientos faciales personalizados.', NULL, 1, 1),
(2, 'Tratamientos Corporales', 'Tratamientos corporales y bienestar.', NULL, 1, 2),
(3, 'Depilacion', 'Servicios esteticos de depilacion y cejas.', NULL, 1, 3),
(4, 'Fibroblasting', 'Tratamientos con plasma y precision estetica.', NULL, 1, 4),
(5, 'Camuflajes', 'Camuflaje estetico de marcas y cicatrices.', NULL, 1, 5);

INSERT INTO `home_content` (`id`, `titulo`, `subtitulo`, `imagen_url`, `video_embed`) VALUES
(1, 'Cuidado profesional para tu piel', 'Tratamientos faciales y corporales personalizados.', NULL, NULL);

INSERT INTO `servicios`
  (`id`, `categoria_id`, `nombre`, `etiqueta`, `subtitulo`, `descripcion`, `beneficios`, `imagen_url`, `precio`, `cta_primary_label`, `cta_primary_url`, `cta_secondary_label`, `cta_secondary_url`, `mostrar_servicios`, `mostrar_empresas`, `activo`, `orden`)
VALUES
(1, 1, 'Limpieza Facial Premium', 'Facial', 'Limpieza facial', 'Evaluacion, higienizacion, exfoliacion, extraccion e hidratacion profunda.', '["Evaluacion personalizada","Limpieza profunda","Hidratacion","Rutina de cuidado"]', NULL, NULL, 'Agendar', '/contacto', 'Ver disponibilidad', '/contacto', 1, 0, 1, 1),
(2, 1, 'Limpieza Facial Deluxe', 'Facial', 'Experiencia completa', 'Tratamiento facial integral con aparatologia y mascarilla premium.', '["Diagnostico de piel","Tratamiento integral","Fototerapia LED","Proteccion solar"]', NULL, NULL, 'Agendar', '/contacto', 'Consultar', '/contacto', 1, 0, 1, 2),
(3, 2, 'Masaje Relajante', 'Corporal', 'Bienestar corporal', 'Sesion orientada a relajar tensiones y mejorar la sensacion de descanso.', '["Relajacion","Bienestar","Alivio de tension","Atencion personalizada"]', NULL, NULL, 'Agendar', '/contacto', 'Consultar', '/contacto', 1, 0, 1, 3),
(4, 3, 'Perfilado de Cejas', 'Depilacion', 'Diseno y perfilado', 'Servicio estetico de diseno, perfilado y cuidado de cejas.', '["Diseno personalizado","Acabado limpio","Asesoria de cuidado"]', NULL, NULL, 'Agendar', '/contacto', 'Consultar', '/contacto', 1, 0, 1, 4);

INSERT INTO `site_config` (`id`, `whatsapp`, `whatsapp_message`, `instagram_profile_url`) VALUES
(1, NULL, 'Hola, me gustaria agendar una cita', NULL);

INSERT INTO `faq` (`id`, `pregunta`, `respuesta`, `categoria`, `orden`, `activo`) VALUES
(1, 'Como puedo agendar una hora?', 'Puedes solicitar una cita desde el sitio o por el canal de contacto configurado.', 'Agenda', 1, 1),
(2, 'Con cuanta anticipacion debo agendar?', 'Se recomienda agendar con anticipacion para asegurar disponibilidad.', 'Agenda', 2, 1),
(3, 'Que formas de pago aceptan?', 'Configura las formas de pago vigentes desde el contenido del sitio o comunicalas al confirmar la cita.', 'Pagos', 3, 1);

INSERT INTO `post_care_tip` (`id`, `icono`, `titulo`, `texto`, `activo`, `orden`) VALUES
(1, 'SPF', 'Usa protector solar', 'Aplica proteccion solar segun la indicacion posterior al tratamiento.', 1, 1),
(2, 'H2O', 'Mantente hidratada', 'Hidrata tu piel y sigue las recomendaciones entregadas en la cita.', 1, 2);

COMMIT;
