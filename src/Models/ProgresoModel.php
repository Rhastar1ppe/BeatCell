<?php

class ProgresoModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function obtenerCursosPorUsuario(int $idUsuario): array
    {
        $sql = "SELECT pc.*, c.nombre AS curso
                FROM progreso_curso pc
                INNER JOIN cursos c ON c.id_curso = pc.id_curso
                WHERE pc.id_usuario = ?
                ORDER BY pc.ultima_actividad DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idUsuario]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerTemasPorUsuario(int $idUsuario): array
    {
        $sql = "SELECT pt.*, t.nombre AS tema
                FROM progreso_tema pt
                INNER JOIN temas t ON t.id_tema = pt.id_tema
                WHERE pt.id_usuario = ?
                ORDER BY pt.ultima_actualizacion DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idUsuario]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerCursoUsuario(
        int $idUsuario,
        int $idCurso
    ): ?array {
        $stmt = $this->db->prepare(
            "SELECT * FROM progreso_curso
             WHERE id_usuario = ? AND id_curso = ?"
        );

        $stmt->execute([$idUsuario, $idCurso]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function obtenerTemaUsuario(
        int $idUsuario,
        int $idTema
    ): ?array {
        $stmt = $this->db->prepare(
            "SELECT * FROM progreso_tema
             WHERE id_usuario = ? AND id_tema = ?"
        );

        $stmt->execute([$idUsuario, $idTema]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}