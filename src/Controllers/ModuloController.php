<?php

require_once __DIR__ . '/../Models/ModuloModel.php';

class ModuloController
{
    private ModuloModel $modelo;

    public function __construct(?ModuloModel $modelo = null)
    {
        $this->modelo = $modelo ?? new ModuloModel();
    }

    public function listarPorCurso(int $idCurso): array
    {
        return $this->modelo->obtenerPorCurso($idCurso);
    }

    public function obtenerPorId(int $idModulo): ?array
    {
        return $this->modelo->obtenerPorId($idModulo);
    }

    public function crear(array $datos): int
    {
        return $this->modelo->crear($datos);
    }

    public function actualizar(int $idModulo, array $datos): bool
    {
        return $this->modelo->actualizar($idModulo, $datos);
    }

    public function cambiarEstado(int $idModulo, string $estado): bool
    {
        return $this->modelo->cambiarEstado($idModulo, $estado);
    }
    public function listarTodos(): array
    {
        return $this->modelo->obtenerTodos();
    }
}
