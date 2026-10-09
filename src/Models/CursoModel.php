<?php

require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/ValidadorDatos.php';

class CursoModel
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? (new Database())->connect();
    }

    public function obtenerTodos(bool $soloActivos = false): array
    {
        $sql = "SELECT c.*,
                       (SELECT COUNT(*)
                        FROM modulos m
                        WHERE m.id_curso = c.id_curso) AS total_modulos
                FROM cursos c";

        if ($soloActivos) {
            $sql .= " WHERE c.estado = 'Activo'";
        }

        $sql .= " ORDER BY c.nombre ASC";

        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(int $idCurso): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM cursos WHERE id_curso = ?"
        );
        $stmt->execute([$idCurso]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function crear(array $datos): int
    {
        $nombre = $this->validarNombre($datos['nombre'] ?? '');
        $descripcion = ValidadorDatos::texto(
            $datos['descripcion'] ?? null,
            'La descripción',
            true
        );

        if ($this->nombreRepetido($nombre)) {
            throw new InvalidArgumentException('Ya existe un curso con ese nombre.');
        }

        $stmt = $this->db->prepare(
            "INSERT INTO cursos (nombre, descripcion)
             VALUES (?, ?)"
        );
        $stmt->execute([$nombre, $descripcion]);

        return (int) $this->db->lastInsertId();
    }

    public function actualizar(int $idCurso, array $datos): bool
    {
        if (!$this->obtenerPorId($idCurso)) {
            throw new InvalidArgumentException('El curso no existe.');
        }

        $nombre = $this->validarNombre($datos['nombre'] ?? '');
        $descripcion = ValidadorDatos::texto(
            $datos['descripcion'] ?? null,
            'La descripción',
            true
        );

        if ($this->nombreRepetido($nombre, $idCurso)) {
            throw new InvalidArgumentException('Ya existe otro curso con ese nombre.');
        }

        $stmt = $this->db->prepare(
            "UPDATE cursos
             SET nombre = ?, descripcion = ?
             WHERE id_curso = ?"
        );

        return $stmt->execute([$nombre, $descripcion, $idCurso]);
    }

    public function cambiarEstado(int $idCurso, string $estado): bool
    {
        if (!$this->obtenerPorId($idCurso)) {
            throw new InvalidArgumentException('El curso no existe.');
        }

        $estado = ValidadorDatos::estado($estado);

        $stmt = $this->db->prepare(
            "UPDATE cursos SET estado = ? WHERE id_curso = ?"
        );

        return $stmt->execute([$estado, $idCurso]);
    }

    private function validarNombre($valor): string
    {
        $nombre = ValidadorDatos::texto($valor, 'El nombre');

        if (mb_strlen($nombre, 'UTF-8') > 100) {
            throw new InvalidArgumentException(
                'El nombre no puede pasar de 100 caracteres.'
            );
        }

        return $nombre;
    }

    private function nombreRepetido(string $nombre, int $idIgnorar = 0): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1
             FROM cursos
             WHERE nombre = ? AND id_curso <> ?
             LIMIT 1"
        );
        $stmt->execute([$nombre, $idIgnorar]);

        return (bool) $stmt->fetchColumn();
    }
}
