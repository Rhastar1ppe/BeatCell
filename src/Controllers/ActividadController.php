<?php
require_once __DIR__ . '/../Models/ActividadModel.php';

// Carga ActividadModel para poder usar sus métodos.
class ActividadController
{
    // Guarda el modelo que realizará las consultas a la base de datos.
    private ActividadModel $modelo;

    // Recibe un modelo existente o crea uno si no se le pasa ninguno.
    public function __construct(?ActividadModel $modelo = null)
    {
        $this->modelo = $modelo ?? new ActividadModel();
    }

    // Pide al modelo todas las actividades (o solo las activas) y devuelve la lista.
    public function listar(bool $soloActivas = false): array
    {
        return $this->modelo->obtenerTodos($soloActivas);
    }

    // Busca una actividad por id; devuelve null si no existe.
    public function obtenerPorId(int $idActividad): ?array
    {
        return $this->modelo->obtenerPorId($idActividad);
    }

    // Lista las actividades de un tema (útil para la vista del estudiante).
    public function listarPorTema(int $idTema, bool $soloActivas = false): array
    {
        return $this->modelo->obtenerPorTema($idTema, $soloActivas);
    }

    // Recibe los datos de una actividad, los envía al modelo y devuelve el id creado.
    public function crear(array $datos): int
    {
        return $this->modelo->crear($datos);
    }

    // Recibe el id y los campos nuevos; el modelo valida y guarda los cambios.
    public function actualizar(int $idActividad, array $datos): bool
    {
        return $this->modelo->actualizar($idActividad, $datos);
    }

    // Cambia el estado; el modelo solo acepta "Activo" o "Inactivo".
    public function cambiarEstado(int $idActividad, string $estado): bool
    {
        return $this->modelo->cambiarEstado($idActividad, $estado);
    }
}