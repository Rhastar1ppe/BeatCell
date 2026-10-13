-- Migracion de preguntas con video para BeatCell
-- Requiere MySQL/MariaDB compatible con ADD COLUMN IF NOT EXISTS.

ALTER TABLE preguntas
MODIFY COLUMN tipo ENUM(
    'opcion_multiple',
    'verdadero_falso',
    'completar',
    'imagen',
    'audio',
    'video'
) NOT NULL;

ALTER TABLE preguntas
ADD COLUMN IF NOT EXISTS video_url VARCHAR(2048) NULL;

ALTER TABLE preguntas
ADD COLUMN IF NOT EXISTS tipo_respuesta VARCHAR(30) NULL;

ALTER TABLE preguntas
ADD COLUMN IF NOT EXISTS seleccion_multiple TINYINT(1) NOT NULL DEFAULT 0;