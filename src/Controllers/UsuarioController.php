<?php

require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../Models/UsuarioModel.php';

class UsuarioController
{
    private UsuarioModel $modelo;

    public function __construct(?UsuarioModel $modelo = null)
    {
        if ($modelo) {
            $this->modelo = $modelo;
            return;
        }

        $db = (new Database())->connect();
        $this->modelo = new UsuarioModel($db);
    }

    public function listar(): array
    {
        return $this->modelo->obtenerTodos();
    }

    public function obtenerPorId(int $id): ?array
    {
        return $this->modelo->obtenerPorId($id);
    }

    public function crear(array $datos): bool
    {
        $datos = $this->validar($datos, true);

        if ($this->modelo->obtenerPorCodigo($datos['codigo'])) {
            throw new InvalidArgumentException('El código ya está registrado.');
        }

        if ($this->modelo->obtenerPorCorreo($datos['correo'])) {
            throw new InvalidArgumentException('El correo ya está registrado.');
        }

        if ($datos['dni'] !== null && $this->modelo->obtenerPorDni($datos['dni'])) {
            throw new InvalidArgumentException('El DNI ya está registrado.');
        }

        return $this->modelo->crear(
            $datos['codigo'],
            $datos['dni'],
            $datos['nombres'],
            $datos['apellidos'],
            $datos['correo'],
            $datos['password'],
            $datos['rol']
        );
    }

    public function actualizar(int $id, array $datos): bool
    {
        if (!$this->modelo->obtenerPorId($id)) {
            throw new InvalidArgumentException('El usuario no existe.');
        }

        $datos = $this->validar($datos, false);
        $otroCodigo = $this->modelo->obtenerPorCodigo($datos['codigo']);
        $otroCorreo = $this->modelo->obtenerPorCorreo($datos['correo']);

        if ($otroCodigo && (int) $otroCodigo['id_usuario'] !== $id) {
            throw new InvalidArgumentException('El código ya está registrado.');
        }

        if ($otroCorreo && (int) $otroCorreo['id_usuario'] !== $id) {
            throw new InvalidArgumentException('El correo ya está registrado.');
        }

        if ($datos['dni'] !== null) {
            $otroDni = $this->modelo->obtenerPorDni($datos['dni']);
            if ($otroDni && (int) $otroDni['id_usuario'] !== $id) {
                throw new InvalidArgumentException('El DNI ya está registrado.');
            }
        }

        return $this->modelo->actualizar(
            $id,
            $datos['codigo'],
            $datos['dni'],
            $datos['nombres'],
            $datos['apellidos'],
            $datos['correo'],
            $datos['rol']
        );
    }

    public function cambiarPassword(int $id, string $password): bool
    {
        if (!$this->modelo->obtenerPorId($id)) {
            throw new InvalidArgumentException('El usuario no existe.');
        }

        $password = trim($password);
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('La contraseña debe tener al menos 8 caracteres.');
        }

        return $this->modelo->actualizarPassword($id, $password);
    }

    public function cambiarEstado(int $id, string $estado): bool
    {
        if (!in_array($estado, ['Activo', 'Inactivo'], true)) {
            throw new InvalidArgumentException('El estado no es válido.');
        }

        if (!$this->modelo->obtenerPorId($id)) {
            throw new InvalidArgumentException('El usuario no existe.');
        }

        return $this->modelo->cambiarEstado($id, $estado);
    }

    private function validar(array $datos, bool $conPassword): array
    {
        $codigo = trim((string) ($datos['codigo'] ?? ''));
        $dni = trim((string) ($datos['dni'] ?? ''));
        $dni = $dni === '' ? null : $dni;
        $nombres = trim((string) ($datos['nombres'] ?? ''));
        $apellidos = trim((string) ($datos['apellidos'] ?? ''));
        $correo = trim((string) ($datos['correo'] ?? ''));
        $rol = (string) ($datos['rol'] ?? 'Estudiante');
        $password = (string) ($datos['password'] ?? '');

        if ($codigo === '' || strlen($codigo) > 20) {
            throw new InvalidArgumentException('Ingresa un código válido.');
        }

        if ($dni !== '' && !preg_match('/^[0-9]{8}$/', $dni)) {
            throw new InvalidArgumentException('El DNI debe tener 8 números.');
        }

        if ($nombres === '' || mb_strlen($nombres) > 100) {
            throw new InvalidArgumentException('Ingresa nombres válidos.');
        }

        if ($apellidos === '' || mb_strlen($apellidos) > 100) {
            throw new InvalidArgumentException('Ingresa apellidos válidos.');
        }

        if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || mb_strlen($correo) > 200) {
            throw new InvalidArgumentException('Ingresa un correo válido.');
        }

        if (!in_array($rol, ['Estudiante', 'Docente', 'Administrador'], true)) {
            throw new InvalidArgumentException('El rol no es válido.');
        }

        if ($conPassword && strlen($password) < 8) {
            throw new InvalidArgumentException('La contraseña debe tener al menos 8 caracteres.');
        }

        return compact('codigo', 'dni', 'nombres', 'apellidos', 'correo', 'rol', 'password');
    }
}
