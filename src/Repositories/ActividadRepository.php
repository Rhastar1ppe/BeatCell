<?php

class ActividadRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function obtenerTodos(): array
    {
        $sql = "SELECT *
                FROM actividades
                ORDER BY id_actividad DESC";

        return $this->db
            ->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(int $idActividad): ?array
    {
        $sql = "SELECT *
                FROM actividades
                WHERE id_actividad = ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idActividad]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function obtenerPorTema(int $idTema): array
    {
        $sql = "SELECT *
                FROM actividades
                WHERE id_tema = ?
                AND estado = 'Activo'
                ORDER BY id_actividad ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idTema]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crear(
        int $idTema,
        string $titulo,
        ?string $descripcion,
        string $tipo,
        int $numeroPreguntas,
        ?int $tiempoLimite,
        ?float $puntajeMinimo
    ): bool {
        $sql = "INSERT INTO actividades
                (
                    id_tema,
                    titulo,
                    descripcion,
                    tipo,
                    numero_preguntas,
                    tiempo_limite,
                    puntaje_minimo
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            $idTema,
            $titulo,
            $descripcion,
            $tipo,
            $numeroPreguntas,
            $tiempoLimite,
            $puntajeMinimo
        ]);
    }

    public function actualizar(
        int $idActividad,
        int $idTema,
        string $titulo,
        ?string $descripcion,
        string $tipo,
        int $numeroPreguntas,
        ?int $tiempoLimite,
        ?float $puntajeMinimo
    ): bool {
        $sql = "UPDATE actividades
                SET
                    id_tema = ?,
                    titulo = ?,
                    descripcion = ?,
                    tipo = ?,
                    numero_preguntas = ?,
                    tiempo_limite = ?,
                    puntaje_minimo = ?
                WHERE id_actividad = ?";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            $idTema,
            $titulo,
            $descripcion,
            $tipo,
            $numeroPreguntas,
            $tiempoLimite,
            $puntajeMinimo,
            $idActividad
        ]);
    }

    public function cambiarEstado(
        int $idActividad,
        string $estado
    ): bool {
        $sql = "UPDATE actividades
                SET estado = ?
                WHERE id_actividad = ?";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            $estado,
            $idActividad
        ]);
    }
}