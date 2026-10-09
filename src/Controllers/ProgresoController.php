<?php

require_once dirname(__DIR__, 2) . '/database/database.php';
require_once __DIR__ . '/../Services/ProgresoService.php';

class ProgresoController
{
    private ProgresoService $servicio;

    public function __construct(?PDO $db = null)
    {
        $db = $db ?? (new Database())->connect();
        $this->servicio = new ProgresoService($db);
    }

    /** Devuelve los cursos del estudiante con sus temas y porcentajes. */
    public function obtenerPorUsuario(int $idUsuario): array
    {
        return $this->servicio->obtenerProgresoEstudiante($idUsuario);
    }

    /**
     * Punto de integración para Tarea 4: llamar cuando resultados ya contenga
     * el intento finalizado. Se debe pasar el usuario autenticado y la actividad.
     */
    public function actualizarTrasIntentoFinalizado(
        int $idUsuario,
        int $idActividad
    ): void {
        $this->servicio->actualizarTrasIntentoFinalizado($idUsuario, $idActividad);
    }

    /** Renderiza la pantalla de progreso para el usuario autenticado. */
    public function mostrar(int $idUsuario): void
    {
        $cursos = $this->obtenerPorUsuario($idUsuario);
        require dirname(__DIR__, 2) . '/pages/estudiante/progreso_vista.php';
    }
}
