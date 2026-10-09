<?php

class UsuarioModel
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

        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM usuarios WHERE id_usuario = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function obtenerPorCorreo(string $correo): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM usuarios WHERE correo = ? LIMIT 1');
        $stmt->execute([$correo]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function obtenerPorCodigo(string $codigo): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM usuarios WHERE codigo = ? LIMIT 1');
        $stmt->execute([$codigo]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function obtenerPorDni(string $dni): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM usuarios WHERE dni = ? LIMIT 1');
        $stmt->execute([$dni]);
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
        $sql = 'INSERT INTO usuarios
                (codigo, dni, nombres, apellidos, correo, password, rol)
                VALUES (?, ?, ?, ?, ?, ?, ?)';

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $codigo,
            $dni,
            $nombres,
            $apellidos,
            $correo,
            password_hash($password, PASSWORD_DEFAULT),
            $rol
        ]);
    }

    public function actualizar(
        int $id,
        string $codigo,
        ?string $dni,
        string $nombres,
        string $apellidos,
        string $correo,
        string $rol
    ): bool {
        $stmt = $this->db->prepare(
            'UPDATE usuarios
             SET codigo = ?, dni = ?, nombres = ?, apellidos = ?, correo = ?, rol = ?
             WHERE id_usuario = ?'
        );

        return $stmt->execute([
            $codigo,
            $dni,
            $nombres,
            $apellidos,
            $correo,
            $rol,
            $id
        ]);
    }

    public function actualizarPassword(int $id, string $password): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE usuarios SET password = ? WHERE id_usuario = ?'
        );

        return $stmt->execute([
            password_hash($password, PASSWORD_DEFAULT),
            $id
        ]);
    }

    public function cambiarEstado(int $id, string $estado): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE usuarios SET estado = ? WHERE id_usuario = ?'
        );

        return $stmt->execute([$estado, $id]);
    }

    public function verificarPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public function guardarTokenRecuperacion(string $correo, string $token, string $expira): bool
    {
        $tokenHash = hash('sha256', $token);
        $stmt = $this->db->prepare(
            'UPDATE usuarios
             SET password_reset_token = ?, password_reset_expira = ?
             WHERE correo = ?'
        );

        return $stmt->execute([$tokenHash, $expira, $correo]);
    }

    public function obtenerPorToken(string $token): ?array
    {
        $tokenHash = hash('sha256', $token);
        $stmt = $this->db->prepare(
            'SELECT * FROM usuarios WHERE password_reset_token = ? LIMIT 1'
        );

        $stmt->execute([$tokenHash]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function cambiarPasswordPorToken(string $token, string $nuevaPassword): bool
    {
        $tokenHash = hash('sha256', $token);
        $stmt = $this->db->prepare(
            'UPDATE usuarios
             SET password = ?, password_reset_token = NULL, password_reset_expira = NULL
             WHERE password_reset_token = ? AND password_reset_expira >= CURRENT_TIMESTAMP'
        );

        return $stmt->execute([
            password_hash($nuevaPassword, PASSWORD_DEFAULT),
            $tokenHash
        ]) && $stmt->rowCount() === 1;
    }
}