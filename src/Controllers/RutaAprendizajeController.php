<?php

declare(strict_types=1);

require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../Models/RutaAprendizajeModel.php';

class RutaAprendizajeController
{
    private RutaAprendizajeModel $modelo;

    public function __construct(
        ?RutaAprendizajeModel $modelo = null,
        ?PDO $db = null
    ) {
        if ($modelo !== null) {
            $this->modelo = $modelo;
        } else {
            $conexion = $db ?? (new Database())->connect();
            $this->modelo = new RutaAprendizajeModel($conexion);
        }
    }

    public function listarParaGestion(): array
    {
        return $this->modelo->listar(false);
    }

    public function listarActivas(): array
    {
        return $this->modelo->listar(true);
    }

    public function obtenerPorId(int $idRuta): ?array
    {
        $this->validarId($idRuta, 'ruta');

        return $this->modelo->obtenerPorId($idRuta);
    }

    public function obtenerDetalleParaEstudiante(int $idRuta): ?array
    {
        $ruta = $this->obtenerPorId($idRuta);

        if ($ruta === null || $ruta['estado'] !== 'Activo') {
            return null;
        }

        $ruta['cursos'] = $this->modelo->obtenerCursos(
            $idRuta,
            true
        );

        return $ruta;
    }

    public function crear(array $datos): int
    {
        return $this->modelo->crear($datos);
    }

    public function actualizar(int $idRuta, array $datos): bool
    {
        $this->validarId($idRuta, 'ruta');

        return $this->modelo->actualizar($idRuta, $datos);
    }

    public function cambiarEstado(int $idRuta, string $estado): bool
    {
        $this->validarId($idRuta, 'ruta');

        return $this->modelo->cambiarEstado($idRuta, $estado);
    }

    public function obtenerCursos(int $idRuta): array
    {
        $this->validarId($idRuta, 'ruta');

        return $this->modelo->obtenerCursos($idRuta);
    }

    public function obtenerCursosDisponibles(int $idRuta): array
    {
        $this->validarId($idRuta, 'ruta');

        return $this->modelo->obtenerCursosDisponibles($idRuta);
    }

    public function agregarCurso(int $idRuta, array $datos): bool
    {
        $this->validarId($idRuta, 'ruta');

        $idCurso = $this->enteroPositivo(
            $datos['id_curso'] ?? null,
            'El curso'
        );

        $orden = $this->enteroPositivo(
            $datos['orden'] ?? null,
            'El orden'
        );

        return $this->modelo->agregarCurso(
            $idRuta,
            $idCurso,
            $orden
        );
    }

    public function cambiarOrden(
        int $idRuta,
        int $idCurso,
        array $datos
    ): bool {
        $this->validarId($idRuta, 'ruta');
        $this->validarId($idCurso, 'curso');

        $orden = $this->enteroPositivo(
            $datos['orden'] ?? null,
            'El orden'
        );

        return $this->modelo->cambiarOrden(
            $idRuta,
            $idCurso,
            $orden
        );
    }

    public function quitarCurso(int $idRuta, int $idCurso): bool
    {
        $this->validarId($idRuta, 'ruta');
        $this->validarId($idCurso, 'curso');

        return $this->modelo->quitarCurso($idRuta, $idCurso);
    }

    private function validarId(int $id, string $entidad): void
    {
        if ($id < 1) {
            throw new InvalidArgumentException(
                "El ID de {$entidad} debe ser mayor que cero."
            );
        }
    }

    private function enteroPositivo($valor, string $campo): int
    {
        if (!is_string($valor) && !is_int($valor)) {
            throw new InvalidArgumentException(
                "{$campo} debe ser un entero mayor que cero."
            );
        }

        $numero = filter_var(
            $valor,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($numero === false) {
            throw new InvalidArgumentException(
                "{$campo} debe ser un entero mayor que cero."
            );
        }

        return $numero;
    }
}