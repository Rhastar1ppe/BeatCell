<?php

class TemaModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function listarPorModulo(int $idModulo): array
    {
        $sql = "SELECT
                                        id_tema, id_modulo, nombre, descripcion,
                                        material_apoyo, orden, estado
                                FROM temas
                                WHERE id_modulo = :id_modulo
                                    AND estado = 'Activo'
                                ORDER BY orden ASC";

        $stmt = $this->db->prepare($sql);
                $stmt->execute([':id_modulo' => $idModulo]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarTodos(): array
    {
        $sql = "SELECT
                    t.id_tema,
                    t.id_modulo,
                    t.nombre,
                    t.descripcion,
                    t.material_apoyo,
                    t.orden,
                    t.estado,
                    m.nombre AS modulo,
                    c.nombre AS curso
                FROM temas t
                INNER JOIN modulos m ON t.id_modulo = m.id_modulo
                INNER JOIN cursos c ON m.id_curso = c.id_curso
                ORDER BY c.nombre ASC, t.orden ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarPorId(int $idTema): ?array
    {
        $sql = "SELECT
                    t.id_tema,
                    t.id_modulo,
                    t.nombre,
                    t.descripcion,
                    t.material_apoyo,
                    t.orden,
                    t.estado,
                    m.nombre AS modulo,
                    c.nombre AS curso
                FROM temas t
                INNER JOIN modulos m ON t.id_modulo = m.id_modulo
                INNER JOIN cursos c ON m.id_curso = c.id_curso
                WHERE t.id_tema = :id_tema
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id_tema' => $idTema]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function crear(
        int $idModulo,
        string $nombre,
        ?string $descripcion = null,
        ?string $materialApoyo = null,
        int $orden = 1
    ): bool {
        $sql = "INSERT INTO temas (
                    id_modulo,
                    nombre,
                    descripcion,
                    material_apoyo,
                    orden,
                    estado
                ) VALUES (
                    :id_modulo,
                    :nombre,
                    :descripcion,
                    :material_apoyo,
                    :orden,
                    'Activo'
                )";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':id_modulo' => $idModulo,
            ':nombre' => $nombre,
            ':descripcion' => $descripcion,
            ':material_apoyo' => $materialApoyo,
            ':orden' => $orden
        ]);
    }

    public function actualizar(
        int $idTema,
        int $idModulo,
        string $nombre,
        ?string $descripcion = null,
        ?string $materialApoyo = null,
        int $orden = 1
    ): bool {
        $sql = "UPDATE temas
                SET
                    id_modulo = :id_modulo,
                    nombre = :nombre,
                    descripcion = :descripcion,
                    material_apoyo = :material_apoyo,
                    orden = :orden
                WHERE id_tema = :id_tema";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':id_tema' => $idTema,
            ':id_modulo' => $idModulo,
            ':nombre' => $nombre,
            ':descripcion' => $descripcion,
            ':material_apoyo' => $materialApoyo,
            ':orden' => $orden
        ]);
    }
    
    public function cambiarEstado(int $idTema, string $estado): bool
    {
        $sql = "UPDATE temas
                SET estado = :estado
                WHERE id_tema = :id_tema";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':estado' => $estado,
            ':id_tema' => $idTema
        ]);
    }
}