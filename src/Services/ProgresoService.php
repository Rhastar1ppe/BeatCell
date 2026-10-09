<?php

require_once __DIR__ . '/../Repositories/ProgresoRepository.php';

class ProgresoService
{
    private PDO $db;
    private ProgresoRepository $repositorio;

    public function __construct(PDO $db, ?ProgresoRepository $repositorio = null)
    {
        $this->db = $db;
        $this->repositorio = $repositorio ?? new ProgresoRepository($db);
    }

    /**
     * Devuelve cursos activos y sus temas activos con progreso, incluyendo 0 %.
     */
    public function obtenerProgresoEstudiante(int $idUsuario): array
    {
        $this->validarId($idUsuario, 'usuario');

        $cursos = $this->repositorio->obtenerCursosPorUsuario($idUsuario);
        $temas = $this->repositorio->obtenerTemasPorUsuario($idUsuario);
        $indiceCursos = [];

        foreach ($cursos as $indice => $curso) {
            $cursos[$indice]['temas'] = [];
            $indiceCursos[(int) $curso['id_curso']] = $indice;
        }

        foreach ($temas as $tema) {
            $idCurso = (int) $tema['id_curso'];
            if (!isset($indiceCursos[$idCurso])) {
                continue;
            }

            $cursos[$indiceCursos[$idCurso]]['temas'][] = $tema;
        }

        return $cursos;
    }

    /**
     * Recalcula tema y curso después de que el resultado del intento se haya
     * insertado en resultados. Puede ejecutarse dentro de la misma transacción
     * que guarda el intento si ambos usan esta misma conexión PDO.
     */
    public function actualizarTrasIntentoFinalizado(
        int $idUsuario,
        int $idActividad
    ): void {
        $this->validarId($idUsuario, 'usuario');
        $this->validarId($idActividad, 'actividad');

        $iniciaTransaccion = !$this->db->inTransaction();
        if ($iniciaTransaccion) {
            $this->db->beginTransaction();
        }

        try {
            $ubicacion = $this->repositorio->obtenerUbicacionActividad($idActividad);
            if ($ubicacion === null) {
                throw new DomainException('La actividad no existe o no está activa.');
            }

            $idTema = (int) $ubicacion['id_tema'];
            $idCurso = (int) $ubicacion['id_curso'];

            $datosTema = $this->repositorio->calcularDatosTema($idUsuario, $idTema);
            if ($datosTema === null) {
                throw new RuntimeException('No se pudo calcular el progreso del tema.');
            }

            $totalTema = (int) $datosTema['actividades_totales'];
            $realizadasTema = (int) $datosTema['actividades_realizadas'];
            $porcentajeTema = $this->porcentaje($realizadasTema, $totalTema);
            $intentos = (int) $datosTema['intentos'];
            $promedio = (float) $datosTema['promedio'];
            $estadoTema = $this->estadoTema(
                $intentos,
                $realizadasTema,
                $totalTema,
                $datosTema['ultimo_estado'] ?? null
            );

            if (!$this->repositorio->guardarProgresoTema(
                $idUsuario,
                $idTema,
                $intentos,
                round($promedio, 2),
                $porcentajeTema,
                $estadoTema
            )) {
                throw new RuntimeException('No se pudo guardar el progreso del tema.');
            }

            $datosCurso = $this->repositorio->calcularDatosCurso($idUsuario, $idCurso);
            if ($datosCurso === null) {
                throw new RuntimeException('No se pudo calcular el progreso del curso.');
            }

            $totalCurso = (int) $datosCurso['actividades_totales'];
            $realizadasCurso = (int) $datosCurso['actividades_realizadas'];
            $porcentajeCurso = $this->porcentaje($realizadasCurso, $totalCurso);
            $estadoCurso = $this->estadoCurso($realizadasCurso, $totalCurso);

            if (!$this->repositorio->guardarProgresoCurso(
                $idUsuario,
                $idCurso,
                $porcentajeCurso,
                $estadoCurso
            )) {
                throw new RuntimeException('No se pudo guardar el progreso del curso.');
            }

            if ($iniciaTransaccion) {
                $this->db->commit();
            }
        } catch (Throwable $e) {
            if ($iniciaTransaccion && $this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    private function porcentaje(int $realizadas, int $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return round(min(100, max(0, ($realizadas / $total) * 100)), 2);
    }

    private function estadoTema(
        int $intentos,
        int $realizadas,
        int $total,
        ?string $ultimoEstado
    ): string {
        if ($intentos === 0) {
            return 'No iniciado';
        }

        if ($ultimoEstado === 'Desaprobado') {
            return 'Reforzar';
        }

        if ($total > 0 && $realizadas >= $total) {
            return 'Completado';
        }

        return 'En progreso';
    }

    private function estadoCurso(int $realizadas, int $total): string
    {
        if ($realizadas === 0 || $total === 0) {
            return 'No iniciado';
        }

        return $realizadas >= $total ? 'Completado' : 'En progreso';
    }

    private function validarId(int $id, string $entidad): void
    {
        if ($id < 1) {
            throw new InvalidArgumentException('El id de ' . $entidad . ' debe ser mayor que cero.');
        }
    }
}
