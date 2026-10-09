<?php

class CursoRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function obtenerTodos(): array
    {
        $sql = "SELECT *
                FROM cursos
                ORDER BY id_curso DESC";

        return $this->db
            ->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerActivos(): array
    {
        $sql = "SELECT *
                FROM cursos
                WHERE estado = 'Activo'
                ORDER BY nombre ASC";

        return $this->db
            ->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(int $idCurso): ?array
    {
        $sql = "SELECT *
                FROM cursos
                WHERE id_curso = ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idCurso]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function crear(
        string $nombre,
        ?string $descripcion,
        ?string $imagen
    ): bool {
        $sql = "INSERT INTO cursos
                (
                    nombre,
                    descripcion,
                    imagen
                )
                VALUES (?, ?, ?)";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            $nombre,
            $descripcion,
            $imagen
        ]);
    }

    public function actualizar(
        int $idCurso,
        string $nombre,
        ?string $descripcion,
        ?string $imagen
    ): bool {
        $sql = "UPDATE cursos
                SET
                    nombre = ?,
                    descripcion = ?,
                    imagen = ?
                WHERE id_curso = ?";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            $nombre,
            $descripcion,
            $imagen,
            $idCurso
        ]);
    }

    public function cambiarEstado(
        int $idCurso,
        string $estado
    ): bool {
        $sql = "UPDATE cursos
                SET estado = ?
                WHERE id_curso = ?";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            $estado,
            $idCurso
        ]);
    }
}