-- Migracion incremental para preguntas con video (MySQL y MariaDB).
-- No borra datos ni recrea tablas. Puede ejecutarse mas de una vez.
-- Ejecutar despues de seleccionar la base de datos BeatCell.
-- La tabla preguntas debe existir previamente.

DELIMITER $$

DROP PROCEDURE IF EXISTS beatcell_migrar_video_preguntas$$
CREATE PROCEDURE beatcell_migrar_video_preguntas()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'preguntas'
          AND COLUMN_NAME = 'video_url'
    ) THEN
        ALTER TABLE preguntas ADD COLUMN video_url VARCHAR(2048) NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'preguntas'
          AND COLUMN_NAME = 'tipo_respuesta'
    ) THEN
        ALTER TABLE preguntas ADD COLUMN tipo_respuesta VARCHAR(30) NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'preguntas'
          AND COLUMN_NAME = 'seleccion_multiple'
    ) THEN
        ALTER TABLE preguntas ADD COLUMN seleccion_multiple TINYINT(1) NOT NULL DEFAULT 0;
    END IF;

    -- Conservar todos los valores existentes del ENUM cuando sea posible.
    -- La base de datos de main ya incluye 'video'. Si otra instalacion
    -- no lo incluye, debe revisarse su ENUM antes de ampliar el tipo.
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'preguntas'
          AND COLUMN_NAME = 'tipo'
          AND COLUMN_TYPE LIKE '%video%'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'El ENUM preguntas.tipo no incluye video; revisar los valores existentes antes de modificarlo.';
    END IF;
END$$

CALL beatcell_migrar_video_preguntas()$$
DROP PROCEDURE beatcell_migrar_video_preguntas$$

DELIMITER ;
