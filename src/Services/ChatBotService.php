<?php

require_once __DIR__ . '/../Models/TiempoActividad.php';

/**
 * Lógica del asistente BeatCell (sin IA, funciona offline).
 * Solo lee datos del estudiante autenticado y responde con texto + enlaces.
 */
class ChatBotService
{
    private PDO $db;
    private int $idUsuario;

    public function __construct(PDO $db, int $idUsuario)
    {
        $this->db = $db;
        $this->idUsuario = $idUsuario;
    }

    /** Minúsculas, sin tildes ni signos, para comparar palabras. */
    public static function normalizar(string $texto): string
    {
        $texto = mb_strtolower(trim($texto), 'UTF-8');
        $texto = strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        $texto = preg_replace('/[^a-z0-9\s]/', ' ', $texto) ?? '';
        return trim(preg_replace('/\s+/', ' ', $texto) ?? '');
    }

    /** Actividades activas que el estudiante todavía no resolvió. */
    public function actividadesPendientes(): array
    {
        $stmt = $this->db->prepare(
            "SELECT a.id_actividad, a.titulo, a.fecha_limite
             FROM actividades a
             WHERE a.estado = 'Activo'
               AND EXISTS (SELECT 1 FROM preguntas p
                           WHERE p.id_actividad = a.id_actividad AND p.estado = 'Activo')
               AND NOT EXISTS (SELECT 1 FROM resultados r
                               WHERE r.id_actividad = a.id_actividad AND r.id_usuario = ?)
             ORDER BY (a.fecha_limite IS NULL), a.fecha_limite ASC, a.id_actividad ASC"
        );
        $stmt->execute([$this->idUsuario]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Número que va en la insignia roja: pendientes por vencer o ya vencidas. */
    public function contarAvisos(): int
    {
        $total = 0;
        foreach ($this->actividadesPendientes() as $a) {
            $estado = TiempoActividad::estadoFecha($a['fecha_limite'] ?? null);
            if ($estado === TiempoActividad::PRONTO || $estado === TiempoActividad::VENCIDA) {
                $total++;
            }
        }
        return $total;
    }

    public function saludoInicial(): array
    {
        $avisos = $this->contarAvisos();
        $texto = '¡Hola! Soy el asistente de BeatCell. Te llevo a cada sección y te recuerdo las fechas de tus actividades.';
        if ($avisos > 0) {
            $texto .= "\n\n⚠️ Tienes {$avisos} actividad" . ($avisos === 1 ? '' : 'es') . ' por vencer o vencida' . ($avisos === 1 ? '' : 's') . '. Pregúntame por tus fechas.';
        }
        return $this->respuesta($texto, [], $this->botonesBase());
    }

    public function responder(string $mensaje): array
    {
        $t = ' ' . self::normalizar($mensaje) . ' ';
        $tiene = static function (array $claves) use ($t): bool {
            foreach ($claves as $c) {
                if (str_contains($t, $c)) {
                    return true;
                }
            }
            return false;
        };

        if ($tiene(['gracias', 'genial', 'perfecto'])) {
            return $this->respuesta('¡De nada! Aquí estaré si necesitas algo más. 😊', [], $this->botonesBase());
        }
        if ($tiene(['vencid', 'atrasad', 'se paso', 'perdi'])) {
            return $this->actividadesVencidas();
        }
        if ($tiene(['fecha', 'plazo', ' vence', 'pendiente', 'entrega', 'tarea', 'cuando', 'urgente', 'record', 'limite', 'que me falta', 'por hacer'])) {
            return $this->actividadesPorFecha();
        }
        if ($tiene(['actividad', 'examen', 'evaluacion', 'cuestionario', 'quiz', 'prueba'])) {
            return $this->respuesta('Aquí encuentras todas tus actividades y evaluaciones.', [['Ir a Actividades', 'actividades.php']], $this->botonesBase());
        }
        if ($tiene(['curso', 'clase', 'tema', 'modulo', 'materia', 'estudiar', 'aprender', 'contenido'])) {
            return $this->respuesta('En Cursos están los módulos y temas para estudiar.', [['Ir a Cursos', 'cursos.php']], $this->botonesBase());
        }
        if ($tiene(['ruta', 'camino', 'carrera'])) {
            return $this->respuesta('Tu ruta de aprendizaje te muestra qué cursos seguir y en qué orden.', [['Ver rutas', 'ruta.php']], $this->botonesBase());
        }
        if ($tiene(['historial', 'resultado', 'resultados', 'intento', 'nota guardada'])) {
            return $this->respuesta('En Historial puedes revisar los resultados que ya se guardaron.', [['Ver mis resultados', 'historial.php']], $this->botonesBase());
        }
        if ($tiene(['progreso', 'avance', 'nota', 'puntaje', 'calificacion'])) {
            return $this->respuesta('En Mi progreso puedes revisar tu avance general.', [['Ir a Mi progreso', 'progreso.php']], $this->botonesBase());
        }
        if ($tiene(['inicio', 'principal', 'home'])) {
            return $this->respuesta('Te llevo al inicio.', [['Ir a Inicio', 'principal.php']], $this->botonesBase());
        }
        if ($tiene([' hola ', 'buenas', 'buenos dias', 'buenas tardes', ' hey '])) {
            return $this->saludoInicial();
        }
        if ($tiene(['ayuda', 'que puedes', 'menu', 'opciones', 'que haces'])) {
            return $this->respuesta('Puedo llevarte a una sección o recordarte tus fechas. Elige una opción:', [], $this->botonesBase());
        }

        return $this->respuesta(
            'No estoy seguro de haber entendido 🤔. Solo puedo llevarte a una sección o recordarte tus fechas. Prueba con una de estas opciones:',
            [],
            $this->botonesBase()
        );
    }

    private function actividadesPorFecha(): array
    {
        $pendientes = $this->actividadesPendientes();
        if (!$pendientes) {
            return $this->respuesta('🎉 ¡Estás al día! No tienes actividades pendientes.', [], $this->botonesBase());
        }

        $lineas = [];
        $enlaces = [];
        foreach ($pendientes as $a) {
            $estado = TiempoActividad::estadoFecha($a['fecha_limite'] ?? null);
            $icono = match ($estado) {
                TiempoActividad::VENCIDA => '🔴',
                TiempoActividad::PRONTO => '🟠',
                TiempoActividad::VIGENTE => '🟢',
                default => '⚪',
            };
            $lineas[] = $icono . ' ' . $a['titulo'] . ' — ' . TiempoActividad::describirFecha($a['fecha_limite'] ?? null);
            if (count($enlaces) < 3) {
                $enlaces[] = ['Resolver: ' . $a['titulo'], 'actividad.php?id=' . (int) $a['id_actividad']];
            }
        }

        $n = count($pendientes);
        $texto = "Tienes {$n} actividad" . ($n === 1 ? '' : 'es') . " pendiente" . ($n === 1 ? '' : 's') . ":\n\n" . implode("\n", $lineas);
        return $this->respuesta($texto, $enlaces, $this->botonesBase());
    }

    private function actividadesVencidas(): array
    {
        $vencidas = [];
        foreach ($this->actividadesPendientes() as $a) {
            if (TiempoActividad::estadoFecha($a['fecha_limite'] ?? null) === TiempoActividad::VENCIDA) {
                $vencidas[] = $a;
            }
        }
        if (!$vencidas) {
            return $this->respuesta('No tienes actividades vencidas sin resolver. ¡Bien! 👏', [], $this->botonesBase());
        }

        $lineas = [];
        $enlaces = [];
        foreach ($vencidas as $a) {
            $lineas[] = '🔴 ' . $a['titulo'] . ' — ' . TiempoActividad::describirFecha($a['fecha_limite']);
            if (count($enlaces) < 3) {
                $enlaces[] = ['Resolver: ' . $a['titulo'], 'actividad.php?id=' . (int) $a['id_actividad']];
            }
        }
        $texto = "Estas actividades ya vencieron y no las has resuelto:\n\n" . implode("\n", $lineas);
        return $this->respuesta($texto, $enlaces, $this->botonesBase());
    }

    private function botonesBase(): array
    {
        return ['Mis fechas', 'Actividades', 'Cursos', 'Mi progreso', 'Historial'];
    }

    private function respuesta(string $texto, array $enlaces, array $botones): array
    {
        return [
            'texto' => $texto,
            'enlaces' => array_map(static fn($e) => ['texto' => $e[0], 'url' => $e[1]], $enlaces),
            'botones' => $botones,
        ];
    }
}
