<<<<<<< HEAD
<?php
require_once __DIR__ . '/../Services/AuthService.php';

class LoginController
{
    private AuthService $servicio;

    public function __construct(PDO $db)
    {
        $this->servicio = new AuthService($db);
    }

    public function autenticar(string $correo, string $password): ?array
    {
        return $this->servicio->iniciarSesion($correo, $password);
    }
}
=======
<?php
require_once __DIR__ . '/../Services/AuthService.php';

class LoginController
{
    private AuthService $servicio;

    public function __construct(PDO $db)
    {
        $this->servicio = new AuthService($db);
    }

    public function autenticar(string $correo, string $password): ?array
    {
        return $this->servicio->iniciarSesion($correo, $password);
    }
}
>>>>>>> upstream/main
