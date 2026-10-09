<?php

class ProgresoRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function obtenerCursosPorUsuario(
        int $idUsuario
    ): array {
        $sql = "SELECT
                    c.id_curso,
                    c.nombre AS curso,
                    c.imagen,
                    COALESCE(pc.porcentaje, 0) AS porcentaje,
                    COALESCE(pc.estado, 'No iniciado') AS estado,
                    pc.ultima_actividad
                FROM cursos c
                LEFT JOIN progreso_curso pc
                    ON pc.id_curso = c.id_curso
                    AND pc.id_usuario = ?
                WHERE c.estado = 'Activo'
                ORDER BY pc.ultima_actividad DESC, c.nombre ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idUsuario]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerCurso(
        int $idUsuario,
        int $idCurso
    ): ?array {
        $sql = "SELECT *
                FROM progreso_curso
                WHERE id_usuario = ?
                AND id_curso = ?";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            $idUsuario,
            $idCurso
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function obtenerTemasPorUsuario(
        int $idUsuario
    ): array {
        $sql = "SELECT
                    t.id_tema,
                    t.nombre AS tema,
                    t.id_modulo,
                    m.nombre AS modulo,
                    m.id_curso,
                    c.nombre AS curso,
                    COALESCE(pt.intentos, 0) AS intentos,
                    COALESCE(pt.promedio, 0) AS promedio,
                    COALESCE(pt.porcentaje, 0) AS porcentaje,
                    COALESCE(pt.estado, 'No iniciado') AS estado,
                    pt.ultima_actualizacion
                FROM temas t
                INNER JOIN modulos m
                    ON m.id_modulo = t.id_modulo
                INNER JOIN cursos c
                    ON c.id_curso = m.id_curso
                LEFT JOIN progreso_tema pt
                    ON pt.id_tema = t.id_tema
                    AND pt.id_usuario = ?
                WHERE t.estado = 'Activo'
                    AND m.estado = 'Activo'
                    AND c.estado = 'Activo'
                ORDER BY c.nombre ASC, m.orden ASC, t.orden ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idUsuario]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerTema(
        int $idUsuario,
        int $idTema
    ): ?array {
        $sql = "SELECT *
                FROM progreso_tema
                WHERE id_usuario = ?
                AND id_tema = ?";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            $idUsuario,
            $idTema
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Devuelve el tema y curso asociados a una actividad activa.
     */
    public function obtenerUbicacionActividad(int $idActividad): ?array
    {
        $sql = "SELECT t.id_tema, m.id_curso
                FROM actividades a
                INNER JOIN temas t ON t.id_tema = a.id_tema
                INNER JOIN modulos m ON m.id_modulo = t.id_modulo
                INNER JOIN cursos c ON c.id_curso = m.id_curso
                WHERE a.id_actividad = ?
                    AND a.estado = 'Activo'
                    AND t.estado = 'Activo'
                    AND m.estado = 'Activo'
                    AND c.estado = 'Activo'";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idActividad]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Calcula el avance de un tema usando intentos finalizados guardados.
     * Cada actividad cuenta una sola vez para el porcentaje, aunque tenga reintentos.
     */
    public function calcularDatosTema(int $idUsuario, int $idTema): ?array
    {
        $sql = "SELECT
                    COUNT(DISTINCT a.id_actividad) AS actividades_totales,
                    COUNT(DISTINCT CASE
                        WHEN r.id_resultado IS NOT NULL THEN a.id_actividad
                    END) AS actividades_realizadas,
                    COUNT(r.id_resultado) AS intentos,
                    COALESCE(AVG(r.porcentaje), 0) AS promedio,
                    (
                        SELECT r2.estado
                        FROM resultados r2
                        INNER JOIN actividades a2
                            ON a2.id_actividad = r2.id_actividad
                        WHERE r2.id_usuario = ?
                            AND a2.id_tema = t.id_tema
                            AND a2.estado = 'Activo'
                        ORDER BY r2.fecha DESC, r2.id_resultado DESC
                        LIMIT 1
                    ) AS ultimo_estado
                FROM temas t
                INNER JOIN modulos m
                    ON m.id_modulo = t.id_modulo
                    AND m.estado = 'Activo'
                LEFT JOIN actividades a
                    ON a.id_tema = t.id_tema
                    AND a.estado = 'Activo'
                LEFT JOIN resultados r
                    ON r.id_actividad = a.id_actividad
                    AND r.id_usuario = ?
                WHERE t.id_tema = ?
                    AND t.estado = 'Activo'
                GROUP BY t.id_tema";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idUsuario, $idUsuario, $idTema]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Calcula el avance del curso con actividades activas de sus temas activos.
     */
    public function calcularDatosCurso(int $idUsuario, int $idCurso): ?array
    {
        $sql = "SELECT
                    COUNT(DISTINCT a.id_actividad) AS actividades_totales,
                    COUNT(DISTINCT CASE
                        WHEN r.id_resultado IS NOT NULL THEN a.id_actividad
                    END) AS actividades_realizadas
                FROM cursos c
                LEFT JOIN modulos m
                    ON m.id_curso = c.id_curso
                    AND m.estado = 'Activo'
                LEFT JOIN temas t
                    ON t.id_modulo = m.id_modulo
                    AND t.estado = 'Activo'
                LEFT JOIN actividades a
                    ON a.id_tema = t.id_tema
                    AND a.estado = 'Activo'
                LEFT JOIN resultados r
                    ON r.id_actividad = a.id_actividad
                    AND r.id_usuario = ?
                WHERE c.id_curso = ?
                    AND c.estado = 'Activo'
                GROUP BY c.id_curso";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idUsuario, $idCurso]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function guardarProgresoCurso(
        int $idUsuario,
        int $idCurso,
        float $porcentaje,
        string $estado
    ): bool {
        $sql = "INSERT INTO progreso_curso
                (
                    id_usuario,
                    id_curso,
                    porcentaje,
                    ultima_actividad,
                    estado
                )
                VALUES (?, ?, ?, NOW(), ?)
                ON DUPLICATE KEY UPDATE
                    porcentaje = VALUES(porcentaje),
                    ultima_actividad = NOW(),
                    estado = VALUES(estado)";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            $idUsuario,
            $idCurso,
            $porcentaje,
            $estado
        ]);
    }

    public function guardarProgresoTema(
        int $idUsuario,
        int $idTema,
        int $intentos,
        float $promedio,
        float $porcentaje,
        string $estado
    ): bool {
        $sql = "INSERT INTO progreso_tema
                (
                    id_usuario,
                    id_tema,
                    intentos,
                    promedio,
                    porcentaje,
                    estado
                )
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    intentos = VALUES(intentos),
                    promedio = VALUES(promedio),
                    porcentaje = VALUES(porcentaje),
                    estado = VALUES(estado),
                    ultima_actualizacion = CURRENT_TIMESTAMP";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            $idUsuario,
            $idTema,
            $intentos,
            $promedio,
            $porcentaje,
            $estado
        ]);
    }
}
