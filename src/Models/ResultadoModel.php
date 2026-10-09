<?php

declare(strict_types=1);

require_once __DIR__ . '/../Repositories/ResultadoRepository.php';

class ResultadoModel
{
    private ResultadoRepository $repository;

    public function __construct(PDO|ResultadoRepository $dbOrRepository)
    {
        if ($dbOrRepository instanceof PDO) {
            $this->repository = new ResultadoRepository($dbOrRepository);
        } else {
            $this->repository = $dbOrRepository;
        }
    }

    public function obtenerPorId(int $id): ?array
    {
        return $this->repository->obtenerPorId($id);
    }

    public function obtenerPorUsuario(int $idUsuario): array
    {
        return $this->repository->obtenerPorUsuario($idUsuario);
    }

    public function obtenerPorActividad(int $idActividad): array
    {
        return $this->repository->obtenerPorActividad($idActividad);
    }

    public function obtenerTodos(): array
    {
        return $this->repository->obtenerTodos();
    }

    public function obtenerDetalle(int $idResultado, ?int $idUsuario = null): ?array
    {
        return $this->repository->obtenerDetalle($idResultado, $idUsuario);
    }

    public function existePorUsuarioYActividad(int $idUsuario, int $idActividad): bool
    {
        return $this->repository->existePorUsuarioYActividad($idUsuario, $idActividad);
    }

    public function crearResultado(
        int $idUsuario,
        int $idActividad,
        int $puntajeObtenido,
        int $puntajeTotal,
        int $totalPreguntas,
        int $respuestasCorrectas,
        float $porcentaje,
        string $estado
    ): int {
        return $this->repository->crearResultado(
            $idUsuario,
            $idActividad,
            $puntajeObtenido,
            $puntajeTotal,
            $totalPreguntas,
            $respuestasCorrectas,
            $porcentaje,
            $estado
        );
    }

    public function guardarRespuestasEstudiante(int $idResultado, array $respuestas): void
    {
        foreach ($respuestas as $resp) {
            $idPregunta = (int) ($resp['id_pregunta'] ?? 0);
            if ($idPregunta <= 0) {
                continue;
            }

            $idOpcion = isset($resp['id_opcion']) ? (int) $resp['id_opcion'] : null;
            $texto = $resp['respuesta_texto'] ?? null;
            $correcta = (bool) ($resp['correcta'] ?? $resp['es_correcta'] ?? false);
            $puntos = $correcta ? (int) ($resp['puntos'] ?? $resp['puntos_obtenidos'] ?? 1) : 0;

            $this->repository->guardarRespuestaEstudiante(
                $idResultado,
                $idPregunta,
                $idOpcion,
                $texto,
                $correcta,
                $puntos
            );
        }
    }

    public function obtenerTemaPorActividad(int $idActividad): int
    {
        return $this->repository->obtenerIdTemaPorActividad($idActividad);
    }
}