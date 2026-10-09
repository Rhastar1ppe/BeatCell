-- =========================================================
-- BASE DE DATOS: BEATCELL
-- Sistema Académico
-- =========================================================

DROP DATABASE IF EXISTS beatcell;

CREATE DATABASE beatcell
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE beatcell;

-- =========================================================
-- 1. USUARIOS
-- =========================================================

CREATE TABLE usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    dni CHAR(8) UNIQUE,
    nombres VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    correo VARCHAR(200) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    password_reset_token VARCHAR(255) NULL,
    password_reset_expira DATETIME NULL,
    rol ENUM('Estudiante', 'Docente', 'Administrador') NOT NULL
        DEFAULT 'Estudiante',
    estado ENUM('Activo', 'Inactivo') NOT NULL
        DEFAULT 'Activo',
    fecha_registro DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_password_reset_token (password_reset_token)
) ENGINE=InnoDB;


-- =========================================================
-- 2. CURSOS
-- =========================================================

CREATE TABLE cursos (
    id_curso INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    imagen VARCHAR(255),
    estado ENUM('Activo', 'Inactivo') NOT NULL
        DEFAULT 'Activo',
    fecha_creacion DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


-- =========================================================
-- 3. MODULOS
-- =========================================================

CREATE TABLE modulos (
    id_modulo INT AUTO_INCREMENT PRIMARY KEY,
    id_curso INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT,
    orden INT NOT NULL DEFAULT 1,
    estado ENUM('Activo', 'Inactivo') NOT NULL
        DEFAULT 'Activo',

    CONSTRAINT fk_modulo_curso
        FOREIGN KEY (id_curso)
        REFERENCES cursos(id_curso)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    UNIQUE KEY uk_modulo_orden (id_curso, orden)
) ENGINE=InnoDB;


-- =========================================================
-- 4. TEMAS
-- =========================================================

CREATE TABLE temas (
    id_tema INT AUTO_INCREMENT PRIMARY KEY,
    id_modulo INT NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    descripcion TEXT,
    material_apoyo TEXT,
    orden INT NOT NULL DEFAULT 1,
    estado ENUM('Activo', 'Inactivo') NOT NULL
        DEFAULT 'Activo',

    CONSTRAINT fk_tema_modulo
        FOREIGN KEY (id_modulo)
        REFERENCES modulos(id_modulo)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    UNIQUE KEY uk_tema_orden (id_modulo, orden)
) ENGINE=InnoDB;


-- =========================================================
-- 5. ACTIVIDADES
-- =========================================================

CREATE TABLE actividades (
    id_actividad INT AUTO_INCREMENT PRIMARY KEY,
    id_tema INT NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    descripcion TEXT,
    tipo VARCHAR(50) NOT NULL DEFAULT 'Cuestionario',
    numero_preguntas INT NOT NULL DEFAULT 0,
    tiempo_limite INT,
    puntaje_minimo DECIMAL(5,2),
    estado ENUM('Activo', 'Inactivo') NOT NULL
        DEFAULT 'Activo',

    CONSTRAINT fk_actividad_tema
        FOREIGN KEY (id_tema)
        REFERENCES temas(id_tema)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB;


-- =========================================================
-- 6. PREGUNTAS
-- =========================================================

CREATE TABLE preguntas (
    id_pregunta INT AUTO_INCREMENT PRIMARY KEY,
    id_actividad INT NOT NULL,
    pregunta TEXT NOT NULL,

    tipo ENUM(
        'opcion_multiple',
        'verdadero_falso',
        'completar',
        'imagen'
    ) NOT NULL,

    puntos INT NOT NULL DEFAULT 1,
    imagen VARCHAR(255),
    tiempo_limite INT,
    explicacion TEXT,

    estado ENUM('Activo', 'Inactivo') NOT NULL
        DEFAULT 'Activo',

    CONSTRAINT fk_pregunta_actividad
        FOREIGN KEY (id_actividad)
        REFERENCES actividades(id_actividad)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB;


-- =========================================================
-- 7. OPCIONES DE RESPUESTA
-- =========================================================

CREATE TABLE opciones_respuesta (
    id_opcion INT AUTO_INCREMENT PRIMARY KEY,
    id_pregunta INT NOT NULL,
    texto VARCHAR(255),
    imagen VARCHAR(255),
    correcta BOOLEAN NOT NULL DEFAULT FALSE,
    retroalimentacion TEXT,

    CONSTRAINT fk_opcion_pregunta
        FOREIGN KEY (id_pregunta)
        REFERENCES preguntas(id_pregunta)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB;


-- =========================================================
-- 8. RESULTADOS
-- Cada registro representa un intento.
-- =========================================================

CREATE TABLE resultados (
    id_resultado INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_actividad INT NOT NULL,

    puntaje_obtenido INT NOT NULL DEFAULT 0,
    puntaje_total INT NOT NULL DEFAULT 0,
    total_preguntas INT NOT NULL DEFAULT 0,
    respuestas_correctas INT NOT NULL DEFAULT 0,

    porcentaje DECIMAL(5,2) NOT NULL DEFAULT 0.00,

    estado ENUM(
        'Aprobado',
        'Desaprobado'
    ) NOT NULL,

    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_resultado_usuario
        FOREIGN KEY (id_usuario)
        REFERENCES usuarios(id_usuario)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_resultado_actividad
        FOREIGN KEY (id_actividad)
        REFERENCES actividades(id_actividad)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT uk_usuario_actividad
        UNIQUE (id_usuario, id_actividad)
) ENGINE=InnoDB;


-- =========================================================
-- 9. RESPUESTAS DEL ESTUDIANTE
-- =========================================================

CREATE TABLE respuestas_estudiante (
    id_respuesta INT AUTO_INCREMENT PRIMARY KEY,
    id_resultado INT NOT NULL,
    id_pregunta INT NOT NULL,

    -- Puede ser NULL para preguntas de completar.
    id_opcion INT NULL,

    -- Se utiliza principalmente para respuestas escritas.
    respuesta_texto TEXT NULL,

    correcta BOOLEAN NOT NULL DEFAULT FALSE,
    puntos_obtenidos INT NOT NULL DEFAULT 0,

    CONSTRAINT fk_respuesta_resultado
        FOREIGN KEY (id_resultado)
        REFERENCES resultados(id_resultado)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_respuesta_pregunta
        FOREIGN KEY (id_pregunta)
        REFERENCES preguntas(id_pregunta)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_respuesta_opcion
        FOREIGN KEY (id_opcion)
        REFERENCES opciones_respuesta(id_opcion)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    UNIQUE KEY uk_resultado_pregunta (
        id_resultado,
        id_pregunta
    )
) ENGINE=InnoDB;


-- =========================================================
-- 10. PROGRESO POR CURSO
-- =========================================================

CREATE TABLE progreso_curso (
    id_progreso INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_curso INT NOT NULL,

    porcentaje DECIMAL(5,2) NOT NULL DEFAULT 0.00,

    fecha_inicio DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    ultima_actividad DATETIME NULL,

    estado ENUM(
        'No iniciado',
        'En progreso',
        'Completado'
    ) NOT NULL DEFAULT 'No iniciado',

    CONSTRAINT fk_progreso_usuario
        FOREIGN KEY (id_usuario)
        REFERENCES usuarios(id_usuario)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_progreso_curso
        FOREIGN KEY (id_curso)
        REFERENCES cursos(id_curso)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    UNIQUE KEY uk_usuario_curso (
        id_usuario,
        id_curso
    )
) ENGINE=InnoDB;


-- =========================================================
-- 11. PROGRESO POR TEMA
-- =========================================================

CREATE TABLE progreso_tema (
    id_progreso_tema INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_tema INT NOT NULL,

    intentos INT NOT NULL DEFAULT 0,
    promedio DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    porcentaje DECIMAL(5,2) NOT NULL DEFAULT 0.00,

    estado ENUM(
        'No iniciado',
        'En progreso',
        'Completado',
        'Reforzar'
    ) NOT NULL DEFAULT 'No iniciado',

    ultima_actualizacion DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_progreso_tema_usuario
        FOREIGN KEY (id_usuario)
        REFERENCES usuarios(id_usuario)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_progreso_tema_tema
        FOREIGN KEY (id_tema)
        REFERENCES temas(id_tema)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    UNIQUE KEY uk_usuario_tema (
        id_usuario,
        id_tema
    )
) ENGINE=InnoDB;


-- =========================================================
-- 12. RUTAS DE APRENDIZAJE
-- =========================================================

CREATE TABLE rutas_aprendizaje (
    id_ruta INT AUTO_INCREMENT PRIMARY KEY,

    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT,

    nivel ENUM(
        'Básico',
        'Intermedio',
        'Avanzado'
    ),

    estado ENUM(
        'Activo',
        'Inactivo'
    ) NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB;


-- =========================================================
-- 13. CURSOS DE UNA RUTA
-- Tabla intermedia N:M
-- =========================================================

CREATE TABLE ruta_cursos (
    id_ruta_curso INT AUTO_INCREMENT PRIMARY KEY,
    id_ruta INT NOT NULL,
    id_curso INT NOT NULL,
    orden INT NOT NULL,

    CONSTRAINT fk_ruta_cursos_ruta
        FOREIGN KEY (id_ruta)
        REFERENCES rutas_aprendizaje(id_ruta)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_ruta_cursos_curso
        FOREIGN KEY (id_curso)
        REFERENCES cursos(id_curso)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    UNIQUE KEY uk_ruta_curso (
        id_ruta,
        id_curso
    ),

    UNIQUE KEY uk_ruta_orden (
        id_ruta,
        orden
    )
) ENGINE=InnoDB;


-- =========================================================
-- 14. LOGROS
-- =========================================================

CREATE TABLE logros (
    id_logro INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    icono VARCHAR(255),
    tipo VARCHAR(50),
    condicion VARCHAR(255),

    estado ENUM(
        'Activo',
        'Inactivo'
    ) NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB;


-- =========================================================
-- 15. LOGROS OBTENIDOS POR USUARIOS
-- Tabla intermedia N:M
-- =========================================================

CREATE TABLE usuario_logros (
    id_usuario_logro INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_logro INT NOT NULL,

    fecha_obtenido DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_usuario_logro_usuario
        FOREIGN KEY (id_usuario)
        REFERENCES usuarios(id_usuario)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_usuario_logro_logro
        FOREIGN KEY (id_logro)
        REFERENCES logros(id_logro)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    UNIQUE KEY uk_usuario_logro (
        id_usuario,
        id_logro
    )
) ENGINE=InnoDB;


-- =========================================================
-- 16. CERTIFICADOS
-- =========================================================

CREATE TABLE certificados (
    id_certificado INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_curso INT NOT NULL,

    codigo_certificado VARCHAR(100)
        NOT NULL UNIQUE,

    archivo VARCHAR(255) NOT NULL,

    fecha_emision DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_certificado_usuario
        FOREIGN KEY (id_usuario)
        REFERENCES usuarios(id_usuario)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_certificado_curso
        FOREIGN KEY (id_curso)
        REFERENCES cursos(id_curso)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    UNIQUE KEY uk_certificado_usuario_curso (
        id_usuario,
        id_curso
    )
) ENGINE=InnoDB;


-- =========================================================
-- 17. SESIONES DEL CHATBOT
-- =========================================================

CREATE TABLE sesiones_chat (
    id_sesion INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,

    fecha_inicio DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    fecha_fin DATETIME NULL,

    estado ENUM(
        'Activa',
        'Finalizada'
    ) NOT NULL DEFAULT 'Activa',

    CONSTRAINT fk_sesion_usuario
        FOREIGN KEY (id_usuario)
        REFERENCES usuarios(id_usuario)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB;


-- =========================================================
-- 18. MENSAJES DEL CHATBOT
-- =========================================================

CREATE TABLE mensajes_chat (
    id_mensaje INT AUTO_INCREMENT PRIMARY KEY,
    id_sesion INT NOT NULL,

    remitente ENUM(
        'Usuario',
        'Bot'
    ) NOT NULL,

    mensaje TEXT NOT NULL,

    fecha_hora DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_mensaje_sesion
        FOREIGN KEY (id_sesion)
        REFERENCES sesiones_chat(id_sesion)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB;


-- =========================================================
-- ÍNDICES ADICIONALES
-- =========================================================

CREATE INDEX idx_resultado_usuario
ON resultados(id_usuario);

CREATE INDEX idx_resultado_actividad
ON resultados(id_actividad);

CREATE INDEX idx_pregunta_actividad
ON preguntas(id_actividad);

CREATE INDEX idx_tema_modulo
ON temas(id_modulo);

CREATE INDEX idx_actividad_tema
ON actividades(id_tema);

CREATE INDEX idx_mensaje_sesion
ON mensajes_chat(id_sesion);


-- =========================================================
-- DATOS INICIALES DE PRUEBA
-- =========================================================

INSERT INTO cursos (
    nombre,
    descripcion
)
VALUES
(
    'Matemática',
    'Curso de fundamentos de matemática.'
),
(
    'Programación',
    'Curso introductorio de programación.'
);

INSERT INTO modulos (
    id_curso,
    nombre,
    descripcion,
    orden
)
VALUES
(
    1,
    'Módulo 1: Fundamentos',
    'Conceptos básicos del curso de Matemática.',
    1
),
(
    2,
    'Módulo 1: Introducción',
    'Conceptos básicos de programación.',
    1
);


INSERT INTO rutas_aprendizaje (
    nombre,
    descripcion,
    nivel
)
VALUES
(
    'Fundamentos académicos',
    'Ruta de aprendizaje para comenzar a utilizar la plataforma.',
    'Básico'
);


INSERT INTO logros (
    nombre,
    descripcion,
    tipo,
    condicion
)
VALUES
(
    'Primer cuestionario',
    'Completa tu primer cuestionario.',
    'Actividad',
    'Completar 1 actividad'
),
(
    'Puntaje perfecto',
    'Obtén el 100% en una actividad.',
    'Puntaje',
    'Obtener 100%'
),
(
    'Primer curso completado',
    'Completa tu primer curso.',
    'Curso',
    'Completar 1 curso'
);


-- =========================================================
-- FIN
-- =========================================================