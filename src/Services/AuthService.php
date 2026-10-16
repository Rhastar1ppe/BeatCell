<?php

require_once __DIR__ . '/../Models/UsuarioModel.php';

class AuthService
{
    private UsuarioModel $usuarios;

    public function __construct(PDO $db, ?UsuarioModel $usuarios = null)
    {
        $this->usuarios = $usuarios ?? new UsuarioModel($db);
    }

    /** Devuelve los datos del usuario solo cuando las credenciales y la cuenta son válidas. */
    public function iniciarSesion(string $correo, string $password): ?array
    {
        $correo = trim($correo);
        if ($correo === '' || $password === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $usuario = $this->usuarios->obtenerPorCorreo($correo);
        if ($usuario === null) {
            return null;
        }

        $hash = (string) ($usuario['password'] ?? '');
        if ($hash === '' || !$this->usuarios->verificarPassword($password, $hash)) {
            return null;
        }

        if (($usuario['estado'] ?? '') !== 'Activo') {
            return null;
        }

        // No devuelve el hash ni los datos temporales de recuperación a quien llama el servicio.
        unset($usuario['password'], $usuario['password_reset_token'], $usuario['password_reset_expira']);

        return $usuario;
    }
}
