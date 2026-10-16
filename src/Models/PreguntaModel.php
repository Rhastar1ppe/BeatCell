<?php

class PreguntaModel
{
    private ?int $id_pregunta;
    private int $id_actividad;
    private string $pregunta;
    private string $tipo;
    private ?string $tipo_respuesta;
    private int $puntos;
    private ?string $imagen;
    private ?string $audio;
    private ?string $video_url;
    private bool $seleccion_multiple;
    private ?int $tiempo_limite;
    private ?string $explicacion;
    private string $estado;
    private array $opciones;
    private array $errores = [];

    const TIPOS_VALIDOS = [
        'verdadero_falso',
        'completar',
        'imagen',
        'audio',
        'opcion_multiple',
        'video'
    ];

    const TIPOS_RESPUESTA_VIDEO = [
        'verdadero_falso',
        'completar',
        'opcion_multiple'
    ];

    const ESTADOS_VALIDOS = ['Activo', 'Inactivo'];

    public function __construct(array $data = [], array $opciones = [])
    {
        $this->id_pregunta = isset($data['id_pregunta'])
            ? (int) $data['id_pregunta']
            : null;

        $this->id_actividad = (int) ($data['id_actividad'] ?? 0);
        $this->pregunta = trim((string) ($data['pregunta'] ?? ''));
        $this->tipo = (string) ($data['tipo'] ?? 'verdadero_falso');

        $this->tipo_respuesta = $this->tipo === 'video'
            ? (string) ($data['tipo_respuesta'] ?? 'opcion_multiple')
            : null;

        $this->puntos = (int) ($data['puntos'] ?? 1);
        $this->imagen = $data['imagen'] ?? null;

        $this->audio = !empty($data['audio'])
            ? (string) $data['audio']
            : null;

        $this->video_url = !empty($data['video_url'])
            ? trim((string) $data['video_url'])
            : null;

        $this->seleccion_multiple = !empty($data['seleccion_multiple']);

        $this->tiempo_limite =
            isset($data['tiempo_limite']) && $data['tiempo_limite'] !== ''
                ? (int) $data['tiempo_limite']
                : null;

        $this->explicacion = trim((string) ($data['explicacion'] ?? ''));
        $this->estado = (string) ($data['estado'] ?? 'Activo');
        $this->opciones = $opciones;
    }

    public function validar(): bool
    {
        $this->errores = [];

        if ($this->id_actividad <= 0) {
            $this->errores[] =
                "Debe asociar la pregunta a una actividad válida.";
        }

        if ($this->pregunta === '') {
            $this->errores[] =
                "El enunciado de la pregunta es obligatorio.";
        }

        if (!in_array($this->tipo, self::TIPOS_VALIDOS, true)) {
            $this->errores[] =
                "El tipo de pregunta no es válido.";
        }

        if ($this->puntos <= 0) {
            $this->errores[] =
                "Los puntos deben ser mayores a 0.";
        }

        if (!in_array($this->estado, self::ESTADOS_VALIDOS, true)) {
            $this->errores[] =
                "El estado no es válido.";
        }

        // Validar archivo de audio
        if ($this->tipo === 'audio' && empty($this->audio)) {
            $this->errores[] =
                "Las preguntas de audio requieren un archivo de audio.";
        }

        // Validar dirección del video
        if (
            $this->video_url !== null &&
            !filter_var($this->video_url, FILTER_VALIDATE_URL) &&
            !preg_match(
                '~^/?[a-zA-Z0-9_./-]+\.mp4$~i',
                $this->video_url
            )
        ) {
            $this->errores[] =
                "La dirección del video debe ser una URL válida o una ruta MP4.";
        }

        if ($this->tipo === 'video' && $this->video_url === null) {
            $this->errores[] =
                "Las preguntas de video requieren un video.";
        }

        // Validar el tipo de respuesta de las preguntas con video
        if ($this->tipo === 'video') {
            if (
                !in_array(
                    $this->tipo_respuesta,
                    self::TIPOS_RESPUESTA_VIDEO,
                    true
                )
            ) {
                $this->errores[] =
                    "El tipo de respuesta del video no es válido.";
            }
        }

        // Determinar cómo debe evaluarse la pregunta
        $tipoEvaluacion = $this->tipo === 'video'
            ? $this->tipo_respuesta
            : $this->tipo;

        // La selección múltiple solo corresponde a preguntas con alternativas
        if (
            $this->seleccion_multiple &&
            !in_array(
                $tipoEvaluacion,
                ['opcion_multiple', 'imagen', 'audio'],
                true
            )
        ) {
            $this->errores[] =
                "Este tipo de respuesta no permite selección múltiple.";
        }

        // Validar preguntas con alternativas
        if (
            in_array(
                $tipoEvaluacion,
                ['imagen', 'audio', 'opcion_multiple'],
                true
            )
        ) {
            if (count($this->opciones) < 2) {
                $this->errores[] =
                    "La pregunta debe tener al menos dos opciones.";
            }

            foreach ($this->opciones as $opcion) {
                $texto = trim((string) ($opcion['texto'] ?? ''));

                if ($texto === '' && empty($opcion['imagen'])) {
                    $this->errores[] =
                        "Cada opción debe incluir texto o una imagen.";
                    break;
                }
            }

            $cantidadCorrectas = $this->contarCorrectas();

            if ($this->seleccion_multiple) {
                if ($cantidadCorrectas < 2) {
                    $this->errores[] =
                        "Debe marcar al menos dos respuestas correctas.";
                }
            } else {
                if ($cantidadCorrectas !== 1) {
                    $this->errores[] =
                        "Debe marcar exactamente una opción como correcta.";
                }
            }
        }

        // Validar Verdadero / Falso
        elseif ($tipoEvaluacion === 'verdadero_falso') {
            if (count($this->opciones) !== 2) {
                $this->errores[] =
                    "Las preguntas de verdadero/falso deben tener exactamente dos opciones.";
            }

            $textos = array_map(
                static fn(array $opcion): string =>
                    trim((string) ($opcion['texto'] ?? '')),
                $this->opciones
            );

            sort($textos);

            if ($textos !== ['Falso', 'Verdadero']) {
                $this->errores[] =
                    "Las opciones deben ser Verdadero y Falso.";
            }

            if ($this->contarCorrectas() !== 1) {
                $this->errores[] =
                    "Debe definir exactamente una respuesta correcta.";
            }
        }

        // Validar Completar
        elseif ($tipoEvaluacion === 'completar') {
            if (
                count($this->opciones) !== 1 ||
                trim((string) ($this->opciones[0]['texto'] ?? '')) === ''
            ) {
                $this->errores[] =
                    "Debe especificar la respuesta correcta para la pregunta de completar.";
            } elseif (empty($this->opciones[0]['correcta'])) {
                $this->errores[] =
                    "La respuesta de completar debe estar marcada como correcta.";
            }
        }

        return empty($this->errores);
    }

    // Contar las respuestas correctas
    private function contarCorrectas(): int
    {
        return count(array_filter(
            $this->opciones,
            static fn(array $opcion): bool =>
                !empty($opcion['correcta'])
        ));
    }

    public function obtenerErrores(): array
    {
        return $this->errores;
    }

    public function toArray(): array
    {
        return [
            'id_pregunta' => $this->id_pregunta,
            'id_actividad' => $this->id_actividad,
            'pregunta' => $this->pregunta,
            'tipo' => $this->tipo,
            'tipo_respuesta' => $this->tipo_respuesta,
            'puntos' => $this->puntos,
            'imagen' => $this->imagen,
            'audio' => $this->audio,
            'video_url' => $this->video_url,
            'seleccion_multiple' => $this->seleccion_multiple ? 1 : 0,
            'tiempo_limite' => $this->tiempo_limite,
            'explicacion' => $this->explicacion,
            'estado' => $this->estado
        ];
    }
}
