<?php

declare(strict_types=1);

require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../Models/ResultadoModel.php';
require_once __DIR__ . '/../Services/ProgresoService.php';

class ResultadoController
{
    private ResultadoModel $modelo;
    private ?ProgresoService $progreso;
    private ?PDO $db;

    public function __construct(
        ?ResultadoModel $modelo = null,
        ?ProgresoService $progreso = null,
        ?PDO $db = null
    ) {
        if ($modelo === null) {
            $this->db = $db ?? (new Database())->connect();
            $this->modelo = new ResultadoModel($this->db);
            $this->progreso = $progreso ?? new ProgresoService($this->db);
        } else {
            $this->modelo = $modelo;
            $this->db = $db;
            $this->progreso = $progreso;
            if ($this->progreso === null && $this->db !== null) {
                $this->progreso = new ProgresoService($this->db);
            }
        }
    }

    public function obtenerPorId(int $idResultado): ?array
    {
        return $this->modelo->obtenerPorId($idResultado);
    }

    public function listarPorUsuario(int $idUsuario): array
    {
        return $this->modelo->obtenerPorUsuario($idUsuario);
    }

    public function listarPorActividad(int $idActividad): array
    {
        return $this->modelo->obtenerPorActividad($idActividad);
    }

    public function listarParaGestion(): array
    {
        return $this->modelo->obtenerTodos();
    }

    public function obtenerDetalleParaGestion(int $idResultado): ?array
    {
        return $this->modelo->obtenerDetalle($idResultado);
    }

    public function obtenerDetalleParaUsuario(int $idResultado, int $idUsuario): ?array
    {
        if ($idResultado < 1 || $idUsuario < 1) {
            return null;
        }

        return $this->modelo->obtenerDetalle($idResultado, $idUsuario);
    }

    public function calcularResultado(array $respuestas, array $config = []): array
    {
        $puntajeObtenido = 0;
        $puntajeTotalIngresado = (int) ($config['puntaje_total'] ?? 0);
        $usarTotalAutomatico = ($puntajeTotalIngresado <= 0);
        $puntajeTotal = $usarTotalAutomatico ? 0 : $puntajeTotalIngresado;

        $totalPreguntas = isset($config['total_preguntas'])
            ? (int) $config['total_preguntas']
            : count($respuestas);

        $respuestasCorrectas = 0;

        foreach ($respuestas as $respuesta) {
            $correcta = (bool) ($respuesta['correcta'] ?? $respuesta['es_correcta'] ?? false);
            $puntosPregunta = (int) ($respuesta['puntos'] ?? $respuesta['puntos_maximos'] ?? 1);

            if ($usarTotalAutomatico) {
                $puntajeTotal += $puntosPregunta;
            }

            if ($correcta) {
                $respuestasCorrectas++;
                $puntajeObtenido += $puntosPregunta;
            }
        }

        $porcentaje = 0.0;
        if ($puntajeTotal > 0) {
            $porcentaje = round(($puntajeObtenido / $puntajeTotal) * 100, 2);
        }

        $umbralAprobacion = (float) ($config['umbral_aprobacion'] ?? 70.0);
        $estado = ($porcentaje >= $umbralAprobacion) ? 'Aprobado' : 'Desaprobado';

        return [
            'puntaje_obtenido'    => $puntajeObtenido,
            'puntaje_total'       => $puntajeTotal,
            'total_preguntas'     => max(0, $totalPreguntas),
            'respuestas_correctas'=> $respuestasCorrectas,
            'porcentaje'          => $porcentaje,
            'estado'              => $estado,
        ];
    }

    public function registrarResultado(
        int $idUsuario,
        int $idActividad,
        array $respuestas,
        array $config = []
    ): int {
        if ($idUsuario < 1) {
            throw new InvalidArgumentException('El ID de usuario debe ser mayor que cero.');
        }

        if ($idActividad < 1) {
            throw new InvalidArgumentException('El ID de actividad debe ser mayor que cero.');
        }

        // 1. Bloqueo previo de 2.º intento
        if ($this->modelo->existePorUsuarioYActividad($idUsuario, $idActividad)) {
            throw new DomainException('El usuario ya ha realizado esta actividad. No se permiten intentos adicionales.');
        }

        // 2. Cálculo de puntuación y nota
        $resultado = $this->calcularResultado($respuestas, $config);

        $iniciaTransaccion = $this->db !== null && !$this->db->inTransaction();
        if ($iniciaTransaccion) {
            $this->db->beginTransaction();
        }

        try {
            // Re-verificación interna por concurrencia
            if ($this->modelo->existePorUsuarioYActividad($idUsuario, $idActividad)) {
                throw new DomainException('El usuario ya ha realizado esta actividad.');
            }

            // 3. Insertar el resultado general
            $idResultado = $this->modelo->crearResultado(
                $idUsuario,
                $idActividad,
                $resultado['puntaje_obtenido'],
                $resultado['puntaje_total'],
                $resultado['total_preguntas'],
                $resultado['respuestas_correctas'],
                $resultado['porcentaje'],
                $resultado['estado']
            );

            // 4. Registrar detalle de respuestas
            if (!empty($respuestas)) {
                $this->modelo->guardarRespuestasEstudiante($idResultado, $respuestas);
            }

            // 5. Actualizar progresos de tema y curso mediante ProgresoService
            if ($this->progreso !== null) {
                $this->progreso->actualizarTrasIntentoFinalizado($idUsuario, $idActividad);
            }

            if ($iniciaTransaccion) {
                $this->db->commit();
            }

            return $idResultado;

        } catch (PDOException $e) {
            if ($iniciaTransaccion && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if ($e->getCode() === '23000') {
                throw new DomainException('Ya existe un intento registrado para esta actividad.');
            }
            throw $e;

        } catch (Throwable $e) {
            if ($iniciaTransaccion && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}