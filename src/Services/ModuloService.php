<?php
require_once __DIR__ . '/../Repositories/ModuloRepository.php';

class ModuloService
{
    private ModuloRepository $modulos;

    public function __construct(PDO $db)
    {
        $this->modulos = new ModuloRepository($db);
    }

    public function listarPorCurso(int $idCurso): array
    {
        return $idCurso > 0 ? $this->modulos->obtenerPorCurso($idCurso) : [];
    }
}
