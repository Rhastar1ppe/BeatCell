-- ============================================================
-- BeatCell - Migración para bases de datos YA EXISTENTES
-- Ejecutar sobre la base `beatcell` (MariaDB 10.4+).
-- Es seguro ejecutarla varias veces: no borra ni modifica datos.
-- ============================================================
USE `beatcell`;

-- Videos por módulo (gestion/modulos.php + estudiante/curso.php)
ALTER TABLE `modulos`
  ADD COLUMN IF NOT EXISTS `video_url` varchar(2048) DEFAULT NULL AFTER `descripcion`;

-- Preguntas de audio (gestion/preguntas.php + estudiante/actividad.php)
ALTER TABLE `preguntas`
  ADD COLUMN IF NOT EXISTS `audio` varchar(255) DEFAULT NULL AFTER `imagen`;

ALTER TABLE `preguntas`
  MODIFY `tipo` enum('opcion_multiple','verdadero_falso','completar','imagen','audio') NOT NULL;

-- Modo de tiempo y fecha límite de actividades
ALTER TABLE `actividades`
  ADD COLUMN IF NOT EXISTS `modo_tiempo` enum('sin_limite','total','por_pregunta') NOT NULL DEFAULT 'sin_limite' AFTER `numero_preguntas`,
  ADD COLUMN IF NOT EXISTS `fecha_limite` datetime DEFAULT NULL;

-- Si `modo_tiempo` existía como varchar, se convierte al ENUM (valores no válidos pasan a 'sin_limite')
UPDATE `actividades` SET `modo_tiempo` = 'sin_limite'
  WHERE `modo_tiempo` IS NULL OR `modo_tiempo` NOT IN ('sin_limite','total','por_pregunta');
ALTER TABLE `actividades`
  MODIFY `modo_tiempo` enum('sin_limite','total','por_pregunta') NOT NULL DEFAULT 'sin_limite';

-- Recuperación de contraseña (auth/recover.php)
ALTER TABLE `usuarios`
  ADD COLUMN IF NOT EXISTS `password_reset_token` varchar(255) DEFAULT NULL AFTER `password`,
  ADD COLUMN IF NOT EXISTS `password_reset_expira` datetime DEFAULT NULL AFTER `password_reset_token`;

ALTER TABLE `usuarios`
  ADD INDEX IF NOT EXISTS `idx_password_reset_token` (`password_reset_token`);
