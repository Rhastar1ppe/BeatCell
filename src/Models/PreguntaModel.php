<?php

class PreguntaModel{
    
    private ?int $id_pregunta;
    private int $id_actividad;
    private string $pregunta;
    private string $tipo;
    private int $puntos;
    private ?string $imagen;
    private ?string $audio;
    private ?int $tiempo_limite;
    private ?string $explicacion;
    private string $estado;
    private array $opciones;
    private array $errores = [];

    const TIPOS_VALIDOS = ['verdadero_falso', 'completar', 'imagen', 'audio'];
    const ESTADOS_VALIDOS = ['Activo', 'Inactivo'];

    public function __construct(array $data = [], array $opciones = []){

        $this->id_pregunta = $data['id_pregunta'] ?? null;
        $this->id_actividad = (int) ($data['id_actividad'] ?? 0);
        $this->pregunta = trim($data['pregunta'] ?? '');
        $this->tipo = $data['tipo'] ?? 'verdadero_falso';
        $this->puntos = (int) ($data['puntos'] ?? 1);
        $this->imagen = $data['imagen'] ?? null;
        $this->audio = !empty($data['audio']) ? (string) $data['audio'] : null;
        $this->tiempo_limite = isset($data['tiempo_limite']) && $data['tiempo_limite'] !== '' ? (int) $data['tiempo_limite'] : null;
        $this->explicacion = trim($data['explicacion'] ?? '');
        $this->estado = $data['estado'] ?? 'Activo';
        $this->opciones = $opciones;
    }

    public function validar(): bool{

        $this->errores = [];

        if ($this->id_actividad <= 0) {
            $this->errores[] = "Debe asociar la pregunta a una actividad válida.";
        }

        if (empty($this->pregunta)) {
            $this->errores[] = "El enunciado de la pregunta es obligatorio.";
        }

        if (!in_array($this->tipo, self::TIPOS_VALIDOS)) {
            $this->errores[] = "El tipo de pregunta no es válido.";
        }

        if ($this->puntos <= 0) {
            $this->errores[] = "Los puntos deben ser mayores a 0.";
        }

        if (!in_array($this->estado, self::ESTADOS_VALIDOS)) {
            $this->errores[] = "El estado no es válido.";
        }

        // Validaciones específicas por tipo
        if ($this->tipo === 'audio' && empty($this->audio)) {
            $this->errores[] = "Las preguntas de audio requieren un archivo de audio.";
        }

        if ($this->tipo === 'imagen' || $this->tipo === 'audio') {
            if (count($this->opciones) < 2) {
                $this->errores[] = "Las preguntas de imagen y audio deben tener al menos 2 opciones.";
            }

            foreach ($this->opciones as $op) {
                if (trim((string) ($op['texto'] ?? '')) === '' && empty($op['imagen'])) {
                    $this->errores[] = "Cada opción debe incluir texto o una imagen.";
                    break;
                }
            }

            $cantidadCorrectas = count(array_filter(
                $this->opciones,
                static fn (array $opcion): bool => !empty($opcion['correcta'])
            ));
            if ($cantidadCorrectas !== 1) {
                $this->errores[] = "Debe marcar exactamente una opción como correcta.";
            }
        } elseif ($this->tipo === 'verdadero_falso') {
            if (count($this->opciones) !== 2) {
                $this->errores[] = "Las preguntas de verdadero/falso deben tener exactamente 2 opciones.";
            }

            $textos = array_map(
                static fn (array $opcion): string => trim((string) ($opcion['texto'] ?? '')),
                $this->opciones
            );
            sort($textos);
            if ($textos !== ['Falso', 'Verdadero']) {
                $this->errores[] = "Las opciones deben ser Verdadero y Falso.";
            }

            $cantidadCorrectas = count(array_filter(
                $this->opciones,
                static fn (array $opcion): bool => !empty($opcion['correcta'])
            ));
            if ($cantidadCorrectas !== 1) {
                $this->errores[] = "Debe definir exactamente una respuesta correcta.";
            }
        } elseif ($this->tipo === 'completar') {
            if (count($this->opciones) !== 1 || trim((string) ($this->opciones[0]['texto'] ?? '')) === '') {
                $this->errores[] = "Debe especificar la respuesta correcta para la pregunta de completar.";
            } elseif (empty($this->opciones[0]['correcta'])) {
                $this->errores[] = "La respuesta de completar debe estar marcada como correcta.";
            }
        }

        return empty($this->errores);
    }

    public function obtenerErrores(): array{
        return $this->errores;
    }

    public function toArray(): array{
        return [
            'id_pregunta' => $this->id_pregunta,
            'id_actividad' => $this->id_actividad,
            'pregunta' => $this->pregunta,
            'tipo' => $this->tipo,
            'puntos' => $this->puntos,
            'imagen' => $this->imagen,
            'audio' => $this->audio,
            'tiempo_limite' => $this->tiempo_limite,
            'explicacion' => $this->explicacion,
            'estado' => $this->estado
        ];
    }
}