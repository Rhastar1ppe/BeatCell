<?php

// Carga el modelo que consulta y modifica los logros.
require_once __DIR__ . '/../Models/LogroModel.php';

class LogroController
{
    // Guarda el modelo que usará este controlador.
    private LogroModel $modelo;

    // Usa el modelo recibido o crea uno nuevo.
    public function __construct(?LogroModel $modelo = null)
    {
        $this->modelo = $modelo ?? new LogroModel();
    }

    // Pide la lista completa o solo los logros activos.
    public function listar(bool $soloActivos = false): array
    {
        return $this->modelo->obtenerTodos($soloActivos);
    }

    // Pide los logros obtenidos por un usuario.
    public function listarPorUsuario(int $idUsuario): array
    {
        return $this->modelo->obtenerPorUsuario($idUsuario);
    }
    // Envía los datos al modelo y devuelve el id del logro creado.
    public function crear(array $datos): int
   {
    return $this->modelo->crear($datos);
   }    
    // Envía el id y los campos nuevos al modelo.
    public function actualizar(int $idLogro, array $datos): bool
    {
        return $this->modelo->actualizar($idLogro, $datos);
    }
        // Activa o desactiva un logro sin borrarlo.
    public function cambiarEstado(int $idLogro, string $estado): bool
    {
        return $this->modelo->cambiarEstado($idLogro, $estado);
    }
        // Registra que el usuario obtuvo el logro; evita duplicarlo.
    public function otorgarAUsuario(int $idUsuario, int $idLogro): bool
    {
        return $this->modelo->otorgarAUsuario($idUsuario, $idLogro);
    }
}