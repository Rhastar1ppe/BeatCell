<?php
require_once __DIR__ . '/../Models/ActividadModel.php';

class ActividadService
{
    private ActividadModel $modelo;

    public function __construct(?ActividadModel $modelo = null)
    {
        $this->modelo = $modelo ?? new ActividadModel();
    }

    public function listarActivas(): array
    {
        return $this->modelo->obtenerTodos(true);
    }

    public function obtener(int $id): ?array
    {
        return $id > 0 ? $this->modelo->obtenerPorId($id) : null;
    }
}
