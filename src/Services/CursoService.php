<?php

require_once __DIR__ . '/../Models/CursoModel.php';

class CursoService
{
    private CursoModel $modelo;

    public function __construct(?CursoModel $modelo = null)
    {
        $this->modelo = $modelo ?? new CursoModel();
    }

    public function obtenerCursosActivos(): array
    {
        return $this->modelo->obtenerTodos(true);
    }

    public function buscarCurso(int $idCurso): ?array
    {
        if ($idCurso < 1) {
            return null;
        }

        return $this->modelo->obtenerPorId($idCurso);
    }
}
