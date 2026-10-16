-- ============================================================
-- BeatCell - Base de datos FUSIONADA
-- Fusión de:
--   (A) dump 19:31 -> video_url en modulos, campos de recuperación
--       de contraseña en usuarios, tipo 'opcion_multiple'
--   (B) dump 17:52 -> columna audio y tipo 'audio' en preguntas,
--       modo_tiempo como ENUM, y todos los datos reales del curso
-- ============================================================

CREATE DATABASE IF NOT EXISTS `beatcell` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `beatcell`;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET FOREIGN_KEY_CHECKS = 0;

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------
-- ESTRUCTURA DE TABLAS
-- --------------------------------------------------------

CREATE TABLE `actividades` (
  `id_actividad` int(11) NOT NULL,
  `id_tema` int(11) NOT NULL,
  `titulo` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `tipo` varchar(50) NOT NULL DEFAULT 'Cuestionario',
  `numero_preguntas` int(11) NOT NULL DEFAULT 0,
  `modo_tiempo` enum('sin_limite','total','por_pregunta') NOT NULL DEFAULT 'sin_limite',
  `tiempo_limite` int(11) DEFAULT NULL,
  `puntaje_minimo` decimal(5,2) DEFAULT NULL,
  `fecha_limite` datetime DEFAULT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `certificados` (
  `id_certificado` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_curso` int(11) NOT NULL,
  `codigo_certificado` varchar(100) NOT NULL,
  `archivo` varchar(255) NOT NULL,
  `fecha_emision` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cursos` (
  `id_curso` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `imagen` varchar(255) DEFAULT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `logros` (
  `id_logro` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `icono` varchar(255) DEFAULT NULL,
  `tipo` varchar(50) DEFAULT NULL,
  `condicion` varchar(255) DEFAULT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `mensajes_chat` (
  `id_mensaje` int(11) NOT NULL,
  `id_sesion` int(11) NOT NULL,
  `remitente` enum('Usuario','Bot') NOT NULL,
  `mensaje` text NOT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `modulos` (
  `id_modulo` int(11) NOT NULL,
  `id_curso` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `video_url` varchar(2048) DEFAULT NULL,
  `orden` int(11) NOT NULL DEFAULT 1,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `opciones_respuesta` (
  `id_opcion` int(11) NOT NULL,
  `id_pregunta` int(11) NOT NULL,
  `texto` varchar(255) DEFAULT NULL,
  `imagen` varchar(255) DEFAULT NULL,
  `video_url` varchar(2048) DEFAULT NULL,
  `correcta` tinyint(1) NOT NULL DEFAULT 0,
  `retroalimentacion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `preguntas` (
  `id_pregunta` int(11) NOT NULL,
  `id_actividad` int(11) NOT NULL,
  `pregunta` text NOT NULL,
  `tipo` enum('opcion_multiple','verdadero_falso','completar','imagen','audio','video') NOT NULL,
  `puntos` int(11) NOT NULL DEFAULT 1,
  `imagen` varchar(255) DEFAULT NULL,
  `audio` varchar(255) DEFAULT NULL,
  `video_url` varchar(2048) DEFAULT NULL,
  `seleccion_multiple` tinyint(1) NOT NULL DEFAULT 0,
  `tiempo_limite` int(11) DEFAULT NULL,
  `explicacion` text DEFAULT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `progreso_curso` (
  `id_progreso` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_curso` int(11) NOT NULL,
  `porcentaje` decimal(5,2) NOT NULL DEFAULT 0.00,
  `fecha_inicio` datetime NOT NULL DEFAULT current_timestamp(),
  `ultima_actividad` datetime DEFAULT NULL,
  `estado` enum('No iniciado','En progreso','Completado') NOT NULL DEFAULT 'No iniciado'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `progreso_tema` (
  `id_progreso_tema` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_tema` int(11) NOT NULL,
  `intentos` int(11) NOT NULL DEFAULT 0,
  `promedio` decimal(5,2) NOT NULL DEFAULT 0.00,
  `porcentaje` decimal(5,2) NOT NULL DEFAULT 0.00,
  `estado` enum('No iniciado','En progreso','Completado','Reforzar') NOT NULL DEFAULT 'No iniciado',
  `ultima_actualizacion` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `respuestas_estudiante` (
  `id_respuesta` int(11) NOT NULL,
  `id_resultado` int(11) NOT NULL,
  `id_pregunta` int(11) NOT NULL,
  `id_opcion` int(11) DEFAULT NULL,
  `opciones_seleccionadas` text DEFAULT NULL,
  `respuesta_texto` text DEFAULT NULL,
  `correcta` tinyint(1) NOT NULL DEFAULT 0,
  `puntos_obtenidos` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `resultados` (
  `id_resultado` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_actividad` int(11) NOT NULL,
  `puntaje_obtenido` int(11) NOT NULL DEFAULT 0,
  `puntaje_total` int(11) NOT NULL DEFAULT 0,
  `total_preguntas` int(11) NOT NULL DEFAULT 0,
  `respuestas_correctas` int(11) NOT NULL DEFAULT 0,
  `porcentaje` decimal(5,2) NOT NULL DEFAULT 0.00,
  `estado` enum('Aprobado','Desaprobado') NOT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rutas_aprendizaje` (
  `id_ruta` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `nivel` enum('Básico','Intermedio','Avanzado') DEFAULT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ruta_cursos` (
  `id_ruta_curso` int(11) NOT NULL,
  `id_ruta` int(11) NOT NULL,
  `id_curso` int(11) NOT NULL,
  `orden` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sesiones_chat` (
  `id_sesion` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `fecha_inicio` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_fin` datetime DEFAULT NULL,
  `estado` enum('Activa','Finalizada') NOT NULL DEFAULT 'Activa'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `temas` (
  `id_tema` int(11) NOT NULL,
  `id_modulo` int(11) NOT NULL,
  `nombre` varchar(200) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `material_apoyo` text DEFAULT NULL,
  `orden` int(11) NOT NULL DEFAULT 1,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `dni` char(8) DEFAULT NULL,
  `nombres` varchar(100) NOT NULL,
  `apellidos` varchar(100) NOT NULL,
  `correo` varchar(200) NOT NULL,
  `password` varchar(255) NOT NULL,
  `password_reset_token` varchar(255) DEFAULT NULL,
  `password_reset_expira` datetime DEFAULT NULL,
  `rol` enum('Estudiante','Docente','Administrador') NOT NULL DEFAULT 'Estudiante',
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `usuario_logros` (
  `id_usuario_logro` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_logro` int(11) NOT NULL,
  `fecha_obtenido` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- DATOS
-- --------------------------------------------------------

INSERT INTO `cursos` (`id_curso`, `nombre`, `descripcion`, `imagen`, `estado`, `fecha_creacion`) VALUES
(1, 'Reparación de Celulares Básico', 'Fundamentos de telefonía móvil, hardware y software, uso seguro de la mesa y las herramientas de trabajo, y reconocimiento de dispositivos. Contenido basado en el libro de BeatCell Academia.', NULL, 'Activo', '2026-10-09 00:44:28');

INSERT INTO `modulos` (`id_modulo`, `id_curso`, `nombre`, `descripcion`, `video_url`, `orden`, `estado`) VALUES
(1, 1, 'Módulo 1: Fundamentos de la telefonía móvil', 'Qué es un celular por dentro, cómo ha evolucionado y cómo han avanzado las redes móviles.', NULL, 1, 'Activo'),
(2, 1, 'Módulo 2: Taller y diagnóstico', 'Cómo preparar la mesa de trabajo, usar las herramientas con seguridad e identificar el equipo que se va a reparar.', NULL, 2, 'Activo');

INSERT INTO `temas` (`id_tema`, `id_modulo`, `nombre`, `descripcion`, `material_apoyo`, `orden`, `estado`) VALUES
(1, 1, '¿Qué es Hardware y Software?', 'Hardware: partes físicas del celular (pantalla, batería, placa base, cámaras, altavoz, micrófono, puerto de carga, botones, sensores y antenas). Software: partes del programa (sistema operativo Android o iOS, interfaz de usuario, aplicaciones, controladores, firmware, sistema de archivos y servicios).', NULL, 1, 'Activo'),
(2, 1, 'Evolución de los celulares', 'Recorrido desde el Nokia 3310 (2000) hasta el Samsung Galaxy S24 Ultra (2024): del celular para llamadas y SMS al equipo con cámaras de alta resolución e inteligencia artificial.', NULL, 2, 'Activo'),
(3, 1, 'Generaciones de telefonía (1G a 6G)', 'Cada generación de telefonía móvil, su década, sus servicios, su velocidad y su tecnología, y las unidades de velocidad Kbps, Mbps y Gbps.', NULL, 3, 'Activo'),
(4, 2, 'Uso correcto de la mesa y las herramientas de trabajo', 'Mesa de trabajo limpia y con buena iluminación, descarga electrostática, uso correcto de desarmadores, pinzas, plancha separadora y palancas plásticas, medidas de seguridad previas y buenas prácticas.', NULL, 1, 'Activo'),
(5, 2, '¿Cómo reconocer un dispositivo?', 'Tres formas de identificar un celular: desde Ajustes (Información del teléfono), tomando una foto con Google Lens y revisando el sticker de la parte trasera.', NULL, 2, 'Activo');

INSERT INTO `actividades` (`id_actividad`, `id_tema`, `titulo`, `descripcion`, `tipo`, `numero_preguntas`, `modo_tiempo`, `tiempo_limite`, `puntaje_minimo`, `fecha_limite`, `estado`) VALUES
(1, 1, 'Cuestionario: Hardware y Software', 'Diferencia las partes físicas del celular de las partes del programa.', 'Cuestionario', 5, 'total', 180, 60.00, '2026-10-10 23:59:00', 'Activo'),
(2, 2, 'Cuestionario: Evolución de los celulares', 'Reconoce los hitos de cada modelo en la evolución de los celulares. Cada pregunta tiene su propio reloj.', 'Cuestionario', 5, 'por_pregunta', 20, 60.00, '2026-10-13 23:59:00', 'Activo'),
(3, 3, 'Cuestionario: Generaciones de telefonía', 'Relaciona cada generación (1G a 6G) con su velocidad, servicios y tecnología.', 'Cuestionario', 6, 'total', 300, 60.00, '2026-10-17 23:59:00', 'Activo'),
(4, 4, 'Cuestionario: Mesa y herramientas de trabajo', 'Repasa las normas de seguridad, el uso correcto de las herramientas y las buenas prácticas de taller. Cada pregunta tiene su propio reloj.', 'Cuestionario', 7, 'por_pregunta', 30, 60.00, '2026-10-21 23:59:00', 'Activo'),
(5, 5, 'Cuestionario: Cómo reconocer un dispositivo', 'Identifica un celular desde Ajustes, con una foto o con su sticker. Sin límite de tiempo.', 'Cuestionario', 5, 'sin_limite', NULL, 60.00, '2026-10-29 23:59:00', 'Activo');

INSERT INTO `preguntas` (`id_pregunta`, `id_actividad`, `pregunta`, `tipo`, `puntos`, `imagen`, `audio`, `tiempo_limite`, `explicacion`, `estado`) VALUES
(1, 1, 'El sistema operativo (Android o iOS) forma parte del software del celular.', 'verdadero_falso', 2, NULL, NULL, NULL, 'El sistema operativo es parte del software: es el programa que gestiona el equipo.', 'Activo'),
(2, 1, 'La placa base es una parte del software del celular.', 'verdadero_falso', 2, NULL, NULL, NULL, 'La placa base es una parte física, es decir, hardware.', 'Activo'),
(3, 1, '¿Cuál de estos elementos es hardware?', 'imagen', 2, NULL, NULL, NULL, 'La placa base es una parte física del celular. Firmware, apps y drivers son software.', 'Activo'),
(4, 1, '¿Cuál de estos elementos es software?', 'imagen', 2, NULL, NULL, NULL, 'El sistema de archivos forma parte del software. Pantalla, batería y antenas son hardware.', 'Activo'),
(5, 1, 'Las partes físicas del celular, como la pantalla o la batería, se conocen como ______.', 'completar', 2, NULL, NULL, NULL, 'Hardware son las partes físicas; software son las partes del programa.', 'Activo'),
(6, 2, '¿Qué celular de la evolución se destaca por su diseño delgado y plegable?', 'imagen', 2, NULL, NULL, NULL, 'El Motorola RAZR V3 (2004) se caracterizó por su diseño delgado y plegable.', 'Activo'),
(7, 2, 'El iPhone X (2017) incorporó Face ID y una pantalla sin bordes.', 'verdadero_falso', 2, NULL, NULL, NULL, 'Face ID y pantalla sin bordes son los hitos del iPhone X.', 'Activo'),
(8, 2, '¿Qué celular de la evolución incorporó un S Pen integrado?', 'imagen', 2, NULL, NULL, NULL, 'El Samsung Galaxy S22 Ultra (2022) integró el S Pen.', 'Activo'),
(9, 2, 'El iPhone 4 (2010) funcionaba con el sistema operativo Android.', 'verdadero_falso', 2, NULL, NULL, NULL, 'El iPhone 4 funcionaba con iOS, el sistema de Apple.', 'Activo'),
(10, 2, 'El Samsung Galaxy S24 Ultra (2024) destaca por incorporar ______ artificial.', 'completar', 2, NULL, NULL, NULL, 'El S24 Ultra se asocia con la inteligencia artificial.', 'Activo'),
(11, 3, '¿Qué generación se asocia con llamadas y SMS, tecnología GSM / CDMA y 64 Kbps?', 'imagen', 2, NULL, NULL, NULL, '2G (década de 1990): llamadas y SMS, 64 Kbps, GSM / CDMA.', 'Activo'),
(12, 3, '¿Qué tecnología corresponde a la generación 4G?', 'imagen', 2, NULL, NULL, NULL, '4G usa LTE / LTE-Advanced. AMPS es 1G, GSM / CDMA es 2G y UMTS / WCDMA es 3G.', 'Activo'),
(13, 3, '1 Gbps equivale a 1,000 Mbps.', 'verdadero_falso', 2, NULL, NULL, NULL, 'Cada unidad vale 1,000 veces la anterior: 1 Mbps = 1,000 Kbps y 1 Gbps = 1,000 Mbps.', 'Activo'),
(14, 3, 'La generación 3G alcanzaba aproximadamente 10 Gbps de velocidad.', 'verdadero_falso', 2, NULL, NULL, NULL, '3G llegaba a unos 2 Mbps. Los 10 Gbps corresponden a 5G.', 'Activo'),
(15, 3, '1 Mbps equivale a 1,000 ______.', 'completar', 2, NULL, NULL, NULL, 'Un megabit por segundo equivale a 1,000 kilobits por segundo.', 'Activo'),
(16, 3, '¿Qué generación está en desarrollo, con proyección estimada, IA nativa y hologramas?', 'imagen', 2, NULL, NULL, NULL, '6G (década de 2030): IA nativa, hologramas y redes inteligentes; aún está en desarrollo.', 'Activo'),
(17, 4, '¿Qué debes usar para evitar la descarga electrostática (EDS) antes de trabajar?', 'imagen', 2, NULL, NULL, NULL, 'Se recomienda una pulsera antiestática o tocar una superficie metálica antes de trabajar.', 'Activo'),
(18, 4, 'Antes de desarmar se debe apagar el equipo y desconectar la batería lo más pronto posible.', 'verdadero_falso', 2, NULL, NULL, NULL, 'Apagar el equipo y desconectar la batería es una medida de seguridad previa.', 'Activo'),
(19, 4, 'Es recomendable trabajar con las manos húmedas.', 'verdadero_falso', 2, NULL, NULL, NULL, 'No se debe trabajar con las manos húmedas ni permitir contacto con líquidos.', 'Activo'),
(20, 4, '¿Qué rango de calor moderado se indica para la plancha separadora?', 'imagen', 2, NULL, NULL, NULL, 'Se aplica calor moderado de 80 a 120 °C, sin sobrecalentar, para no dañar la pantalla ni la batería.', 'Activo'),
(21, 4, 'Para abrir el equipo sin rayar ni dañar, ¿qué herramienta se recomienda?', 'imagen', 2, NULL, NULL, NULL, 'Las palancas plásticas (spudger) abren sin rayar; se deben evitar herramientas metálicas en partes sensibles.', 'Activo'),
(22, 4, 'Los cables flex son resistentes y se pueden doblar sin problema.', 'verdadero_falso', 2, NULL, NULL, NULL, 'Los cables flex son frágiles y se rompen fácilmente; se debe evitar doblarlos.', 'Activo'),
(23, 4, 'Antes de desarmar, toma ______ del proceso para recordar cómo armar el equipo.', 'completar', 2, NULL, NULL, NULL, 'Tomar fotos del proceso ayuda a recordar el armado.', 'Activo'),
(24, 5, '¿Qué opción de Ajustes se abre para ver las características del celular?', 'imagen', 2, NULL, NULL, NULL, 'En Ajustes, "Información del teléfono" muestra procesador, RAM, modelo, IMEI y versión de Android.', 'Activo'),
(25, 5, 'Google Lens permite tomar una foto del móvil para consultar su modelo y características.', 'verdadero_falso', 2, NULL, NULL, NULL, 'El libro propone tomar una foto con Google Lens y luego una captura del modelo y sus características.', 'Activo'),
(26, 5, '¿Dónde viene pegado el sticker con los datos del celular?', 'imagen', 2, NULL, NULL, NULL, 'El sticker viene pegado en la parte trasera del móvil.', 'Activo'),
(27, 5, 'El código que identifica a cada celular, y que aparece por cada ranura de SIM en Información del teléfono, es el ______.', 'completar', 2, NULL, NULL, NULL, 'El IMEI se muestra por ranura de SIM en Información del teléfono.', 'Activo'),
(28, 5, 'El sticker del celular se debe quitar porque no sirve para nada.', 'verdadero_falso', 2, NULL, NULL, NULL, 'Es de suma importancia guardarlo y no sacarlo.', 'Activo');

INSERT INTO `opciones_respuesta` (`id_opcion`, `id_pregunta`, `texto`, `imagen`, `correcta`, `retroalimentacion`) VALUES
(1, 1, 'Verdadero', NULL, 1, NULL),
(2, 1, 'Falso', NULL, 0, NULL),
(3, 2, 'Verdadero', NULL, 0, NULL),
(4, 2, 'Falso', NULL, 1, NULL),
(5, 3, 'Firmware', NULL, 0, NULL),
(6, 3, 'Placa base', NULL, 1, NULL),
(7, 3, 'Aplicaciones (apps)', NULL, 0, NULL),
(8, 3, 'Controladores (drivers)', NULL, 0, NULL),
(9, 4, 'Pantalla', NULL, 0, NULL),
(10, 4, 'Batería', NULL, 0, NULL),
(11, 4, 'Sistema de archivos', NULL, 1, NULL),
(12, 4, 'Antenas', NULL, 0, NULL),
(13, 5, 'hardware', NULL, 1, NULL),
(14, 6, 'Nokia 3310', NULL, 0, NULL),
(15, 6, 'Motorola RAZR V3', NULL, 1, NULL),
(16, 6, 'iPhone 4', NULL, 0, NULL),
(17, 6, 'Samsung Galaxy S4', NULL, 0, NULL),
(18, 7, 'Verdadero', NULL, 1, NULL),
(19, 7, 'Falso', NULL, 0, NULL),
(20, 8, 'iPhone X', NULL, 0, NULL),
(21, 8, 'Samsung Galaxy S4', NULL, 0, NULL),
(22, 8, 'Samsung Galaxy S22 Ultra', NULL, 1, NULL),
(23, 8, 'Nokia 3310', NULL, 0, NULL),
(24, 9, 'Verdadero', NULL, 0, NULL),
(25, 9, 'Falso', NULL, 1, NULL),
(26, 10, 'inteligencia', NULL, 1, NULL),
(27, 11, '1G', NULL, 0, NULL),
(28, 11, '2G', NULL, 1, NULL),
(29, 11, '3G', NULL, 0, NULL),
(30, 11, '4G', NULL, 0, NULL),
(31, 12, 'AMPS', NULL, 0, NULL),
(32, 12, 'GSM / CDMA', NULL, 0, NULL),
(33, 12, 'UMTS / WCDMA', NULL, 0, NULL),
(34, 12, 'LTE / LTE-Advanced', NULL, 1, NULL),
(35, 13, 'Verdadero', NULL, 1, NULL),
(36, 13, 'Falso', NULL, 0, NULL),
(37, 14, 'Verdadero', NULL, 0, NULL),
(38, 14, 'Falso', NULL, 1, NULL),
(39, 15, 'Kbps', NULL, 1, NULL),
(40, 16, '4G', NULL, 0, NULL),
(41, 16, '5G', NULL, 0, NULL),
(42, 16, '6G', NULL, 1, NULL),
(43, 16, '3G', NULL, 0, NULL),
(44, 17, 'Una plancha caliente', NULL, 0, NULL),
(45, 17, 'Una pulsera antiestática', NULL, 1, NULL),
(46, 17, 'Guantes de lana', NULL, 0, NULL),
(47, 17, 'Un imán', NULL, 0, NULL),
(48, 18, 'Verdadero', NULL, 1, NULL),
(49, 18, 'Falso', NULL, 0, NULL),
(50, 19, 'Verdadero', NULL, 0, NULL),
(51, 19, 'Falso', NULL, 1, NULL),
(52, 20, '40 - 60 °C', NULL, 0, NULL),
(53, 20, '150 - 200 °C', NULL, 0, NULL),
(54, 20, '80 - 120 °C', NULL, 1, NULL),
(55, 20, '200 - 250 °C', NULL, 0, NULL),
(56, 21, 'Un cuchillo', NULL, 0, NULL),
(57, 21, 'Un martillo', NULL, 0, NULL),
(58, 21, 'Pinzas metálicas', NULL, 0, NULL),
(59, 21, 'Palancas plásticas (spudger)', NULL, 1, NULL),
(60, 22, 'Verdadero', NULL, 0, NULL),
(61, 22, 'Falso', NULL, 1, NULL),
(62, 23, 'fotos', NULL, 1, NULL),
(63, 24, 'Ubicación', NULL, 0, NULL),
(64, 24, 'Información del teléfono', NULL, 1, NULL),
(65, 24, 'Emergencia', NULL, 0, NULL),
(66, 24, 'Bienestar digital', NULL, 0, NULL),
(67, 25, 'Verdadero', NULL, 1, NULL),
(68, 25, 'Falso', NULL, 0, NULL),
(69, 26, 'En la pantalla', NULL, 0, NULL),
(70, 26, 'En el puerto de carga', NULL, 0, NULL),
(71, 26, 'En la parte trasera del móvil', NULL, 1, NULL),
(72, 26, 'En la tapa de la SIM', NULL, 0, NULL),
(73, 27, 'IMEI', NULL, 1, NULL),
(74, 28, 'Verdadero', NULL, 0, NULL),
(75, 28, 'Falso', NULL, 1, NULL);

INSERT INTO `logros` (`id_logro`, `nombre`, `descripcion`, `icono`, `tipo`, `condicion`, `estado`) VALUES
(1, 'Primer cuestionario', 'Completa tu primer cuestionario.', NULL, 'Actividad', 'Completar 1 actividad', 'Activo'),
(2, 'Puntaje perfecto', 'Obtén el 100% en una actividad.', NULL, 'Puntaje', 'Obtener 100%', 'Activo'),
(3, 'Primer curso completado', 'Completa tu primer curso.', NULL, 'Curso', 'Completar 1 curso', 'Activo');

INSERT INTO `rutas_aprendizaje` (`id_ruta`, `nombre`, `descripcion`, `nivel`, `estado`) VALUES
(1, 'Técnico en reparación de celulares', 'Ruta de aprendizaje para iniciarse en la reparación de celulares con BeatCell Academia.', 'Básico', 'Activo');

INSERT INTO `ruta_cursos` (`id_ruta_curso`, `id_ruta`, `id_curso`, `orden`) VALUES
(1, 1, 1, 1);

-- Usuarios (el usuario Peter Parker pasa a id 6 para no chocar con Enma Suarez, id 1)
INSERT INTO `usuarios` (`id_usuario`, `codigo`, `dni`, `nombres`, `apellidos`, `correo`, `password`, `password_reset_token`, `password_reset_expira`, `rol`, `estado`, `fecha_registro`) VALUES
(1, 'BCC8854B2B82EE', '12345678', 'Enma', 'Suarez', 'senma@mail.com', '$2y$10$3MepVHVbR7U4TPfYkBfyw./pN43lL4EuXiSUJfY3W4l/WCWU/L5Qy', NULL, NULL, 'Estudiante', 'Activo', '2026-10-01 09:32:34'),
(2, 'BCC8854B2B92EE', '95123641', 'Nick', 'Fury', 'nifury@mail.com', '$2y$10$3iZ5MnV29dqbXVZNz79BgeKRcqeYK.jhh2g7Zhc/arPFj9ndkW0E2', NULL, NULL, 'Administrador', 'Activo', '2026-10-01 11:59:07'),
(3, 'BCC9954B2B92EE', NULL, 'Bruce', 'Banner', 'brunner@mail.com', '$2y$10$3iZ5MnV29dqbXVZNz79BgeKRcqeYK.jhh2g7Zhc/arPFj9ndkW0E2', NULL, NULL, 'Docente', 'Activo', '2026-10-01 12:05:34'),
(4, 'BC5B5C50D652AF', '05040607', 'Cesar', 'Ramirez', 'estudiantecs@gmail.com', '$2y$10$yQMNZsDdV3K3traBPqLKk.xRvDMI2SL7TdqYACraaudNofzSdBAqe', NULL, NULL, 'Administrador', 'Activo', '2026-10-05 08:39:21'),
(5, 'BC92D463154530', '55555555', 'Martin', 'Rodriguez', 'estudiantema@gmail.com', '$2y$10$DBbVhPBk0r38QA/eWJ70LOAsMqCJpgB7nqMwDYgdxMY4u9WyLguri', NULL, NULL, 'Estudiante', 'Activo', '2026-10-09 00:45:44'),
(6, 'BC5DBE56B4D2F4', '72719356', 'Peter', 'Parker', 'peparker@mail.com', '$2y$10$fkamzuGLnWN.TA3cGH2csOvq5H94mmViYRZeBl/c4uvqluX0XVnEm', NULL, NULL, 'Estudiante', 'Activo', '2026-10-12 11:05:05');

INSERT INTO `progreso_curso` (`id_progreso`, `id_usuario`, `id_curso`, `porcentaje`, `fecha_inicio`, `ultima_actividad`, `estado`) VALUES
(1, 5, 1, 20.00, '2026-10-09 00:47:22', '2026-10-09 00:47:22', 'En progreso'),
(2, 1, 1, 40.00, '2026-10-09 09:24:12', '2026-10-09 09:25:46', 'En progreso');

INSERT INTO `progreso_tema` (`id_progreso_tema`, `id_usuario`, `id_tema`, `intentos`, `promedio`, `porcentaje`, `estado`, `ultima_actualizacion`) VALUES
(1, 5, 1, 1, 100.00, 100.00, 'Completado', '2026-10-09 00:47:22'),
(2, 1, 5, 1, 60.00, 100.00, 'Completado', '2026-10-09 09:24:12'),
(3, 1, 4, 1, 71.43, 100.00, 'Completado', '2026-10-09 09:25:46');

INSERT INTO `resultados` (`id_resultado`, `id_usuario`, `id_actividad`, `puntaje_obtenido`, `puntaje_total`, `total_preguntas`, `respuestas_correctas`, `porcentaje`, `estado`, `fecha`) VALUES
(1, 5, 1, 10, 10, 5, 5, 100.00, 'Aprobado', '2026-10-09 00:47:22'),
(2, 1, 5, 6, 10, 5, 3, 60.00, 'Aprobado', '2026-10-09 09:24:12'),
(3, 1, 4, 10, 14, 7, 5, 71.43, 'Aprobado', '2026-10-09 09:25:46');

INSERT INTO `respuestas_estudiante` (`id_respuesta`, `id_resultado`, `id_pregunta`, `id_opcion`, `respuesta_texto`, `correcta`, `puntos_obtenidos`) VALUES
(1, 1, 1, 1, NULL, 1, 2),
(2, 1, 2, 4, NULL, 1, 2),
(3, 1, 3, 6, NULL, 1, 2),
(4, 1, 4, 11, NULL, 1, 2),
(5, 1, 5, NULL, 'Hardware', 1, 2),
(6, 2, 24, 64, NULL, 1, 2),
(7, 2, 25, 68, NULL, 0, 0),
(8, 2, 26, 71, NULL, 1, 2),
(9, 2, 27, NULL, 'asd', 0, 0),
(10, 2, 28, 75, NULL, 1, 2),
(11, 3, 17, 45, NULL, 1, 2),
(12, 3, 18, 48, NULL, 1, 2),
(13, 3, 19, 51, NULL, 1, 2),
(14, 3, 20, 54, NULL, 1, 2),
(15, 3, 21, 59, NULL, 1, 2),
(16, 3, 22, 60, NULL, 0, 0),
(17, 3, 23, NULL, 'sa', 0, 0);

-- --------------------------------------------------------
-- ÍNDICES
-- --------------------------------------------------------

ALTER TABLE `actividades`
  ADD PRIMARY KEY (`id_actividad`),
  ADD KEY `idx_actividad_tema` (`id_tema`);

ALTER TABLE `certificados`
  ADD PRIMARY KEY (`id_certificado`),
  ADD UNIQUE KEY `codigo_certificado` (`codigo_certificado`),
  ADD UNIQUE KEY `uk_certificado_usuario_curso` (`id_usuario`,`id_curso`),
  ADD KEY `fk_certificado_curso` (`id_curso`);

ALTER TABLE `cursos`
  ADD PRIMARY KEY (`id_curso`);

ALTER TABLE `logros`
  ADD PRIMARY KEY (`id_logro`);

ALTER TABLE `mensajes_chat`
  ADD PRIMARY KEY (`id_mensaje`),
  ADD KEY `idx_mensaje_sesion` (`id_sesion`);

ALTER TABLE `modulos`
  ADD PRIMARY KEY (`id_modulo`),
  ADD UNIQUE KEY `uk_modulo_orden` (`id_curso`,`orden`);

ALTER TABLE `opciones_respuesta`
  ADD PRIMARY KEY (`id_opcion`),
  ADD KEY `fk_opcion_pregunta` (`id_pregunta`);

ALTER TABLE `preguntas`
  ADD PRIMARY KEY (`id_pregunta`),
  ADD KEY `idx_pregunta_actividad` (`id_actividad`);

ALTER TABLE `progreso_curso`
  ADD PRIMARY KEY (`id_progreso`),
  ADD UNIQUE KEY `uk_usuario_curso` (`id_usuario`,`id_curso`),
  ADD KEY `fk_progreso_curso` (`id_curso`);

ALTER TABLE `progreso_tema`
  ADD PRIMARY KEY (`id_progreso_tema`),
  ADD UNIQUE KEY `uk_usuario_tema` (`id_usuario`,`id_tema`),
  ADD KEY `fk_progreso_tema_tema` (`id_tema`);

ALTER TABLE `respuestas_estudiante`
  ADD PRIMARY KEY (`id_respuesta`),
  ADD UNIQUE KEY `uk_resultado_pregunta` (`id_resultado`,`id_pregunta`),
  ADD KEY `fk_respuesta_pregunta` (`id_pregunta`),
  ADD KEY `fk_respuesta_opcion` (`id_opcion`);

ALTER TABLE `resultados`
  ADD PRIMARY KEY (`id_resultado`),
  ADD UNIQUE KEY `uk_usuario_actividad` (`id_usuario`,`id_actividad`),
  ADD KEY `idx_resultado_usuario` (`id_usuario`),
  ADD KEY `idx_resultado_actividad` (`id_actividad`);

ALTER TABLE `rutas_aprendizaje`
  ADD PRIMARY KEY (`id_ruta`);

ALTER TABLE `ruta_cursos`
  ADD PRIMARY KEY (`id_ruta_curso`),
  ADD UNIQUE KEY `uk_ruta_curso` (`id_ruta`,`id_curso`),
  ADD UNIQUE KEY `uk_ruta_orden` (`id_ruta`,`orden`),
  ADD KEY `fk_ruta_cursos_curso` (`id_curso`);

ALTER TABLE `sesiones_chat`
  ADD PRIMARY KEY (`id_sesion`),
  ADD KEY `fk_sesion_usuario` (`id_usuario`);

ALTER TABLE `temas`
  ADD PRIMARY KEY (`id_tema`),
  ADD UNIQUE KEY `uk_tema_orden` (`id_modulo`,`orden`),
  ADD KEY `idx_tema_modulo` (`id_modulo`);

ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD UNIQUE KEY `correo` (`correo`),
  ADD UNIQUE KEY `dni` (`dni`),
  ADD KEY `idx_password_reset_token` (`password_reset_token`);

ALTER TABLE `usuario_logros`
  ADD PRIMARY KEY (`id_usuario_logro`),
  ADD UNIQUE KEY `uk_usuario_logro` (`id_usuario`,`id_logro`),
  ADD KEY `fk_usuario_logro_logro` (`id_logro`);

-- --------------------------------------------------------
-- AUTO_INCREMENT
-- --------------------------------------------------------

ALTER TABLE `actividades`           MODIFY `id_actividad` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
ALTER TABLE `certificados`          MODIFY `id_certificado` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `cursos`                MODIFY `id_curso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
ALTER TABLE `logros`                MODIFY `id_logro` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
ALTER TABLE `mensajes_chat`         MODIFY `id_mensaje` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `modulos`               MODIFY `id_modulo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
ALTER TABLE `opciones_respuesta`    MODIFY `id_opcion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;
ALTER TABLE `preguntas`             MODIFY `id_pregunta` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;
ALTER TABLE `progreso_curso`        MODIFY `id_progreso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
ALTER TABLE `progreso_tema`         MODIFY `id_progreso_tema` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
ALTER TABLE `respuestas_estudiante` MODIFY `id_respuesta` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;
ALTER TABLE `resultados`            MODIFY `id_resultado` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
ALTER TABLE `rutas_aprendizaje`     MODIFY `id_ruta` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
ALTER TABLE `ruta_cursos`           MODIFY `id_ruta_curso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
ALTER TABLE `sesiones_chat`         MODIFY `id_sesion` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `temas`                 MODIFY `id_tema` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
ALTER TABLE `usuarios`              MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
ALTER TABLE `usuario_logros`        MODIFY `id_usuario_logro` int(11) NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------
-- CLAVES FORÁNEAS
-- --------------------------------------------------------

ALTER TABLE `actividades`
  ADD CONSTRAINT `fk_actividad_tema` FOREIGN KEY (`id_tema`) REFERENCES `temas` (`id_tema`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `certificados`
  ADD CONSTRAINT `fk_certificado_curso` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_certificado_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `mensajes_chat`
  ADD CONSTRAINT `fk_mensaje_sesion` FOREIGN KEY (`id_sesion`) REFERENCES `sesiones_chat` (`id_sesion`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `modulos`
  ADD CONSTRAINT `fk_modulo_curso` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `opciones_respuesta`
  ADD CONSTRAINT `fk_opcion_pregunta` FOREIGN KEY (`id_pregunta`) REFERENCES `preguntas` (`id_pregunta`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `preguntas`
  ADD CONSTRAINT `fk_pregunta_actividad` FOREIGN KEY (`id_actividad`) REFERENCES `actividades` (`id_actividad`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `progreso_curso`
  ADD CONSTRAINT `fk_progreso_curso` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_progreso_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `progreso_tema`
  ADD CONSTRAINT `fk_progreso_tema_tema` FOREIGN KEY (`id_tema`) REFERENCES `temas` (`id_tema`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_progreso_tema_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `respuestas_estudiante`
  ADD CONSTRAINT `fk_respuesta_opcion` FOREIGN KEY (`id_opcion`) REFERENCES `opciones_respuesta` (`id_opcion`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_respuesta_pregunta` FOREIGN KEY (`id_pregunta`) REFERENCES `preguntas` (`id_pregunta`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_respuesta_resultado` FOREIGN KEY (`id_resultado`) REFERENCES `resultados` (`id_resultado`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `resultados`
  ADD CONSTRAINT `fk_resultado_actividad` FOREIGN KEY (`id_actividad`) REFERENCES `actividades` (`id_actividad`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_resultado_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `ruta_cursos`
  ADD CONSTRAINT `fk_ruta_cursos_curso` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ruta_cursos_ruta` FOREIGN KEY (`id_ruta`) REFERENCES `rutas_aprendizaje` (`id_ruta`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `sesiones_chat`
  ADD CONSTRAINT `fk_sesion_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `temas`
  ADD CONSTRAINT `fk_tema_modulo` FOREIGN KEY (`id_modulo`) REFERENCES `modulos` (`id_modulo`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `usuario_logros`
  ADD CONSTRAINT `fk_usuario_logro_logro` FOREIGN KEY (`id_logro`) REFERENCES `logros` (`id_logro`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_usuario_logro_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
