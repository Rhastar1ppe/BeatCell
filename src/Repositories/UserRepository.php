<?php

class UserRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function obtenerTodos(): array
    {
        $sql = "SELECT
                    id_usuario,
                    codigo,
                    dni,
                    nombres,
                    apellidos,
                    correo,
                    rol,
                    estado,
                    fecha_registro
                FROM usuarios
                ORDER BY id_usuario DESC";

        return $this->db
            ->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(
        int $idUsuario
    ): ?array {
        $sql = "SELECT *
                FROM usuarios
                WHERE id_usuario = ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idUsuario]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function obtenerPorCorreo(
        string $correo
    ): ?array {
        $sql = "SELECT *
                FROM usuarios
                WHERE correo = ?
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$correo]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function obtenerPorCodigo(
        string $codigo
    ): ?array {
        $sql = "SELECT *
                FROM usuarios
                WHERE codigo = ?
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$codigo]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function crear(
        string $codigo,
        ?string $dni,
        string $nombres,
        string $apellidos,
        string $correo,
        string $password,
        string $rol = 'Estudiante'
    ): bool {
        $passwordHash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $sql = "INSERT INTO usuarios
                (
                    codigo,
                    dni,
                    nombres,
                    apellidos,
                    correo,
                    password,
                    rol
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            $codigo,
            $dni,
            $nombres,
            $apellidos,
            $correo,
            $passwordHash,
            $rol
        ]);
    }

    public function actualizar(
        int $idUsuario,
        string $codigo,
        ?string $dni,
        string $nombres,
        string $apellidos,
        string $correo,
        string $rol
    ): bool {
        $sql = "UPDATE usuarios
                SET
                    codigo = ?,
                    dni = ?,
                    nombres = ?,
                    apellidos = ?,
                    correo = ?,
                    rol = ?
                WHERE id_usuario = ?";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            $codigo,
            $dni,
            $nombres,
            $apellidos,
            $correo,
            $rol,
            $idUsuario
        ]);
    }

    public function actualizarPassword(
        int $idUsuario,
        string $password
    ): bool {
        $passwordHash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $sql = "UPDATE usuarios
                SET password = ?
                WHERE id_usuario = ?";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            $passwordHash,
            $idUsuario
        ]);
    }

    public function cambiarEstado(
        int $idUsuario,
        string $estado
    ): bool {
        $sql = "UPDATE usuarios
                SET estado = ?
                WHERE id_usuario = ?";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            $estado,
            $idUsuario
        ]);
    }

    public function verificarPassword(
        string $password,
        string $passwordHash
    ): bool {
        return password_verify(
            $password,
            $passwordHash
        );
    }
}