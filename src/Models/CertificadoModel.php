<?php

class CertificadoModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function obtenerPorId(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM certificados WHERE id_certificado = ?"
        );

        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function obtenerPorUsuario(int $idUsuario): array
    {
        $sql = "SELECT c.*, cu.nombre AS curso
                FROM certificados c
                INNER JOIN cursos cu ON cu.id_curso = c.id_curso
                WHERE c.id_usuario = ?
                ORDER BY c.fecha_emision DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idUsuario]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crear(
        int $idUsuario,
        int $idCurso,
        string $codigo,
        string $archivo
    ): bool {
        $sql = "INSERT INTO certificados
                (id_usuario, id_curso, codigo_certificado, archivo)
                VALUES (?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            $idUsuario,
            $idCurso,
            $codigo,
            $archivo
        ]);
    }
}