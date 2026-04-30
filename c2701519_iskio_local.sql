-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost
-- Tiempo de generación: 08-02-2026 a las 00:27:16
-- Versión del servidor: 8.0.42
-- Versión de PHP: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `c2701519_iskio`
--
CREATE DATABASE IF NOT EXISTS `c2701519_iskio` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `c2701519_iskio`;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `about_content`
--

CREATE TABLE `about_content` (
  `id` int NOT NULL,
  `titulo` varchar(120) DEFAULT 'Sobre mí',
  `texto` text,
  `imagen_url` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `actualizado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `about_content`
--

INSERT INTO `about_content` (`id`, `titulo`, `texto`, `imagen_url`, `activo`, `actualizado_en`) VALUES
(1, 'Sobre Mí', '¡Hola! Soy Constanza Quezada, estilista profesional con más de 5 años de experiencia en el mundo de la belleza. Me especializo en tratamientos faciales, corporales y depilación, combinando técnicas modernas con un enfoque personalizado para cada cliente. Mi pasión es ayudarte a sentirte segura, radiante y feliz con tu piel 💖\r\n', 'https://res.cloudinary.com/dsbqbjfp4/image/upload/v1762581253/c51f0637-5945-4d5b-9708-7404254113bb.png', 1, '2025-11-16 01:54:27');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias_servicio`
--

CREATE TABLE `categorias_servicio` (
  `id` int NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text,
  `imagen_url` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `orden` int DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `categorias_servicio`
--

INSERT INTO `categorias_servicio` (`id`, `nombre`, `descripcion`, `imagen_url`, `activo`, `orden`) VALUES
(10, 'Limpieza Facial', 'Tratamientos faciales como limpieza premium, deluxe, facialgym, dermaplaning, químicos y más.', 'https://res.cloudinary.com/dsbqbjfp4/image/upload/v1763000463/facial_mbaxsm.png', 1, 0),
(11, 'Tratamientos Corporales', 'Masajes relajantes, reductivos, drenajes, post operatorios, anticelulíticos y más.', 'https://res.cloudinary.com/dsbqbjfp4/image/upload/v1763000463/corporal_pxqrkd.png', 1, 1),
(12, 'Depilación', 'Lifting de pestañas, laminado, hidrabox, visagismo y perfilado profesional de cejas.', 'https://res.cloudinary.com/dsbqbjfp4/image/upload/v1763000463/lash_gdein5.png', 1, 2),
(13, 'Fibroblasting', 'Tratamientos con plasma para zona ocular, rostro, abdomen, escote, estrías y papada.', 'https://res.cloudinary.com/dsbqbjfp4/image/upload/v1763000463/fibroblast_oddkhk.png', 1, 3),
(14, 'Camuflajes', 'Restauración y camuflaje de estrías o cicatrices con técnicas de precisión y estética.', 'https://res.cloudinary.com/dsbqbjfp4/image/upload/v1763000464/camuflaje_oreibv.png', 1, 4),
(17, 'Masajes a domicilio', 'Elige tu experiencia principal. Ideal para aliviar tensión, descansar y recargar energía.', NULL, 1, 1),
(18, 'Regalos y promociones', 'Opciones para sorprender o regalonearte. Coordinación simple por contacto.', NULL, 1, 2),
(19, 'Bienestar corporativo', 'Sesiones pensadas para equipos: pausas saludables, alivio de tensión y un ambiente laboral más liviano.', NULL, 1, 10),
(20, 'Activaciones y eventos', 'Ideal para actividades internas, aniversarios, celebraciones o eventos corporativos. Llevamos la experiencia a tu lugar.', NULL, 1, 11),
(21, 'Beneficios y regalos corporativos', 'Giftcards y beneficios para colaboradores: un regalo útil y memorable, fácil de coordinar.', NULL, 1, 12);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `citas`
--

CREATE TABLE `citas` (
  `id` int NOT NULL,
  `cliente_id` int NOT NULL,
  `servicio_id` int NOT NULL,
  `fecha` datetime NOT NULL,
  `hora` time DEFAULT NULL,
  `estado` enum('pendiente','confirmada','cancelada') DEFAULT 'pendiente',
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

CREATE TABLE `clientes` (
  `id` int NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `correo` varchar(100) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `clientes`
--

INSERT INTO `clientes` (`id`, `nombre`, `correo`, `telefono`, `creado_en`) VALUES
(17, 'Pedro Ocares', 'pedroocaresescobar@gmail.com', '+56942008577', '2025-11-16 01:23:54'),
(18, 'Agenda Personalizada', 'ped.ocares@duocuc.cl', '+56994547622', '2025-11-16 02:19:18'),
(19, 'pedro', '007.pedro.ocares@gmail.com', '+56942008577', '2026-01-25 06:01:37');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `contacto`
--

CREATE TABLE `contacto` (
  `id` int NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(254) DEFAULT NULL,
  `mensaje` text NOT NULL,
  `recibido_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `gestionado` tinyint(1) DEFAULT '0',
  `notas_gestion` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `contacto`
--

INSERT INTO `contacto` (`id`, `nombre`, `telefono`, `email`, `mensaje`, `recibido_en`, `gestionado`, `notas_gestion`) VALUES
(1, 'Pedro', '942008577', 'pedro@gmail.com', 'Consulta sobre limpieza facial.', '2025-11-05 00:03:07', 0, NULL),
(2, 'Camila', '999888777', 'camila@example.com', '¿Atienden los sábados?', '2025-11-05 00:03:07', 0, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `faq`
--

CREATE TABLE `faq` (
  `id` int NOT NULL,
  `pregunta` varchar(255) NOT NULL,
  `respuesta` text NOT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `orden` int DEFAULT '0',
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `faq`
--

INSERT INTO `faq` (`id`, `pregunta`, `respuesta`, `activo`, `orden`, `creado_en`, `actualizado_en`) VALUES
(2, '¿Cuáles son las formas de pago?', 'Acepto pagos en efectivo, transferencia y también mediante tarjetas vía sistema SumUp.', 1, 2, '2025-11-13 04:23:21', '2025-11-16 00:18:29'),
(4, '¿Qué pasa si llego tarde a mi cita?', 'Si llegas con más de 15 minutos de atraso, es posible que debamos reagendar según la disponibilidad.', 1, 4, '2025-11-13 04:23:21', '2025-11-16 00:25:13'),
(16, '¿Cómo agendo una hora?', 'Puedes agendar escribiéndome directamente por WhatsApp mediante el botón flotante en el sitio.', 1, 1, '2025-11-16 01:42:40', '2025-11-16 02:02:47'),
(17, '¿Con cuánta anticipación debo agendar?', 'Idealmente con 2 o 3 días de anticipación para asegurar disponibilidad.', 1, 3, '2025-11-16 01:43:14', '2025-11-16 01:43:14');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `home_content`
--

CREATE TABLE `home_content` (
  `id` int NOT NULL,
  `titulo` varchar(180) DEFAULT NULL,
  `subtitulo` varchar(240) DEFAULT NULL,
  `imagen_url` varchar(255) DEFAULT NULL,
  `video_embed` varchar(255) DEFAULT NULL,
  `actualizado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `home_content`
--

INSERT INTO `home_content` (`id`, `titulo`, `subtitulo`, `imagen_url`, `video_embed`, `actualizado_en`) VALUES
(1, 'Cuidado profesional para tu piel', 'Tratamientos faciales y corporales personalizados.', 'https://cquezadaskin.cl/img/banner.jpg', '', '2025-11-05 00:03:07');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `horarios_disponibles`
--

CREATE TABLE `horarios_disponibles` (
  `id` int NOT NULL,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `horarios_disponibles`
--

INSERT INTO `horarios_disponibles` (`id`, `fecha`, `hora`, `activo`, `creado_en`) VALUES
(1, '2026-01-26', '10:00:00', 1, '2026-01-25 07:07:50'),
(2, '2026-01-26', '11:00:00', 1, '2026-01-25 07:07:50'),
(3, '2026-01-26', '12:00:00', 1, '2026-01-25 07:07:50'),
(4, '2026-01-26', '13:00:00', 1, '2026-01-25 07:07:50'),
(5, '2026-01-26', '15:00:00', 1, '2026-01-25 07:07:50'),
(6, '2026-01-26', '16:00:00', 1, '2026-01-25 07:07:50'),
(7, '2026-01-26', '17:00:00', 1, '2026-01-25 07:07:50'),
(8, '2026-01-26', '18:00:00', 1, '2026-01-25 07:07:50'),
(9, '2026-01-26', '19:00:00', 1, '2026-01-25 07:07:50'),
(10, '2026-01-27', '10:00:00', 0, '2026-01-25 07:07:50'),
(11, '2026-01-27', '11:00:00', 0, '2026-01-25 07:07:50'),
(12, '2026-01-27', '12:00:00', 0, '2026-01-25 07:07:50'),
(13, '2026-01-27', '13:00:00', 0, '2026-01-25 07:07:50'),
(14, '2026-01-27', '15:00:00', 0, '2026-01-25 07:07:50'),
(15, '2026-01-27', '16:00:00', 0, '2026-01-25 07:07:50'),
(16, '2026-01-27', '17:00:00', 0, '2026-01-25 07:07:50'),
(17, '2026-01-27', '18:00:00', 0, '2026-01-25 07:07:50'),
(18, '2026-01-27', '19:00:00', 0, '2026-01-25 07:07:50'),
(19, '2026-01-28', '10:00:00', 1, '2026-01-25 07:07:50'),
(20, '2026-01-28', '11:00:00', 1, '2026-01-25 07:07:50'),
(21, '2026-01-28', '12:00:00', 1, '2026-01-25 07:07:50'),
(22, '2026-01-28', '13:00:00', 1, '2026-01-25 07:07:50'),
(23, '2026-01-28', '15:00:00', 1, '2026-01-25 07:07:50'),
(24, '2026-01-28', '16:00:00', 1, '2026-01-25 07:07:50'),
(25, '2026-01-28', '17:00:00', 1, '2026-01-25 07:07:50'),
(26, '2026-01-28', '18:00:00', 1, '2026-01-25 07:07:50'),
(27, '2026-01-28', '19:00:00', 1, '2026-01-25 07:07:50'),
(28, '2026-01-29', '10:00:00', 1, '2026-01-25 07:07:50'),
(29, '2026-01-29', '11:00:00', 1, '2026-01-25 07:07:50'),
(30, '2026-01-29', '12:00:00', 1, '2026-01-25 07:07:50'),
(31, '2026-01-29', '13:00:00', 1, '2026-01-25 07:07:50'),
(32, '2026-01-29', '15:00:00', 1, '2026-01-25 07:07:50'),
(33, '2026-01-29', '16:00:00', 1, '2026-01-25 07:07:50'),
(34, '2026-01-29', '17:00:00', 1, '2026-01-25 07:07:50'),
(35, '2026-01-29', '18:00:00', 1, '2026-01-25 07:07:50'),
(36, '2026-01-29', '19:00:00', 1, '2026-01-25 07:07:50'),
(37, '2026-01-30', '10:00:00', 1, '2026-01-25 07:07:50'),
(38, '2026-01-30', '11:00:00', 1, '2026-01-25 07:07:50'),
(39, '2026-01-30', '12:00:00', 1, '2026-01-25 07:07:50'),
(40, '2026-01-30', '13:00:00', 1, '2026-01-25 07:07:50'),
(41, '2026-01-30', '15:00:00', 1, '2026-01-25 07:07:50'),
(42, '2026-01-30', '16:00:00', 1, '2026-01-25 07:07:50'),
(43, '2026-01-30', '17:00:00', 1, '2026-01-25 07:07:50'),
(44, '2026-01-30', '18:00:00', 1, '2026-01-25 07:07:50'),
(45, '2026-01-30', '19:00:00', 1, '2026-01-25 07:07:50'),
(46, '2026-01-31', '10:00:00', 1, '2026-01-25 07:07:50'),
(47, '2026-01-31', '11:00:00', 1, '2026-01-25 07:07:50'),
(48, '2026-01-31', '12:00:00', 1, '2026-01-25 07:07:50'),
(49, '2026-01-31', '13:00:00', 1, '2026-01-25 07:07:50'),
(50, '2026-01-31', '15:00:00', 1, '2026-01-25 07:07:50'),
(51, '2026-01-31', '16:00:00', 1, '2026-01-25 07:07:50'),
(52, '2026-01-31', '17:00:00', 1, '2026-01-25 07:07:50'),
(53, '2026-01-31', '18:00:00', 1, '2026-01-25 07:07:50'),
(54, '2026-01-31', '19:00:00', 1, '2026-01-25 07:07:50'),
(55, '2026-02-02', '10:00:00', 0, '2026-01-25 07:07:50'),
(56, '2026-02-02', '11:00:00', 0, '2026-01-25 07:07:50'),
(57, '2026-02-02', '12:00:00', 0, '2026-01-25 07:07:50'),
(58, '2026-02-02', '13:00:00', 0, '2026-01-25 07:07:50'),
(59, '2026-02-02', '15:00:00', 1, '2026-01-25 07:07:50'),
(60, '2026-02-02', '16:00:00', 0, '2026-01-25 07:07:50'),
(61, '2026-02-02', '17:00:00', 0, '2026-01-25 07:07:50'),
(62, '2026-02-02', '18:00:00', 0, '2026-01-25 07:07:50'),
(63, '2026-02-02', '19:00:00', 0, '2026-01-25 07:07:50'),
(64, '2026-02-03', '10:00:00', 1, '2026-01-25 07:07:50'),
(65, '2026-02-03', '11:00:00', 1, '2026-01-25 07:07:50'),
(66, '2026-02-03', '12:00:00', 1, '2026-01-25 07:07:50'),
(67, '2026-02-03', '13:00:00', 1, '2026-01-25 07:07:50'),
(68, '2026-02-03', '15:00:00', 1, '2026-01-25 07:07:50'),
(69, '2026-02-03', '16:00:00', 1, '2026-01-25 07:07:50'),
(70, '2026-02-03', '17:00:00', 1, '2026-01-25 07:07:50'),
(71, '2026-02-03', '18:00:00', 1, '2026-01-25 07:07:50'),
(72, '2026-02-03', '19:00:00', 1, '2026-01-25 07:07:50'),
(73, '2026-02-04', '10:00:00', 1, '2026-01-25 07:07:50'),
(74, '2026-02-04', '11:00:00', 1, '2026-01-25 07:07:50'),
(75, '2026-02-04', '12:00:00', 1, '2026-01-25 07:07:50'),
(76, '2026-02-04', '13:00:00', 1, '2026-01-25 07:07:50'),
(77, '2026-02-04', '15:00:00', 1, '2026-01-25 07:07:50'),
(78, '2026-02-04', '16:00:00', 1, '2026-01-25 07:07:50'),
(79, '2026-02-04', '17:00:00', 1, '2026-01-25 07:07:50'),
(80, '2026-02-04', '18:00:00', 1, '2026-01-25 07:07:50'),
(81, '2026-02-04', '19:00:00', 1, '2026-01-25 07:07:50'),
(82, '2026-02-05', '10:00:00', 1, '2026-01-25 07:07:50'),
(83, '2026-02-05', '11:00:00', 1, '2026-01-25 07:07:50'),
(84, '2026-02-05', '12:00:00', 1, '2026-01-25 07:07:50'),
(85, '2026-02-05', '13:00:00', 1, '2026-01-25 07:07:50'),
(86, '2026-02-05', '15:00:00', 1, '2026-01-25 07:07:50'),
(87, '2026-02-05', '16:00:00', 1, '2026-01-25 07:07:50'),
(88, '2026-02-05', '17:00:00', 1, '2026-01-25 07:07:50'),
(89, '2026-02-05', '18:00:00', 1, '2026-01-25 07:07:50'),
(90, '2026-02-05', '19:00:00', 1, '2026-01-25 07:07:50'),
(91, '2026-02-06', '10:00:00', 1, '2026-01-25 07:07:50'),
(92, '2026-02-06', '11:00:00', 1, '2026-01-25 07:07:50'),
(93, '2026-02-06', '12:00:00', 1, '2026-01-25 07:07:50'),
(94, '2026-02-06', '13:00:00', 1, '2026-01-25 07:07:50'),
(95, '2026-02-06', '15:00:00', 1, '2026-01-25 07:07:50'),
(96, '2026-02-06', '16:00:00', 1, '2026-01-25 07:07:50'),
(97, '2026-02-06', '17:00:00', 1, '2026-01-25 07:07:50'),
(98, '2026-02-06', '18:00:00', 1, '2026-01-25 07:07:50'),
(99, '2026-02-06', '19:00:00', 1, '2026-01-25 07:07:50'),
(100, '2026-02-07', '10:00:00', 1, '2026-01-25 07:07:50'),
(101, '2026-02-07', '11:00:00', 1, '2026-01-25 07:07:50'),
(102, '2026-02-07', '12:00:00', 1, '2026-01-25 07:07:50'),
(103, '2026-02-07', '13:00:00', 1, '2026-01-25 07:07:50'),
(104, '2026-02-07', '15:00:00', 1, '2026-01-25 07:07:50'),
(105, '2026-02-07', '16:00:00', 1, '2026-01-25 07:07:50'),
(106, '2026-02-07', '17:00:00', 1, '2026-01-25 07:07:50'),
(107, '2026-02-07', '18:00:00', 1, '2026-01-25 07:07:50'),
(108, '2026-02-07', '19:00:00', 1, '2026-01-25 07:07:50'),
(109, '2026-02-09', '10:00:00', 1, '2026-01-25 07:07:50'),
(110, '2026-02-09', '11:00:00', 1, '2026-01-25 07:07:50'),
(111, '2026-02-09', '12:00:00', 1, '2026-01-25 07:07:50'),
(112, '2026-02-09', '13:00:00', 1, '2026-01-25 07:07:50'),
(113, '2026-02-09', '15:00:00', 1, '2026-01-25 07:07:50'),
(114, '2026-02-09', '16:00:00', 1, '2026-01-25 07:07:50'),
(115, '2026-02-09', '17:00:00', 1, '2026-01-25 07:07:50'),
(116, '2026-02-09', '18:00:00', 1, '2026-01-25 07:07:50'),
(117, '2026-02-09', '19:00:00', 1, '2026-01-25 07:07:50'),
(118, '2026-02-10', '10:00:00', 1, '2026-01-25 07:07:50'),
(119, '2026-02-10', '11:00:00', 1, '2026-01-25 07:07:50'),
(120, '2026-02-10', '12:00:00', 1, '2026-01-25 07:07:50'),
(121, '2026-02-10', '13:00:00', 1, '2026-01-25 07:07:50'),
(122, '2026-02-10', '15:00:00', 1, '2026-01-25 07:07:50'),
(123, '2026-02-10', '16:00:00', 1, '2026-01-25 07:07:50'),
(124, '2026-02-10', '17:00:00', 1, '2026-01-25 07:07:50'),
(125, '2026-02-10', '18:00:00', 1, '2026-01-25 07:07:50'),
(126, '2026-02-10', '19:00:00', 1, '2026-01-25 07:07:50'),
(127, '2026-02-11', '10:00:00', 1, '2026-01-25 07:07:50'),
(128, '2026-02-11', '11:00:00', 1, '2026-01-25 07:07:50'),
(129, '2026-02-11', '12:00:00', 1, '2026-01-25 07:07:50'),
(130, '2026-02-11', '13:00:00', 1, '2026-01-25 07:07:50'),
(131, '2026-02-11', '15:00:00', 1, '2026-01-25 07:07:50'),
(132, '2026-02-11', '16:00:00', 1, '2026-01-25 07:07:50'),
(133, '2026-02-11', '17:00:00', 1, '2026-01-25 07:07:50'),
(134, '2026-02-11', '18:00:00', 1, '2026-01-25 07:07:50'),
(135, '2026-02-11', '19:00:00', 1, '2026-01-25 07:07:50'),
(136, '2026-02-12', '10:00:00', 1, '2026-01-25 07:07:50'),
(137, '2026-02-12', '11:00:00', 1, '2026-01-25 07:07:50'),
(138, '2026-02-12', '12:00:00', 1, '2026-01-25 07:07:50'),
(139, '2026-02-12', '13:00:00', 1, '2026-01-25 07:07:50'),
(140, '2026-02-12', '15:00:00', 1, '2026-01-25 07:07:50'),
(141, '2026-02-12', '16:00:00', 1, '2026-01-25 07:07:50'),
(142, '2026-02-12', '17:00:00', 1, '2026-01-25 07:07:50'),
(143, '2026-02-12', '18:00:00', 1, '2026-01-25 07:07:50'),
(144, '2026-02-12', '19:00:00', 1, '2026-01-25 07:07:50'),
(145, '2026-02-13', '10:00:00', 1, '2026-01-25 07:07:50'),
(146, '2026-02-13', '11:00:00', 1, '2026-01-25 07:07:50'),
(147, '2026-02-13', '12:00:00', 1, '2026-01-25 07:07:50'),
(148, '2026-02-13', '13:00:00', 1, '2026-01-25 07:07:50'),
(149, '2026-02-13', '15:00:00', 1, '2026-01-25 07:07:50'),
(150, '2026-02-13', '16:00:00', 1, '2026-01-25 07:07:50'),
(151, '2026-02-13', '17:00:00', 1, '2026-01-25 07:07:50'),
(152, '2026-02-13', '18:00:00', 1, '2026-01-25 07:07:50'),
(153, '2026-02-13', '19:00:00', 1, '2026-01-25 07:07:50'),
(154, '2026-02-14', '10:00:00', 1, '2026-01-25 07:07:50'),
(155, '2026-02-14', '11:00:00', 1, '2026-01-25 07:07:50'),
(156, '2026-02-14', '12:00:00', 1, '2026-01-25 07:07:50'),
(157, '2026-02-14', '13:00:00', 1, '2026-01-25 07:07:50'),
(158, '2026-02-14', '15:00:00', 1, '2026-01-25 07:07:50'),
(159, '2026-02-14', '16:00:00', 1, '2026-01-25 07:07:50'),
(160, '2026-02-14', '17:00:00', 1, '2026-01-25 07:07:50'),
(161, '2026-02-14', '18:00:00', 1, '2026-01-25 07:07:50'),
(162, '2026-02-14', '19:00:00', 1, '2026-01-25 07:07:50'),
(163, '2026-02-16', '10:00:00', 1, '2026-01-25 07:07:50'),
(164, '2026-02-16', '11:00:00', 1, '2026-01-25 07:07:50'),
(165, '2026-02-16', '12:00:00', 1, '2026-01-25 07:07:50'),
(166, '2026-02-16', '13:00:00', 1, '2026-01-25 07:07:50'),
(167, '2026-02-16', '15:00:00', 1, '2026-01-25 07:07:50'),
(168, '2026-02-16', '16:00:00', 1, '2026-01-25 07:07:50'),
(169, '2026-02-16', '17:00:00', 1, '2026-01-25 07:07:50'),
(170, '2026-02-16', '18:00:00', 1, '2026-01-25 07:07:50'),
(171, '2026-02-16', '19:00:00', 1, '2026-01-25 07:07:50'),
(172, '2026-02-17', '10:00:00', 1, '2026-01-25 07:07:50'),
(173, '2026-02-17', '11:00:00', 1, '2026-01-25 07:07:50'),
(174, '2026-02-17', '12:00:00', 1, '2026-01-25 07:07:50'),
(175, '2026-02-17', '13:00:00', 1, '2026-01-25 07:07:50'),
(176, '2026-02-17', '15:00:00', 1, '2026-01-25 07:07:50'),
(177, '2026-02-17', '16:00:00', 1, '2026-01-25 07:07:50'),
(178, '2026-02-17', '17:00:00', 1, '2026-01-25 07:07:50'),
(179, '2026-02-17', '18:00:00', 1, '2026-01-25 07:07:50'),
(180, '2026-02-17', '19:00:00', 1, '2026-01-25 07:07:50'),
(181, '2026-02-18', '10:00:00', 1, '2026-01-25 07:07:50'),
(182, '2026-02-18', '11:00:00', 1, '2026-01-25 07:07:50'),
(183, '2026-02-18', '12:00:00', 1, '2026-01-25 07:07:50'),
(184, '2026-02-18', '13:00:00', 1, '2026-01-25 07:07:50'),
(185, '2026-02-18', '15:00:00', 1, '2026-01-25 07:07:50'),
(186, '2026-02-18', '16:00:00', 1, '2026-01-25 07:07:50'),
(187, '2026-02-18', '17:00:00', 1, '2026-01-25 07:07:50'),
(188, '2026-02-18', '18:00:00', 1, '2026-01-25 07:07:50'),
(189, '2026-02-18', '19:00:00', 1, '2026-01-25 07:07:50'),
(190, '2026-02-19', '10:00:00', 1, '2026-01-25 07:07:50'),
(191, '2026-02-19', '11:00:00', 1, '2026-01-25 07:07:50'),
(192, '2026-02-19', '12:00:00', 1, '2026-01-25 07:07:50'),
(193, '2026-02-19', '13:00:00', 1, '2026-01-25 07:07:50'),
(194, '2026-02-19', '15:00:00', 1, '2026-01-25 07:07:50'),
(195, '2026-02-19', '16:00:00', 1, '2026-01-25 07:07:50'),
(196, '2026-02-19', '17:00:00', 1, '2026-01-25 07:07:50'),
(197, '2026-02-19', '18:00:00', 1, '2026-01-25 07:07:50'),
(198, '2026-02-19', '19:00:00', 1, '2026-01-25 07:07:50'),
(199, '2026-02-20', '10:00:00', 1, '2026-01-25 07:07:50'),
(200, '2026-02-20', '11:00:00', 1, '2026-01-25 07:07:50'),
(201, '2026-02-20', '12:00:00', 1, '2026-01-25 07:07:50'),
(202, '2026-02-20', '13:00:00', 1, '2026-01-25 07:07:50'),
(203, '2026-02-20', '15:00:00', 1, '2026-01-25 07:07:50'),
(204, '2026-02-20', '16:00:00', 1, '2026-01-25 07:07:50'),
(205, '2026-02-20', '17:00:00', 1, '2026-01-25 07:07:50'),
(206, '2026-02-20', '18:00:00', 1, '2026-01-25 07:07:50'),
(207, '2026-02-20', '19:00:00', 1, '2026-01-25 07:07:50');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `instagram_post`
--

CREATE TABLE `instagram_post` (
  `id` int NOT NULL,
  `embed_url` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `orden` int UNSIGNED DEFAULT '0',
  `actualizado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `instagram_post`
--

INSERT INTO `instagram_post` (`id`, `embed_url`, `activo`, `orden`, `actualizado_en`) VALUES
(11, 'https://www.instagram.com/p/CywADP7prdE/embed', 1, 0, '2025-11-08 04:02:54'),
(15, 'https://www.instagram.com/p/DMTqU66R7BC/embed', 1, 0, '2025-11-13 05:54:17'),
(16, 'https://www.instagram.com/p/DIRfPH6R-bd/embed', 1, 0, '2025-11-13 04:09:29'),
(17, 'https://www.instagram.com/p/CywADP7prdE/embed', 1, 0, '2025-11-13 04:10:32');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `post_care_tip`
--

CREATE TABLE `post_care_tip` (
  `id` int NOT NULL,
  `icono` varchar(8) DEFAULT NULL,
  `titulo` varchar(80) DEFAULT NULL,
  `texto` varchar(240) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `orden` int UNSIGNED DEFAULT '0',
  `actualizado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `post_care_tip`
--

INSERT INTO `post_care_tip` (`id`, `icono`, `titulo`, `texto`, `activo`, `orden`, `actualizado_en`) VALUES
(1, '💧', 'Evita la humedad', 'No mojes la zona tratada durante las primeras 12 horas.', 1, 0, '2025-11-13 04:00:51'),
(2, '☀', 'Evita el sol', 'Protégete del sol al menos por 48 horas.', 1, 1, '2025-11-13 04:00:55'),
(3, '🧴', 'Usa bloqueador', 'Aplica SPF cada 2 horas si estás al aire libre.', 1, 2, '2025-11-13 04:00:58'),
(4, '🚫', 'Nada de maquillaje', 'Evita productos cosméticos por al menos 24 horas.', 1, 3, '2025-11-13 04:01:09'),
(5, '🙅‍♀️', 'Sin masajes', 'No frotes ni manipules la zona tratada.', 1, 4, '2025-11-13 04:01:12');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `refresh_tokens`
--

CREATE TABLE `refresh_tokens` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `token` varchar(120) NOT NULL,
  `expires_at` datetime NOT NULL,
  `creado_en` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `refresh_tokens`
--

INSERT INTO `refresh_tokens` (`id`, `user_id`, `token`, `expires_at`, `creado_en`) VALUES
(1, 2, 'b81e3aa1595c3ea045b722419b5badf46eea8db5cd6ecddbeb33af82170beebbeb7f5aa6c69eabec', '2025-11-22 04:26:38', '2025-11-15 04:26:38'),
(2, 2, 'bfed43919c3f139afa37f4845e2c4936ea6b17500577b843c14d640f4cec2dc26f337151cbc2a8e9', '2025-11-22 04:34:13', '2025-11-15 04:34:13'),
(3, 2, '8c19494b6ffad7962fd686b3ba59d2be2eb6ce42b845bde336e472ad0bf3c3dc2c41621dbc06f861', '2025-11-22 04:38:58', '2025-11-15 04:38:58'),
(4, 2, 'aaaea3e22cdebc009c02b75dbd9aa26539441d04834f0b703f77098fab62de367c8c88db924bc4f8', '2025-11-22 04:43:19', '2025-11-15 04:43:19'),
(5, 2, 'ed3b0a6134be1ba1a8ecf6a37aad651daae5c7cac3383c6faf70081f9130e6c25bd9db1365b7bbff', '2025-11-22 18:09:50', '2025-11-15 18:09:50'),
(6, 2, '1af6b845899b3db0598b0d4d33c4b7e3eff21859ea2f12f2ed2e928af774da7a4611b2b4f805197c', '2025-11-22 18:12:39', '2025-11-15 18:12:39'),
(7, 2, 'ea8ef80de500a9fb85ca75f714df0524169ce8f44cb3f071cb813533e1bf240fbfe9f6f1b2df962e', '2025-11-22 18:52:59', '2025-11-15 18:52:59'),
(8, 2, '0628e38acf25915530b80c93c75d326c2181ade0b2b53cf2f4af0d6f6cad82c3b6a67a2e5a4d4c85', '2025-11-22 20:57:13', '2025-11-15 20:57:13'),
(9, 2, 'c3aa5e4680cacc4da7ba9c1ae775da1a8d5f74603765667bb5b4841a21603610bd839b298e8d320c', '2025-11-22 21:00:03', '2025-11-15 21:00:03'),
(11, 2, '755965e7119a4d5aad0aa9b35a98f0680eb0130fd33b93850665751bf54c4487eeb1812b6cf51166', '2025-11-22 21:03:15', '2025-11-15 21:03:15'),
(12, 2, '9131784b9646d5cb18a389e1d93009b0ab504440dacef9a8be4be58df7346b2e63d1c6e2789bffdb', '2025-11-22 21:03:40', '2025-11-15 21:03:40'),
(13, 2, '0a58a1a16404e19892d8803a2fde8a9c51b8593a0216a15a9532bf8c679e7fade095cbbdbd1376fc', '2025-11-22 21:18:27', '2025-11-15 21:18:27'),
(14, 2, 'a834d0f51af82d4c288f6c4807d4643ef9d5613a85557172b4aa94447a0033f489f5e4b4b10bb028', '2025-11-22 21:18:54', '2025-11-15 21:18:54'),
(15, 2, '323a7fda315ce7f67d9e08a15e8a1dc54a36b13f37cd682b7cb3daaeb688e02c775647090da682cd', '2025-11-22 21:25:03', '2025-11-15 21:25:03'),
(16, 2, 'b002593ad39339b1e49f00250b0c2a5cf012521ab7459699faea7d0e199e15439a9cd07ed8a8f617', '2025-11-22 21:46:44', '2025-11-15 21:46:44'),
(17, 2, '5cdf6814da9d13a799a6fa2cf951e9178add9d6f868b61aceaf19f2c8d51b3b054483f4c0ef48864', '2025-11-22 21:46:58', '2025-11-15 21:46:58'),
(18, 2, '556bf366ff870a4b6cc4fd67917fb5c7409c5e13fa6eb58678f7653a9b248861504c1cbb1a5cba75', '2025-11-22 22:02:15', '2025-11-15 22:02:15'),
(19, 2, '4dfd9b1cb718d9bf8d3c78bdccba8dee5208650ab3776a5038aae685a717bd730c66f78cae792579', '2025-11-22 22:24:53', '2025-11-15 22:24:53'),
(20, 2, 'cbcdfd3e83798ba800051a4923ff8c23b61674f00d0880c1a51d7e6bad79c9a869a7645d099e35b6', '2025-11-22 22:34:04', '2025-11-15 22:34:04'),
(21, 2, '6224f7f2337e9295a1aaf9ca1a44112077bb3ff70d50aa6949dd105acdf14994abb985a439fd3921', '2025-11-22 22:41:31', '2025-11-15 22:41:31'),
(22, 2, 'a911197e6f9f69fb5bdfa088b94d509389f061af46d0224d3ff875a5a362684b5b0c0de17128f897', '2025-11-22 22:49:38', '2025-11-15 22:49:38'),
(23, 2, '76b69990de321e36c667cdb9d62f85a8d4e77cd3fa2074f98686de371f210909f38eed383857ccbe', '2025-11-22 23:01:31', '2025-11-15 23:01:31'),
(24, 2, '89d60013d5a655403242f534b998dca35e5eab09b2ce03e5eb9c8cdb348a25af23a2e09c481181a6', '2025-11-22 23:07:27', '2025-11-15 23:07:27'),
(25, 2, '096669d72943d814e820c014face26e88247e8633b45596f86c6e1cc29b7a9addb64609b52e5a031', '2025-11-22 23:23:20', '2025-11-15 23:23:20'),
(26, 2, 'd215f6fbabf878235e6724deab05ce9b590599d9c2527f4b63aee19eb71c4f801fed74a12d977bb4', '2026-01-23 05:43:43', '2026-01-16 05:43:43'),
(28, 2, 'd09208aee58f87079561d096a941d745c8a7d5b7e8191f98458a813413bec9f5f82f559cd64d8902', '2026-01-25 03:14:56', '2026-01-18 03:14:56');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `servicios`
--

CREATE TABLE `servicios` (
  `id` int NOT NULL,
  `categoria_id` int DEFAULT NULL,
  `nombre` varchar(150) NOT NULL,
  `descripcion` text,
  `imagen_url` varchar(255) DEFAULT NULL,
  `precio` decimal(10,2) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `orden` int DEFAULT '0',
  `etiqueta` varchar(60) DEFAULT NULL,
  `subtitulo` varchar(150) DEFAULT NULL,
  `beneficios` text,
  `cta_primary_label` varchar(120) DEFAULT NULL,
  `cta_primary_url` varchar(255) DEFAULT NULL,
  `cta_secondary_label` varchar(120) DEFAULT NULL,
  `cta_secondary_url` varchar(255) DEFAULT NULL,
  `mostrar_servicios` tinyint(1) DEFAULT '0',
  `mostrar_empresas` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `servicios`
--

INSERT INTO `servicios` (`id`, `categoria_id`, `nombre`, `descripcion`, `imagen_url`, `precio`, `activo`, `orden`, `etiqueta`, `subtitulo`, `beneficios`, `cta_primary_label`, `cta_primary_url`, `cta_secondary_label`, `cta_secondary_url`, `mostrar_servicios`, `mostrar_empresas`) VALUES
(40, 17, 'Masaje Drenaje Linfático', 'Técnica suave orientada a estimular el sistema linfático para apoyar la eliminación de líquidos y la sensación de liviandad corporal.', NULL, NULL, 1, 1, 'Masaje', 'Drenaje Linfático', '[\"Mejora la circulación linfática\", \"Apoya la reducción de inflamación\", \"Favorece la sensación de descanso\", \"Mejora el aspecto de la piel\"]', 'Agendar', '/contacto', 'Ver disponibilidad', '/contacto', 1, 0),
(41, 17, 'Masaje Descontracturante', 'Ideal para aliviar tensión muscular (espalda, cuello, hombros). Enfocado en liberar zonas cargadas y mejorar la movilidad.', NULL, NULL, 1, 2, 'Masaje', 'Descontracturante', '[\"Alivia dolor y tensión muscular\", \"Mejora postura y flexibilidad\", \"Reduce estrés acumulado\", \"Aumenta la sensación de energía\"]', 'Reservar ahora', '/contacto', 'Hablar por WhatsApp', '/contacto', 1, 0),
(42, 17, 'Masaje Deportivo', 'Pensado para personas activas y deportistas. Ayuda a preparar el músculo, prevenir molestias y apoyar la recuperación.', NULL, NULL, 1, 3, 'Masaje', 'Deportivo', '[\"Apoya la recuperación post-entreno\", \"Ayuda a prevenir lesiones\", \"Reduce dolor muscular y fatiga\", \"Mejora circulación y rendimiento\"]', 'Agendar sesión', '/contacto', 'Cotizar para equipos', '/empresas', 1, 0),
(43, 17, 'Masaje Relajante', 'Una experiencia tranquila para desconectar, bajar el estrés y mejorar el bienestar general. Ideal para recargar energía.', NULL, NULL, 1, 4, 'Masaje', 'Relajante', '[\"Reduce estrés y ansiedad\", \"Mejora el descanso y ánimo\", \"Favorece la relajación profunda\", \"Apoya el bienestar general\"]', 'Agendar', '/contacto', 'Ver servicios', '/servicios', 1, 0),
(44, 18, 'Giftcard Día del Profesor', 'Regala una experiencia de bienestar. Giftcard equivalente a un masaje, en la comodidad del hogar (ideal para sorprender).', NULL, NULL, 1, 1, 'Giftcard', 'Día del Profesor', '[\"Regalo útil y memorable\", \"Perfecto para fechas especiales\", \"Coordinación simple por contacto\", \"Experiencia premium y cálida\"]', 'Comprar / Consultar', '/contacto', 'Empresas (beneficios)', '/empresas', 1, 0),
(45, 18, 'Promo Día de Relajo', 'Pack promocional con opciones de masaje y extras (según disponibilidad). Perfecto para regalar o regalonearte.', NULL, NULL, 1, 2, 'Promo', 'Día de Relajo', '[\"Incluye aromaterapia / musicoterapia (según pack)\", \"Duración aproximada 60 min\", \"Opciones: relajante, descontracturante o mixto\", \"Ideal para fechas como Día de la Madre\"]', 'Quiero esta promo', '/contacto', 'Agendar', '/contacto', 1, 0),
(46, 19, 'Masaje Descontracturante (Oficina)', 'Ideal para aliviar tensión muscular (espalda, cuello, hombros). Perfecto para rutinas de escritorio y estrés acumulado.', NULL, NULL, 1, 1, 'Masaje', 'Descontracturante (Oficina)', '[\"Alivia dolor y tensión muscular\", \"Mejora postura y movilidad\", \"Reduce estrés acumulado\", \"Excelente para pausas saludables\"]', 'Cotizar para empresas', '/empresas', 'Hablar por WhatsApp', '/contacto', 0, 1),
(47, 19, 'Masaje Relajante (Pausa saludable)', 'Experiencia tranquila para desconectar y recargar energía. Ideal para jornadas intensas o semanas de alta carga.', NULL, NULL, 1, 2, 'Masaje', 'Relajante (Pausa saludable)', '[\"Reduce estrés y ansiedad\", \"Mejora bienestar general\", \"Aporta calma y enfoque\", \"Perfecto para actividades internas\"]', 'Solicitar propuesta', '/empresas', 'Consultar disponibilidad', '/contacto', 0, 1),
(48, 20, 'Masaje Deportivo (Equipos / Actividad)', 'Pensado para personas activas. En empresas funciona excelente para jornadas de movimiento, equipos deportivos internos o eventos.', NULL, NULL, 1, 1, 'Masaje', 'Deportivo (Equipos / Actividad)', '[\"Apoya recuperación post-actividad\", \"Reduce fatiga muscular\", \"Mejora circulación y rendimiento\", \"Ideal para equipos y eventos\"]', 'Cotizar evento', '/empresas', 'Hablar por WhatsApp', '/contacto', 0, 1),
(49, 21, 'Giftcard Para colaboradores', 'Regala una experiencia de bienestar. Coordinación simple por contacto, ideal para reconocer y motivar.', NULL, NULL, 1, 1, 'Giftcard', 'Para colaboradores', '[\"Regalo útil y memorable\", \"Perfecto para fechas especiales\", \"Coordinación simple\", \"Experiencia premium y cálida\"]', 'Quiero giftcards', '/empresas', 'Consultar', '/contacto', 0, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `site_config`
--

CREATE TABLE `site_config` (
  `id` int NOT NULL,
  `whatsapp` varchar(20) DEFAULT NULL,
  `whatsapp_message` varchar(200) DEFAULT NULL,
  `instagram_profile_url` varchar(255) DEFAULT NULL,
  `actualizado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `site_config`
--

INSERT INTO `site_config` (`id`, `whatsapp`, `whatsapp_message`, `instagram_profile_url`, `actualizado_en`) VALUES
(1, '+56949628081', 'Hola, me gustaría agendar una cita', 'https://www.instagram.com/cquezadaskin/', '2025-11-16 01:47:07');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `testimonio`
--

CREATE TABLE `testimonio` (
  `id` int NOT NULL,
  `nombre` varchar(120) DEFAULT NULL,
  `texto` text,
  `foto_url` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `orden` int UNSIGNED DEFAULT '0',
  `actualizado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `testimonio`
--

INSERT INTO `testimonio` (`id`, `nombre`, `texto`, `foto_url`, `activo`, `orden`, `actualizado_en`) VALUES
(2, 'Fernanda Rivas', 'Profesional y dedicada. 100% recomendada.', 'https://randomuser.me/api/portraits/women/43.jpg', 1, 2, '2025-11-16 01:43:32'),
(3, 'Camila Pérez', 'Muy amable y puntual, un serviciodasdasdad impecable.', 'https://randomuser.me/api/portraits/women/90.jpg', 1, 3, '2025-11-13 05:42:02'),
(12, 'Camila Soto', '\"La mejor experiencia estética que he tenido. Volveré sin duda.\"', 'https://back.ocaresdev.cl/uploads/testimonios/0a1695e877ddad2eed675405e8423b33.jpg', 1, 1, '2025-11-16 01:45:08');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios_admin`
--

CREATE TABLE `usuarios_admin` (
  `id` int NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `rol` enum('admin','superadmin') DEFAULT 'admin',
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios_admin`
--

INSERT INTO `usuarios_admin` (`id`, `username`, `password_hash`, `rol`, `creado_en`) VALUES
(2, 'Pedro', '$2y$10$hJWyrPoDzVpoXzok5mj6lOMChthPuyVv9xkMxuvsqIezIwtRLcREa', 'superadmin', '2025-11-06 23:22:24');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `about_content`
--
ALTER TABLE `about_content`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `categorias_servicio`
--
ALTER TABLE `categorias_servicio`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `citas`
--
ALTER TABLE `citas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `servicio_id` (`servicio_id`);

--
-- Indices de la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `contacto`
--
ALTER TABLE `contacto`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `faq`
--
ALTER TABLE `faq`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `home_content`
--
ALTER TABLE `home_content`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `horarios_disponibles`
--
ALTER TABLE `horarios_disponibles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_horario_fecha_hora` (`fecha`,`hora`);

--
-- Indices de la tabla `instagram_post`
--
ALTER TABLE `instagram_post`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `post_care_tip`
--
ALTER TABLE `post_care_tip`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `refresh_tokens`
--
ALTER TABLE `refresh_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `user_id` (`user_id`);

--
-- Indices de la tabla `servicios`
--
ALTER TABLE `servicios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `categoria_id` (`categoria_id`);

--
-- Indices de la tabla `site_config`
--
ALTER TABLE `site_config`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `testimonio`
--
ALTER TABLE `testimonio`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `usuarios_admin`
--
ALTER TABLE `usuarios_admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `about_content`
--
ALTER TABLE `about_content`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `categorias_servicio`
--
ALTER TABLE `categorias_servicio`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT de la tabla `citas`
--
ALTER TABLE `citas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT de la tabla `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT de la tabla `contacto`
--
ALTER TABLE `contacto`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `faq`
--
ALTER TABLE `faq`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de la tabla `home_content`
--
ALTER TABLE `home_content`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `horarios_disponibles`
--
ALTER TABLE `horarios_disponibles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=267;

--
-- AUTO_INCREMENT de la tabla `instagram_post`
--
ALTER TABLE `instagram_post`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de la tabla `post_care_tip`
--
ALTER TABLE `post_care_tip`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT de la tabla `refresh_tokens`
--
ALTER TABLE `refresh_tokens`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT de la tabla `servicios`
--
ALTER TABLE `servicios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT de la tabla `site_config`
--
ALTER TABLE `site_config`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `testimonio`
--
ALTER TABLE `testimonio`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `usuarios_admin`
--
ALTER TABLE `usuarios_admin`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `citas`
--
ALTER TABLE `citas`
  ADD CONSTRAINT `citas_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `citas_ibfk_2` FOREIGN KEY (`servicio_id`) REFERENCES `servicios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `refresh_tokens`
--
ALTER TABLE `refresh_tokens`
  ADD CONSTRAINT `refresh_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `usuarios_admin` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `servicios`
--
ALTER TABLE `servicios`
  ADD CONSTRAINT `servicios_ibfk_1` FOREIGN KEY (`categoria_id`) REFERENCES `categorias_servicio` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
