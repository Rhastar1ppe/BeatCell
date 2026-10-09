<?php

declare(strict_types=1);

class RutaAprendizajeModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function listar(bool $soloActivas = false): array
    {
        $sql = "SELECT r.*,
                       (SELECT COUNT(*)
                        FROM ruta_cursos rc
                        WHERE rc.id_ruta = r.id_ruta) AS total_cursos
                FROM rutas_aprendizaje r";

        if ($soloActivas) {
            $sql .= " WHERE r.estado = 'Activo'";
        }

        $sql .= " ORDER BY r.nombre, r.id_ruta";

        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(int $idRuta): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM rutas_aprendizaje WHERE id_ruta = ?"
        );
        $stmt->execute([$idRuta]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function crear(array $datos): int
    {
        [$nombre, $descripcion, $nivel] = $this->validarDatos($datos);

        $stmt = $this->db->prepare(
            "INSERT INTO rutas_aprendizaje
                (nombre, descripcion, nivel)
             VALUES (?, ?, ?)"
        );
        $stmt->execute([$nombre, $descripcion, $nivel]);

        return (int) $this->db->lastInsertId();
    }

    public function actualizar(int $idRuta, array $datos): bool
    {
        $this->exigirRuta($idRuta);
        [$nombre, $descripcion, $nivel] = $this->validarDatos($datos);

        $stmt = $this->db->prepare(
            "UPDATE rutas_aprendizaje
             SET nombre = ?, descripcion = ?, nivel = ?
             WHERE id_ruta = ?"
        );

        return $stmt->execute([
            $nombre,
            $descripcion,
            $nivel,
            $idRuta
        ]);
    }

    public function cambiarEstado(int $idRuta, string $estado): bool
    {
        $this->exigirRuta($idRuta);

        if (!in_array($estado, ['Activo', 'Inactivo'], true)) {
            throw new InvalidArgumentException('El estado no es válido.');
        }

        $stmt = $this->db->prepare(
            "UPDATE rutas_aprendizaje
             SET estado = ?
             WHERE id_ruta = ?"
        );

        return $stmt->execute([$estado, $idRuta]);
    }

    public function obtenerCursos(
        int $idRuta,
        bool $soloActivos = false
    ): array {
        $this->exigirRuta($idRuta);

        $sql = "SELECT c.*, rc.orden
                FROM ruta_cursos rc
                INNER JOIN cursos c ON c.id_curso = rc.id_curso
                WHERE rc.id_ruta = ?";

        if ($soloActivos) {
            $sql .= " AND c.estado = 'Activo'";
        }

        $sql .= " ORDER BY rc.orden, c.id_curso";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idRuta]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerCursosDisponibles(int $idRuta): array
    {
        $this->exigirRuta($idRuta);

        $stmt = $this->db->prepare(
            "SELECT c.*
             FROM cursos c
             WHERE c.estado = 'Activo'
               AND NOT EXISTS (
                   SELECT 1
                   FROM ruta_cursos rc
                   WHERE rc.id_ruta = ?
                     AND rc.id_curso = c.id_curso
               )
             ORDER BY c.nombre"
        );
        $stmt->execute([$idRuta]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function agregarCurso(
        int $idRuta,
        int $idCurso,
        int $orden
    ): bool {
        $this->exigirRuta($idRuta);
        $this->validarOrden($orden);

        $stmtCurso = $this->db->prepare(
            "SELECT 1
             FROM cursos
             WHERE id_curso = ? AND estado = 'Activo'"
        );
        $stmtCurso->execute([$idCurso]);

        if ($idCurso < 1 || $stmtCurso->fetchColumn() === false) {
            throw new InvalidArgumentException(
                'Selecciona un curso activo válido.'
            );
        }

        $stmt = $this->db->prepare(
            "INSERT INTO ruta_cursos (id_ruta, id_curso, orden)
             VALUES (?, ?, ?)"
        );

        try {
            return $stmt->execute([$idRuta, $idCurso, $orden]);
        } catch (PDOException $ex) {
            if ((int) ($ex->errorInfo[1] ?? 0) === 1062) {
                throw new InvalidArgumentException(
                    'El curso ya está en la ruta o el orden está ocupado.'
                );
            }

            throw $ex;
        }
    }

    public function cambiarOrden(
        int $idRuta,
        int $idCurso,
        int $orden
    ): bool {
        $this->exigirRuta($idRuta);
        $this->validarOrden($orden);
        $this->exigirCursoEnRuta($idRuta, $idCurso);

        $stmt = $this->db->prepare(
            "UPDATE ruta_cursos
             SET orden = ?
             WHERE id_ruta = ? AND id_curso = ?"
        );

        try {
            return $stmt->execute([$orden, $idRuta, $idCurso]);
        } catch (PDOException $ex) {
            if ((int) ($ex->errorInfo[1] ?? 0) === 1062) {
                throw new InvalidArgumentException(
                    'Ese orden ya pertenece a otro curso de la ruta.'
                );
            }

            throw $ex;
        }
    }

    public function quitarCurso(int $idRuta, int $idCurso): bool
    {
        $this->exigirRuta($idRuta);
        $this->exigirCursoEnRuta($idRuta, $idCurso);

        $stmt = $this->db->prepare(
            "DELETE FROM ruta_cursos
             WHERE id_ruta = ? AND id_curso = ?"
        );

        return $stmt->execute([$idRuta, $idCurso]);
    }

    private function exigirRuta(int $idRuta): void
    {
        if ($idRuta < 1 || $this->obtenerPorId($idRuta) === null) {
            throw new InvalidArgumentException('La ruta no existe.');
        }
    }

    private function exigirCursoEnRuta(
        int $idRuta,
        int $idCurso
    ): void {
        $stmt = $this->db->prepare(
            "SELECT 1
             FROM ruta_cursos
             WHERE id_ruta = ? AND id_curso = ?"
        );
        $stmt->execute([$idRuta, $idCurso]);

        if ($idCurso < 1 || $stmt->fetchColumn() === false) {
            throw new InvalidArgumentException(
                'El curso no pertenece a esta ruta.'
            );
        }
    }

    private function validarOrden(int $orden): void
    {
        if ($orden < 1) {
            throw new InvalidArgumentException(
                'El orden debe ser mayor que cero.'
            );
        }
    }

    private function validarDatos(array $datos): array
    {
        $nombre = $datos['nombre'] ?? '';
        $descripcion = $datos['descripcion'] ?? '';
        $nivel = $datos['nivel'] ?? '';

        if (
            !is_string($nombre)
            || !is_string($descripcion)
            || !is_string($nivel)
        ) {
            throw new InvalidArgumentException(
                'Los datos de la ruta no tienen un formato válido.'
            );
        }

        $nombre = trim($nombre);
        $descripcion = trim($descripcion);
        $nivel = trim($nivel);

        if ($nombre === '' || mb_strlen($nombre, 'UTF-8') > 150) {
            throw new InvalidArgumentException(
                'El nombre es obligatorio y admite hasta 150 caracteres.'
            );
        }

        if (!in_array($nivel, ['Básico', 'Intermedio', 'Avanzado'], true)) {
            throw new InvalidArgumentException(
                'Selecciona Básico, Intermedio o Avanzado.'
            );
        }

        return [$nombre, $descripcion, $nivel];
    }
}