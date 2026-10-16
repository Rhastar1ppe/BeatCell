<?php

require_once __DIR__ . '/../Models/CursoModel.php';

class CursoController
{
    private CursoModel $modelo;

    public function __construct(?CursoModel $modelo = null)
    {
        $this->modelo = $modelo ?? new CursoModel();
    }

    public function listar(bool $soloActivos = false): array
    {
        return $this->modelo->obtenerTodos($soloActivos);
    }

    public function obtenerPorId(int $idCurso): ?array
    {
        return $this->modelo->obtenerPorId($idCurso);
    }

    public function crear(array $datos): int
    {
        return $this->modelo->crear($datos);
    }

    public function actualizar(int $idCurso, array $datos): bool
    {
        return $this->modelo->actualizar($idCurso, $datos);
    }

    public function cambiarEstado(int $idCurso, string $estado): bool
    {
        return $this->modelo->cambiarEstado($idCurso, $estado);
    }
}