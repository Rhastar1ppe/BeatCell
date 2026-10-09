<?php

require_once __DIR__ . '/../Repositories/PreguntaRepository.php';
require_once __DIR__ . '/../Models/PreguntaModel.php';

class PreguntaController {
    
    private PreguntaRepository $repository;
    private PDO $db;

    // Audio del enunciado: extensiones permitidas y tipos MIME aceptados para cada una.
    private const AUDIO_TIPOS = [
        'mp3' => ['audio/mpeg', 'audio/mp3', 'audio/x-mpeg'],
        'wav' => ['audio/wav', 'audio/x-wav', 'audio/wave', 'audio/vnd.wave'],
        'ogg' => ['audio/ogg', 'application/ogg', 'video/ogg'],
        'm4a' => ['audio/mp4', 'audio/x-m4a', 'audio/m4a', 'video/mp4'],
    ];
    private const AUDIO_MAX_BYTES = 10 * 1024 * 1024; // 10 MB

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->repository = new PreguntaRepository($db);
    }

    private function directorioAudio(): string {
        return __DIR__ . '/../../public/assets/audio/preguntas/';
    }

    /**
     * Procesa el archivo enviado en el campo "audio_pregunta".
     * Devuelve ['nombre' => ?string, 'error' => ?string].
     * 'nombre' es null cuando no se envió ningún archivo (no es un error).
     */
    private function procesarAudioSubido(): array {
        $archivo = $_FILES['audio_pregunta'] ?? null;
        $codigo = is_array($archivo) ? ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;

        if ($codigo === UPLOAD_ERR_NO_FILE) {
            return ['nombre' => null, 'error' => null];
        }
        if ($codigo === UPLOAD_ERR_INI_SIZE || $codigo === UPLOAD_ERR_FORM_SIZE) {
            return ['nombre' => null, 'error' => 'El audio supera el tamaño máximo permitido (10 MB).'];
        }
        if ($codigo !== UPLOAD_ERR_OK) {
            return ['nombre' => null, 'error' => 'No se pudo recibir el audio. Inténtalo de nuevo.'];
        }

        $rutaTemporal = $archivo['tmp_name'] ?? '';
        $nombreOriginal = (string) ($archivo['name'] ?? '');
        $tamano = (int) ($archivo['size'] ?? 0);
        $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));

        if (
            !is_string($rutaTemporal)
            || $rutaTemporal === ''
            || !is_uploaded_file($rutaTemporal)
            || $tamano < 1
            || $tamano > self::AUDIO_MAX_BYTES
            || !isset(self::AUDIO_TIPOS[$extension])
        ) {
            return ['nombre' => null, 'error' => 'El audio debe ser MP3, WAV, OGG o M4A y no superar 10 MB.'];
        }

        // Se comprueba el contenido real del archivo, no solo la extensión.
        if (class_exists('finfo')) {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($rutaTemporal);
            if (!is_string($mime) || !in_array($mime, self::AUDIO_TIPOS[$extension], true)) {
                return ['nombre' => null, 'error' => 'El contenido del archivo no corresponde a un audio válido.'];
            }
        }

        $directorio = $this->directorioAudio();
        if (!is_dir($directorio) && !mkdir($directorio, 0755, true) && !is_dir($directorio)) {
            return ['nombre' => null, 'error' => 'No se pudo preparar el almacenamiento de audios.'];
        }
        if (!is_writable($directorio)) {
            return ['nombre' => null, 'error' => 'El almacenamiento de audios no está disponible.'];
        }

        $nombreNuevo = bin2hex(random_bytes(16)) . '.' . $extension;
        if (!move_uploaded_file($rutaTemporal, $directorio . $nombreNuevo)) {
            return ['nombre' => null, 'error' => 'No se pudo guardar el audio. Inténtalo de nuevo.'];
        }

        return ['nombre' => $nombreNuevo, 'error' => null];
    }

    private function eliminarArchivoAudio(?string $nombre): void {
        if ($nombre === null || $nombre === '') {
            return;
        }
        $ruta = $this->directorioAudio() . basename($nombre);
        if (is_file($ruta)) {
            @unlink($ruta);
        }
    }

    public function obtenerActividades(): array {
        $stmt = $this->db->query("SELECT a.id_actividad, a.titulo, t.nombre AS tema_nombre 
                                FROM actividades a 
                                INNER JOIN temas t ON a.id_tema = t.id_tema 
                                WHERE a.estado = 'Activo'
                                ORDER BY t.nombre ASC, a.titulo ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarPorActividad(?int $idActividad): array {
        if (!$idActividad) {
            return [];
        }
        return $this->repository->obtenerPorActividad($idActividad);
    }

    public function verDetalle(int $idPregunta): ?array {
        return $this->repository->obtenerConOpciones($idPregunta);
    }

    public function procesarFormulario(): ?array {
        $mensaje = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $accion = $_POST['accion'] ?? '';

            if ($accion === 'crear' || $accion === 'actualizar') {
                $idActividad = (int) ($_POST['id_actividad'] ?? 0);
                $enunciado = $_POST['pregunta'] ?? '';
                $tipo = $_POST['tipo'] ?? 'verdadero_falso';
                $puntos = (int) ($_POST['puntos'] ?? 1);
                $tiempoLimite = !empty($_POST['tiempo_limite']) ? (int) $_POST['tiempo_limite'] : null;
                $explicacion = $_POST['explicacion'] ?? null;
                $estado = $_POST['estado'] ?? 'Activo';

                // Recolectar opciones del formulario
                $opcionesInput = $_POST['opciones'] ?? [];
                $textosOpciones = $opcionesInput['texto'] ?? [];
                $correctasOpciones = $opcionesInput['correcta'] ?? [];
                $retroalimentaciones = $opcionesInput['retroalimentacion'] ?? [];
                $imagenesActuales = $opcionesInput['imagen_actual'] ?? [];
                $idsOpciones = $opcionesInput['id_opcion'] ?? [];

                $uploadDir = __DIR__ . '/../../public/assets/img/preguntas/';

                $opcionesFormateadas = [];
                foreach ($textosOpciones as $index => $texto) {
                    $tieneTexto = trim($texto) !== '';
                    $imagenPath = $imagenesActuales[$index] ?? null;

                    $errorSubida = $_FILES['opciones']['error']['imagen'][$index] ?? UPLOAD_ERR_NO_FILE;
                    if ($errorSubida !== UPLOAD_ERR_NO_FILE) {
                        if ($errorSubida !== UPLOAD_ERR_OK) {
                            return ['tipo' => 'danger', 'texto' => 'No se pudo recibir la imagen. Inténtalo de nuevo.'];
                        }

                        $fileTmpPath = $_FILES['opciones']['tmp_name']['imagen'][$index] ?? '';
                        $fileName = $_FILES['opciones']['name']['imagen'][$index] ?? '';
                        $fileSize = (int) ($_FILES['opciones']['size']['imagen'][$index] ?? 0);
                        $fileExtension = strtolower(pathinfo((string) $fileName, PATHINFO_EXTENSION));
                        $tiposImagen = [
                            'jpg' => 'image/jpeg',
                            'jpeg' => 'image/jpeg',
                            'png' => 'image/png',
                            'gif' => 'image/gif',
                            'webp' => 'image/webp',
                        ];
                        $informacionImagen = is_string($fileTmpPath) && $fileTmpPath !== ''
                            ? @getimagesize($fileTmpPath)
                            : false;

                        if (
                            $fileSize < 1
                            || $fileSize > 5 * 1024 * 1024
                            || !isset($tiposImagen[$fileExtension])
                            || $informacionImagen === false
                            || ($informacionImagen['mime'] ?? '') !== ($tiposImagen[$fileExtension] ?? null)
                        ) {
                            return ['tipo' => 'danger', 'texto' => 'La imagen debe ser JPG, PNG, GIF o WebP y no superar 5 MB.'];
                        }

                        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                            return ['tipo' => 'danger', 'texto' => 'No se pudo preparar el almacenamiento de imágenes.'];
                        }
                        if (!is_writable($uploadDir)) {
                            return ['tipo' => 'danger', 'texto' => 'El almacenamiento de imágenes no está disponible.'];
                        }

                        $newFileName = bin2hex(random_bytes(16)) . '.' . $fileExtension;
                        if (!move_uploaded_file($fileTmpPath, $uploadDir . $newFileName)) {
                            return ['tipo' => 'danger', 'texto' => 'No se pudo guardar la imagen. Inténtalo de nuevo.'];
                        }
                        $imagenPath = $newFileName;
                    }

                    // Se valida que la opción tenga contenido
                    if ($tieneTexto || $imagenPath) {
                        $opcionesFormateadas[] = [
                            'texto' => trim($texto),
                            'imagen' => $imagenPath,
                            'id_opcion' => (int) ($idsOpciones[$index] ?? 0),
                            // El formulario envía siempre un campo oculto con '1' o '0'.
                            // isset() daba true también con '0', por eso se compara el valor.
                            'correcta' => (($correctasOpciones[$index] ?? '0') === '1'),
                            'retroalimentacion' => $retroalimentaciones[$index] ?? null
                        ];
                    }
                }

                if ($tipo === 'verdadero_falso') {
                    foreach ($opcionesFormateadas as &$opcion) {
                        $textoNormalizado = strtolower(trim($opcion['texto']));
                        if ($textoNormalizado === 'verdadero') {
                            $opcion['texto'] = 'Verdadero';
                        } elseif ($textoNormalizado === 'falso') {
                            $opcion['texto'] = 'Falso';
                        }
                    }
                    unset($opcion);
                }

                // Audio actual guardado en BD (se lee de la BD, no del formulario, para no confiar en datos del cliente)
                $audioAnterior = null;
                if ($accion === 'actualizar') {
                    $idEdicion = (int) ($_POST['id_pregunta'] ?? 0);
                    if ($idEdicion > 0) {
                        $existente = $this->repository->obtenerPorId($idEdicion);
                        $audioAnterior = !empty($existente['audio']) ? (string) $existente['audio'] : null;
                    }
                }

                // Solo las preguntas de tipo audio conservan archivo; en los demás tipos queda en NULL.
                $audioNuevo = null;
                $audioFinal = null;
                if ($tipo === 'audio') {
                    $subida = $this->procesarAudioSubido();
                    if ($subida['error'] !== null) {
                        return ['tipo' => 'danger', 'texto' => $subida['error']];
                    }
                    $audioNuevo = $subida['nombre'];
                    $audioFinal = $audioNuevo ?? $audioAnterior;
                }

                $data = [
                    'id_actividad' => $idActividad,
                    'pregunta' => $enunciado,
                    'tipo' => $tipo,
                    'puntos' => $puntos,
                    'audio' => $audioFinal,
                    'tiempo_limite' => $tiempoLimite,
                    'explicacion' => $explicacion,
                    'estado' => $estado
                ];

                $modelo = new PreguntaModel($data, $opcionesFormateadas);

                if ($modelo->validar()) {
                    try {
                        if ($accion === 'crear') {
                            $this->repository->crear($modelo->toArray(), $opcionesFormateadas);
                            $mensaje = ['tipo' => 'success', 'texto' => 'Pregunta registrada con éxito.'];
                        } else {
                            $idPregunta = (int) ($_POST['id_pregunta'] ?? 0);
                            if ($idPregunta <= 0) {
                                $this->eliminarArchivoAudio($audioNuevo);
                                return ['tipo' => 'danger', 'texto' => 'La pregunta que intentas editar no es válida.'];
                            }
                            $this->repository->actualizar($idPregunta, $modelo->toArray(), $opcionesFormateadas);
                            // Si el audio se reemplazó o el tipo dejó de ser audio, se borra el archivo viejo.
                            if ($audioAnterior !== null && $audioAnterior !== $audioFinal) {
                                $this->eliminarArchivoAudio($audioAnterior);
                            }
                            $mensaje = ['tipo' => 'success', 'texto' => 'Pregunta actualizada correctamente.'];
                        }
                    } catch (Exception $e) {
                        $this->eliminarArchivoAudio($audioNuevo);
                        // El detalle técnico va al log del servidor, no a la pantalla.
                        error_log('BeatCell - error al guardar pregunta: ' . $e->getMessage());
                        $mensaje = ['tipo' => 'danger', 'texto' => 'No se pudo guardar la pregunta. Revisa que la actividad exista e inténtalo de nuevo.'];
                    }
                } else {
                    $this->eliminarArchivoAudio($audioNuevo);
                    $errores = implode("\n", $modelo->obtenerErrores());
                    $mensaje = ['tipo' => 'danger', 'texto' => $errores];
                }
            } elseif ($accion === 'eliminar') {
                $idPregunta = (int) ($_POST['id_pregunta'] ?? 0);
                if ($idPregunta > 0) {
                    $existente = $this->repository->obtenerPorId($idPregunta);
                    $this->repository->eliminar($idPregunta);
                    if (!empty($existente['audio'])) {
                        $this->eliminarArchivoAudio((string) $existente['audio']);
                    }
                    $mensaje = ['tipo' => 'success', 'texto' => 'Pregunta eliminada correctamente.'];
                } else {
                    $mensaje = ['tipo' => 'danger', 'texto' => 'La pregunta que intentas eliminar no es válida.'];
                }
            }
        }

        return $mensaje;
    }
}   