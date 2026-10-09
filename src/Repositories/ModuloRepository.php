<?php

class ModuloRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function obtenerTodos(): array
    {
        $sql = "SELECT *
                FROM modulos
                ORDER BY orden ASC";

        return $this->db
            ->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(int $idModulo): ?array
    {
        $sql = "SELECT *
                FROM modulos
                WHERE id_modulo = ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idModulo]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function obtenerPorCurso(int $idCurso): array
    {
        $sql = "SELECT *
                FROM modulos
                WHERE id_curso = ?
                ORDER BY orden ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idCurso]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crear(
        int $idCurso,
        string $nombre,
        ?string $descripcion,
        int $orden
    ): bool {
        $sql = "INSERT INTO modulos
                (
                    id_curso,
                    nombre,
                    descripcion,
                    orden
                )
                VALUES (?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            $idCurso,
            $nombre,
            $descripcion,
            $orden
        ]);
    }

    public function actualizar(
        int $idModulo,
        int $idCurso,
        string $nombre,
        ?string $descripcion,
        int $orden
    ): bool {
        $sql = "UPDATE modulos
                SET
                    id_curso = ?,
                    nombre = ?,
                    descripcion = ?,
                    orden = ?
                WHERE id_modulo = ?";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            $idCurso,
            $nombre,
            $descripcion,
            $orden,
            $idModulo
        ]);
    }

    public function eliminar(int $idModulo): bool
    {
        $sql = "DELETE FROM modulos
                WHERE id_modulo = ?";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([$idModulo]);
    }
}