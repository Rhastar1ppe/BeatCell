<<<<<<< HEAD
<?php

declare(strict_types=1);

class ResultadoRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function obtenerPorId(int $idResultado): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM resultados WHERE id_resultado = ?');
        $stmt->execute([$idResultado]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function obtenerPorUsuario(int $idUsuario): array
    {
        $sql = "SELECT r.*, a.titulo AS actividad, c.nombre AS curso
                FROM resultados r
                INNER JOIN actividades a ON a.id_actividad = r.id_actividad
                INNER JOIN temas t ON t.id_tema = a.id_tema
                INNER JOIN modulos m ON m.id_modulo = t.id_modulo
                INNER JOIN cursos c ON c.id_curso = m.id_curso
                WHERE r.id_usuario = ?
                ORDER BY r.fecha DESC, r.id_resultado DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idUsuario]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorActividad(int $idActividad): array
    {
        $sql = "SELECT r.*, u.nombres, u.apellidos
                FROM resultados r
                INNER JOIN usuarios u ON u.id_usuario = r.id_usuario
                WHERE r.id_actividad = ?
                ORDER BY r.fecha DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idActividad]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerTodos(): array
    {
        $sql = "SELECT r.*, u.nombres, u.apellidos, u.codigo,
                       a.titulo AS actividad, c.nombre AS curso
                FROM resultados r
                INNER JOIN usuarios u ON u.id_usuario = r.id_usuario
                INNER JOIN actividades a ON a.id_actividad = r.id_actividad
                INNER JOIN temas t ON t.id_tema = a.id_tema
                INNER JOIN modulos m ON m.id_modulo = t.id_modulo
                INNER JOIN cursos c ON c.id_curso = m.id_curso
                ORDER BY r.fecha DESC, r.id_resultado DESC";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerDetalle(int $idResultado, ?int $idUsuario = null): ?array
    {
        $sql = "SELECT r.*, u.nombres, u.apellidos, u.codigo,
                       a.titulo AS actividad, c.nombre AS curso, t.nombre AS tema
                FROM resultados r
                INNER JOIN usuarios u ON u.id_usuario = r.id_usuario
                INNER JOIN actividades a ON a.id_actividad = r.id_actividad
                INNER JOIN temas t ON t.id_tema = a.id_tema
                INNER JOIN modulos m ON m.id_modulo = t.id_modulo
                INNER JOIN cursos c ON c.id_curso = m.id_curso
                WHERE r.id_resultado = ?";
        $parametros = [$idResultado];
        if ($idUsuario !== null) {
            $sql .= ' AND r.id_usuario = ?';
            $parametros[] = $idUsuario;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($parametros);
        $detalle = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($detalle === false) {
            return null;
        }

        $stmtRespuestas = $this->db->prepare(
            "SELECT re.correcta, re.puntos_obtenidos, re.respuesta_texto,
                    re.opciones_seleccionadas,
                    p.pregunta, p.puntos, o.texto AS opcion_texto,
                    o.imagen AS opcion_imagen
             FROM respuestas_estudiante re
             INNER JOIN preguntas p ON p.id_pregunta = re.id_pregunta
             LEFT JOIN opciones_respuesta o ON o.id_opcion = re.id_opcion
             WHERE re.id_resultado = ?
             ORDER BY p.id_pregunta"
        );
        $stmtRespuestas->execute([$idResultado]);
        $detalle['respuestas'] = $stmtRespuestas->fetchAll(PDO::FETCH_ASSOC);
        foreach ($detalle['respuestas'] as &$respuesta) {
            $json = $respuesta['opciones_seleccionadas'];
            $decodificado = is_string($json) ? json_decode($json, true) : null;
            $respuesta['opciones_seleccionadas'] = is_array($decodificado) ? $decodificado : [];
        }
        unset($respuesta);
        return $detalle;
    }

    public function existePorUsuarioYActividad(int $idUsuario, int $idActividad): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM resultados WHERE id_usuario = ? AND id_actividad = ? LIMIT 1');
        $stmt->execute([$idUsuario, $idActividad]);
        return $stmt->fetchColumn() !== false;
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
        $sql = "INSERT INTO resultados
                (id_usuario, id_actividad, puntaje_obtenido, puntaje_total, total_preguntas, respuestas_correctas, porcentaje, estado)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $idUsuario, $idActividad, $puntajeObtenido, $puntajeTotal,
            $totalPreguntas, $respuestasCorrectas, $porcentaje, $estado
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * $opcionesSeleccionadas: IDs de todas las alternativas elegidas.
     * NULL conserva compatibilidad con llamadas anteriores.
     */
    public function guardarRespuestaEstudiante(
        int $idResultado,
        int $idPregunta,
        ?int $idOpcion,
        ?string $respuestaTexto,
        bool $correcta,
        int $puntosObtenidos,
        ?array $opcionesSeleccionadas = null
    ): bool {
        $opcionesJson = null;
        if ($opcionesSeleccionadas !== null) {
            $ids = [];
            foreach ($opcionesSeleccionadas as $valor) {
                $id = filter_var($valor, FILTER_VALIDATE_INT);
                if ($id === false || $id < 1) {
                    throw new InvalidArgumentException('Las opciones seleccionadas deben contener IDs válidos.');
                }
                $ids[] = $id;
            }
            $opcionesJson = json_encode(array_values(array_unique($ids)), JSON_THROW_ON_ERROR);
        }

        $sql = "INSERT INTO respuestas_estudiante
                (id_resultado, id_pregunta, id_opcion, opciones_seleccionadas,
                 respuesta_texto, correcta, puntos_obtenidos)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $idResultado, $idPregunta, $idOpcion, $opcionesJson,
            $respuestaTexto, (int) $correcta, $puntosObtenidos
        ]);
    }

    public function obtenerIdTemaPorActividad(int $idActividad): int
    {
        $stmt = $this->db->prepare('SELECT id_tema FROM actividades WHERE id_actividad = ? LIMIT 1');
        $stmt->execute([$idActividad]);
        return (int) $stmt->fetchColumn();
    }
}
=======
<?php

declare(strict_types=1);

class ResultadoRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function obtenerPorId(int $idResultado): ?array
    {
        $sql = "SELECT * FROM resultados WHERE id_resultado = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idResultado]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function obtenerPorUsuario(int $idUsuario): array
    {
        $sql = "SELECT r.*, a.titulo AS actividad, c.nombre AS curso
                FROM resultados r
                INNER JOIN actividades a ON a.id_actividad = r.id_actividad
            INNER JOIN temas t ON t.id_tema = a.id_tema
            INNER JOIN modulos m ON m.id_modulo = t.id_modulo
            INNER JOIN cursos c ON c.id_curso = m.id_curso
                WHERE r.id_usuario = ?
            ORDER BY r.fecha DESC, r.id_resultado DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idUsuario]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorActividad(int $idActividad): array
    {
        $sql = "SELECT r.*, u.nombres, u.apellidos
                FROM resultados r
                INNER JOIN usuarios u ON u.id_usuario = r.id_usuario
                WHERE r.id_actividad = ?
                ORDER BY r.fecha DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idActividad]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerTodos(): array
    {
        $sql = "SELECT r.*, u.nombres, u.apellidos, u.codigo,
                       a.titulo AS actividad, c.nombre AS curso
                FROM resultados r
                INNER JOIN usuarios u ON u.id_usuario = r.id_usuario
                INNER JOIN actividades a ON a.id_actividad = r.id_actividad
                INNER JOIN temas t ON t.id_tema = a.id_tema
                INNER JOIN modulos m ON m.id_modulo = t.id_modulo
                INNER JOIN cursos c ON c.id_curso = m.id_curso
                ORDER BY r.fecha DESC, r.id_resultado DESC";

        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerDetalle(int $idResultado, ?int $idUsuario = null): ?array
    {
        $sql = "SELECT r.*, u.nombres, u.apellidos, u.codigo,
                       a.titulo AS actividad, c.nombre AS curso, t.nombre AS tema
                FROM resultados r
                INNER JOIN usuarios u ON u.id_usuario = r.id_usuario
                INNER JOIN actividades a ON a.id_actividad = r.id_actividad
                INNER JOIN temas t ON t.id_tema = a.id_tema
                INNER JOIN modulos m ON m.id_modulo = t.id_modulo
                INNER JOIN cursos c ON c.id_curso = m.id_curso
                WHERE r.id_resultado = ?";
        $parametros = [$idResultado];

        if ($idUsuario !== null) {
            $sql .= ' AND r.id_usuario = ?';
            $parametros[] = $idUsuario;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($parametros);
        $detalle = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($detalle === false) {
            return null;
        }

        $stmtRespuestas = $this->db->prepare(
                "SELECT re.correcta, re.puntos_obtenidos, re.respuesta_texto,
                    p.pregunta, p.puntos, o.texto AS opcion_texto,
                    o.imagen AS opcion_imagen
             FROM respuestas_estudiante re
             INNER JOIN preguntas p ON p.id_pregunta = re.id_pregunta
             LEFT JOIN opciones_respuesta o ON o.id_opcion = re.id_opcion
             WHERE re.id_resultado = ?
             ORDER BY p.id_pregunta"
        );
        $stmtRespuestas->execute([$idResultado]);
        $detalle['respuestas'] = $stmtRespuestas->fetchAll(PDO::FETCH_ASSOC);

        return $detalle;
    }

    public function existePorUsuarioYActividad(int $idUsuario, int $idActividad): bool
    {
        $sql = "SELECT 1 FROM resultados WHERE id_usuario = ? AND id_actividad = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idUsuario, $idActividad]);

        return $stmt->fetchColumn() !== false;
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
        $sql = "INSERT INTO resultados
                (id_usuario, id_actividad, puntaje_obtenido, puntaje_total, total_preguntas, respuestas_correctas, porcentaje, estado)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $idUsuario,
            $idActividad,
            $puntajeObtenido,
            $puntajeTotal,
            $totalPreguntas,
            $respuestasCorrectas,
            $porcentaje,
            $estado
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function guardarRespuestaEstudiante(
        int $idResultado,
        int $idPregunta,
        ?int $idOpcion,
        ?string $respuestaTexto,
        bool $correcta,
        int $puntosObtenidos
    ): bool {
        $sql = "INSERT INTO respuestas_estudiante
                (id_resultado, id_pregunta, id_opcion, respuesta_texto, correcta, puntos_obtenidos)
                VALUES (?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            $idResultado,
            $idPregunta,
            $idOpcion,
            $respuestaTexto,
            (int) $correcta,
            $puntosObtenidos
        ]);
    }

    public function obtenerIdTemaPorActividad(int $idActividad): int
    {
        $sql = "SELECT id_tema FROM actividades WHERE id_actividad = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idActividad]);
        
        return (int) $stmt->fetchColumn();
    }
}
>>>>>>> upstream/main
