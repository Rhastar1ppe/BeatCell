<<<<<<< HEAD
<?php

require_once __DIR__ . '/../../src/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../src/Controllers/CursoController.php';
require_once __DIR__ . '/../../src/Controllers/ModuloController.php';

AuthMiddleware::requireRole(['Docente', 'Administrador'], '../auth/login.php');

if (empty($_SESSION['csrf_gestion'])) {
    $_SESSION['csrf_gestion'] = bin2hex(random_bytes(32));
}

function e($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

/** Borra un video local subido por esta pantalla (ruta ya validada). */
function eliminarVideoLocal(?string $ruta): void
{
    if (
        is_string($ruta) &&
        preg_match('#^public/uploads/videos/([a-f0-9]{32}\.mp4)$#', $ruta, $m) === 1
    ) {
        $archivo = __DIR__ . '/../../public/uploads/videos/' . $m[1];

        if (is_file($archivo)) {
            @unlink($archivo);
        }
    }
}

$idCurso = (int) ($_GET['id_curso'] ?? $_POST['id_curso'] ?? 0);
$idEditar = (int) ($_GET['editar'] ?? 0);
$datos = $_POST;
$mensaje = null;
$moduloEditar = null;
$cursoController = null;
$moduloController = null;
$cursos = [];
$curso = null;
$modulos = [];
$archivoNuevo = null;

try {
    $cursoController = new CursoController();
    $moduloController = new ModuloController();

    // 1. Obtener todos los cursos para el selector
    $cursos = method_exists($cursoController, 'obtenerTodos') 
        ? $cursoController->obtenerTodos() 
        : (method_exists($cursoController, 'listar') ? $cursoController->listar() : []);

    // 2. Si no viene id_curso por parámetro, tomar el ID del primer curso disponible
    if ($idCurso === 0 && !empty($cursos)) {
        $idCurso = (int) ($cursos[0]['id_curso'] ?? 0);
    }

    // 3. Obtener los datos del curso actual si existe idCurso
    if ($idCurso > 0) {
        $curso = $cursoController->obtenerPorId($idCurso);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Comprobar la sesión del formulario.
        $token = $_POST['csrf_token'] ?? '';

        if (
            !is_string($token) ||
            !hash_equals($_SESSION['csrf_gestion'], $token)
        ) {
            throw new InvalidArgumentException(
                'La sesión del formulario expiró. Recarga la página. '
                . 'Si subiste un video, comprueba el límite de carga de PHP.'
            );
        }

        $accion = $_POST['accion'] ?? '';
        $idModulo = (int) ($_POST['id_modulo'] ?? 0);

        if (!in_array($accion, ['crear', 'actualizar', 'estado'], true)) {
            throw new InvalidArgumentException('La acción no es válida.');
        }

        // Verificar que el módulo pertenece al curso.
        $moduloActual = null;

        if ($accion === 'actualizar' || $accion === 'estado') {
            $moduloActual = $moduloController->obtenerPorId($idModulo);

            if (
                !$moduloActual ||
                (int) $moduloActual['id_curso'] !== $idCurso
            ) {
                throw new InvalidArgumentException(
                    'El módulo no existe en este curso.'
                );
            }

            if ($accion === 'actualizar') {
                $moduloEditar = $moduloActual;
            }
        }

        if ($accion === 'crear' || $accion === 'actualizar') {
            $datosGuardar = $_POST;
            $datosGuardar['id_curso'] = $idCurso;

            // Conservar el video actual si no se selecciona otro.
            $datosGuardar['video_url'] =
                $moduloActual['video_url'] ?? null;

            if (isset($_POST['quitar_video'])) {
                $datosGuardar['video_url'] = null;
            }

            $video = $_FILES['video'] ?? null;

            if (
                $video !== null &&
                (
                    !is_array($video) ||
                    !isset($video['error']) ||
                    !is_int($video['error'])
                )
            ) {
                throw new InvalidArgumentException(
                    'El archivo de video no es válido.'
                );
            }

            if (
                $video !== null &&
                $video['error'] !== UPLOAD_ERR_NO_FILE
            ) {
                if ($video['error'] !== UPLOAD_ERR_OK) {
                    throw new InvalidArgumentException(
                        'No se pudo subir el video. '
                        . 'Revisa el límite de carga de PHP.'
                    );
                }

                $temporal = $video['tmp_name'] ?? '';

                if (
                    !is_string($temporal) ||
                    !is_uploaded_file($temporal)
                ) {
                    throw new InvalidArgumentException(
                        'La carga del archivo no es válida.'
                    );
                }

                // Validar tamaño y tipo real del archivo.
                $tamano = filesize($temporal);
                $tipo = (new finfo(FILEINFO_MIME_TYPE))->file($temporal);

                if (
                    !$tamano ||
                    $tamano > 50 * 1024 * 1024 ||
                    $tipo !== 'video/mp4'
                ) {
                    throw new InvalidArgumentException(
                        'Selecciona un video MP4 de hasta 50 MB.'
                    );
                }

                $carpeta = __DIR__ . '/../../public/uploads/videos';

                if (
                    !is_dir($carpeta) &&
                    !mkdir($carpeta, 0755, true) &&
                    !is_dir($carpeta)
                ) {
                    throw new RuntimeException(
                        'No se pudo crear la carpeta de videos.'
                    );
                }

                // Generar un nombre único para el archivo.
                $nombreArchivo = bin2hex(random_bytes(16)) . '.mp4';
                $archivoNuevo = $carpeta . '/' . $nombreArchivo;

                if (!move_uploaded_file($temporal, $archivoNuevo)) {
                    throw new RuntimeException(
                        'No se pudo guardar el video.'
                    );
                }

                $datosGuardar['video_url'] =
                    'public/uploads/videos/' . $nombreArchivo;
            }

            if ($accion === 'crear') {
                $moduloController->crear($datosGuardar);
                $resultado = 'creado';
            } else {
                if (
                    !$moduloController->actualizar(
                        $idModulo,
                        $datosGuardar
                    )
                ) {
                    throw new RuntimeException(
                        'No se pudo actualizar el módulo.'
                    );
                }

                $resultado = 'actualizado';

                $videoAnterior = $moduloActual['video_url'] ?? null;

                if ($videoAnterior !== ($datosGuardar['video_url'] ?? null)) {
                    eliminarVideoLocal($videoAnterior);
                }
            }

            // El archivo ya está asociado al módulo.
            $archivoNuevo = null;

            header(
                'Location: modulos.php?id_curso='
                . $idCurso . '&ok=' . $resultado
            );
            exit;
        }

        if ($accion === 'estado') {
            if (
                !$moduloController->cambiarEstado(
                    $idModulo,
                    $_POST['estado'] ?? ''
                )
            ) {
                throw new RuntimeException(
                    'No se pudo cambiar el estado del módulo.'
                );
            }

            header(
                'Location: modulos.php?id_curso='
                . $idCurso . '&ok=estado'
            );
            exit;
        }
    }

    if ($idEditar > 0 && $moduloEditar === null) {
        $moduloEditar = $moduloController->obtenerPorId($idEditar);

        if (!$moduloEditar || (int) $moduloEditar['id_curso'] !== $idCurso) {
            $moduloEditar = null;

            throw new InvalidArgumentException(
                'El módulo no existe en este curso.'
            );
        }
    }

    $mensajes = [
        'creado' => 'Módulo creado correctamente.',
        'actualizado' => 'Módulo actualizado correctamente.',
        'estado' => 'Estado del módulo actualizado.'
    ];

    $ok = $_GET['ok'] ?? '';

    if (is_string($ok) && isset($mensajes[$ok])) {
        $mensaje = [
            'tipo' => 'success',
            'texto' => $mensajes[$ok]
        ];
    }
} catch (InvalidArgumentException $exception) {
    if ($archivoNuevo !== null && is_file($archivoNuevo)) {
        unlink($archivoNuevo);
    }

    $mensaje = [
        'tipo' => 'danger',
        'texto' => $exception->getMessage()
    ];
} catch (Throwable $exception) {
    if ($archivoNuevo !== null && is_file($archivoNuevo)) {
        unlink($archivoNuevo);
    }

    error_log('BeatCell - módulos: ' . $exception->getMessage());

    $mensaje = [
        'tipo' => 'danger',
        'texto' => 'No se pudo completar la operación. '
            . 'Revisa la conexión y los permisos de la carpeta de videos.'
    ];
}

if ($moduloController !== null && $idCurso > 0) {
    try {
        $modulos = $moduloController->listarPorCurso($idCurso);
    } catch (Throwable $exception) {
        error_log('BeatCell - módulos (listado): ' . $exception->getMessage());
    }
}

$nombre = $datos['nombre'] ?? ($moduloEditar['nombre'] ?? '');
$descripcion = $datos['descripcion']
    ?? ($moduloEditar['descripcion'] ?? '');
$orden = $datos['orden'] ?? ($moduloEditar['orden'] ?? '');

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Módulos - <?= e($curso['nombre'] ?? 'BeatCell') ?></title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
</head>
<body class="bg-light">

<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Gestión de Módulos</h1>
            <div class="text-muted">
                Curso actual:
                <strong><?= e($curso['nombre'] ?? 'Ninguno seleccionado') ?></strong>
            </div>
        </div>

        <div class="d-flex gap-2 align-items-center">
            <form method="get" action="modulos.php" class="d-flex align-items-center gap-2">
                <label for="id_curso_select" class="form-label mb-0 fw-semibold text-nowrap">Curso:</label>
                <select name="id_curso" id="id_curso_select" class="form-select" onchange="this.form.submit()">
                    <?php if (empty($cursos)): ?>
                        <option value="0">No hay cursos disponibles</option>
                    <?php else: ?>
                        <?php foreach ($cursos as $c): ?>
                            <option value="<?= (int) $c['id_curso'] ?>" <?= (int) $c['id_curso'] === $idCurso ? 'selected' : '' ?>>
                                <?= e($c['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </form>

            <a href="cursos.php" class="btn btn-outline-secondary text-nowrap">Volver a cursos</a>
            <a href="panel.php" class="btn btn-outline-secondary text-nowrap">Panel</a>
        </div>
    </div>

    <?php if ($mensaje): ?>
        <div
            class="alert alert-<?= e($mensaje['tipo']) ?>"
            role="alert"
        >
            <?= e($mensaje['texto']) ?>
        </div>
    <?php endif; ?>

    <?php if (!$curso): ?>
        <div class="alert alert-warning">
            Por favor, selecciona un curso válido para gestionar sus módulos.
        </div>
    <?php else: ?>
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h2 class="h5">
                            <?= $moduloEditar
                                ? 'Editar módulo'
                                : 'Nuevo módulo' ?>
                        </h2>

                        <form method="post" enctype="multipart/form-data">
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= e($_SESSION['csrf_gestion']) ?>"
                            >

                            <input
                                type="hidden"
                                name="accion"
                                value="<?= $moduloEditar
                                    ? 'actualizar'
                                    : 'crear' ?>"
                            >

                            <input
                                type="hidden"
                                name="id_curso"
                                value="<?= $idCurso ?>"
                            >

                            <?php if ($moduloEditar): ?>
                                <input
                                    type="hidden"
                                    name="id_modulo"
                                    value="<?= (int) $moduloEditar['id_modulo'] ?>"
                                >
                            <?php endif; ?>

                            <div class="mb-3">
                                <label for="nombre" class="form-label">
                                    Nombre
                                </label>

                                <input
                                    id="nombre"
                                    type="text"
                                    name="nombre"
                                    class="form-control"
                                    maxlength="150"
                                    value="<?= e($nombre) ?>"
                                    required
                                >
                            </div>

                            <div class="mb-3">
                                <label for="descripcion" class="form-label">
                                    Descripción
                                </label>

                                <textarea
                                    id="descripcion"
                                    name="descripcion"
                                    class="form-control"
                                    rows="4"
                                ><?= e($descripcion) ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="orden" class="form-label">
                                    Orden
                                </label>

                                <input
                                    id="orden"
                                    type="number"
                                    name="orden"
                                    class="form-control"
                                    min="1"
                                    value="<?= e($orden) ?>"
                                    required
                                >
                            </div>

                            <div class="mb-3">
                                <label for="video" class="form-label">
                                    Video del módulo
                                </label>

                                <input
                                    id="video"
                                    type="file"
                                    name="video"
                                    class="form-control"
                                    accept="video/mp4,.mp4"
                                >

                                <div class="form-text">
                                    MP4, máximo 50 MB.
                                    Deja vacío para conservar el video actual.
                                    Si ocurre un error, selecciona nuevamente
                                    el archivo antes de guardar.
                                </div>

                                <?php if (!empty($moduloEditar['video_url'])): ?>
                                    <p class="small mt-2 mb-2">
                                        Este módulo tiene un video asignado.
                                    </p>

                                    <div class="form-check">
                                        <input
                                            id="quitar_video"
                                            type="checkbox"
                                            name="quitar_video"
                                            value="1"
                                            class="form-check-input"
                                            <?= isset($datos['quitar_video'])
                                                ? 'checked'
                                                : '' ?>
                                        >

                                        <label
                                            for="quitar_video"
                                            class="form-check-label"
                                        >
                                            Quitar video del módulo
                                        </label>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <?= $moduloEditar
                                    ? 'Guardar cambios'
                                    : 'Crear módulo' ?>
                            </button>

                            <?php if ($moduloEditar): ?>
                                <a
                                    href="modulos.php?id_curso=<?= $idCurso ?>"
                                    class="btn btn-outline-secondary"
                                >
                                    Cancelar
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body p-0">
                        <?php if (empty($modulos)): ?>
                            <p class="p-3 mb-0 text-muted">
                                Este curso todavía no tiene módulos.
                            </p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                    <tr>
                                        <th>Orden</th>
                                        <th>Módulo</th>
                                        <th>Video</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                    </thead>

                                    <tbody>
                                    <?php foreach ($modulos as $modulo): ?>
                                        <?php
                                        $activo = $modulo['estado'] === 'Activo';
                                        ?>

                                        <tr>
                                            <td>
                                                <?= (int) $modulo['orden'] ?>
                                            </td>

                                            <td>
                                                <strong>
                                                    <?= e($modulo['nombre']) ?>
                                                </strong>

                                                <?php if (!empty($modulo['descripcion'])): ?>
                                                    <div class="small text-muted">
                                                        <?= e($modulo['descripcion']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <?= !empty($modulo['video_url'])
                                                    ? 'Asignado'
                                                    : 'Sin video' ?>
                                            </td>

                                            <td>
                                                <?= e($modulo['estado']) ?>
                                            </td>

                                            <td class="text-end text-nowrap">
                                                <a
                                                    href="modulos.php?id_curso=<?= $idCurso ?>&editar=<?= (int) $modulo['id_modulo'] ?>"
                                                    class="btn btn-sm btn-outline-primary"
                                                >
                                                    Editar
                                                </a>

                                                <form
                                                    method="post"
                                                    class="d-inline"
                                                >
                                                    <input
                                                        type="hidden"
                                                        name="csrf_token"
                                                        value="<?= e($_SESSION['csrf_gestion']) ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="accion"
                                                        value="estado"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="id_curso"
                                                        value="<?= $idCurso ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="id_modulo"
                                                        value="<?= (int) $modulo['id_modulo'] ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="estado"
                                                        value="<?= $activo
                                                            ? 'Inactivo'
                                                            : 'Activo' ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="btn btn-sm btn-outline-<?= $activo
                                                            ? 'danger'
                                                            : 'success' ?>"
                                                    >
                                                        <?= $activo
                                                            ? 'Desactivar'
                                                            : 'Activar' ?>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

</body>
=======
<?php

require_once __DIR__ . '/../../src/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../src/Controllers/CursoController.php';
require_once __DIR__ . '/../../src/Controllers/ModuloController.php';

AuthMiddleware::requireRole(['Docente', 'Administrador'], '../auth/login.php');

if (empty($_SESSION['csrf_gestion'])) {
    $_SESSION['csrf_gestion'] = bin2hex(random_bytes(32));
}

function e($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

/** Borra un video local subido por esta pantalla (ruta ya validada). */
function eliminarVideoLocal(?string $ruta): void
{
    if (
        is_string($ruta) &&
        preg_match('#^public/uploads/videos/([a-f0-9]{32}\.mp4)$#', $ruta, $m) === 1
    ) {
        $archivo = __DIR__ . '/../../public/uploads/videos/' . $m[1];

        if (is_file($archivo)) {
            @unlink($archivo);
        }
    }
}

$idCurso = (int) ($_GET['id_curso'] ?? $_POST['id_curso'] ?? 0);
$idEditar = (int) ($_GET['editar'] ?? 0);
$datos = $_POST;
$mensaje = null;
$moduloEditar = null;
$cursoController = null;
$moduloController = null;
$cursos = [];
$curso = null;
$modulos = [];
$archivoNuevo = null;

try {
    $cursoController = new CursoController();
    $moduloController = new ModuloController();

    // 1. Obtener todos los cursos para el selector
    $cursos = method_exists($cursoController, 'obtenerTodos') 
        ? $cursoController->obtenerTodos() 
        : (method_exists($cursoController, 'listar') ? $cursoController->listar() : []);

    // 2. Si no viene id_curso por parámetro, tomar el ID del primer curso disponible
    if ($idCurso === 0 && !empty($cursos)) {
        $idCurso = (int) ($cursos[0]['id_curso'] ?? 0);
    }

    // 3. Obtener los datos del curso actual si existe idCurso
    if ($idCurso > 0) {
        $curso = $cursoController->obtenerPorId($idCurso);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Comprobar la sesión del formulario.
        $token = $_POST['csrf_token'] ?? '';

        if (
            !is_string($token) ||
            !hash_equals($_SESSION['csrf_gestion'], $token)
        ) {
            throw new InvalidArgumentException(
                'La sesión del formulario expiró. Recarga la página. '
                . 'Si subiste un video, comprueba el límite de carga de PHP.'
            );
        }

        $accion = $_POST['accion'] ?? '';
        $idModulo = (int) ($_POST['id_modulo'] ?? 0);

        if (!in_array($accion, ['crear', 'actualizar', 'estado'], true)) {
            throw new InvalidArgumentException('La acción no es válida.');
        }

        // Verificar que el módulo pertenece al curso.
        $moduloActual = null;

        if ($accion === 'actualizar' || $accion === 'estado') {
            $moduloActual = $moduloController->obtenerPorId($idModulo);

            if (
                !$moduloActual ||
                (int) $moduloActual['id_curso'] !== $idCurso
            ) {
                throw new InvalidArgumentException(
                    'El módulo no existe en este curso.'
                );
            }

            if ($accion === 'actualizar') {
                $moduloEditar = $moduloActual;
            }
        }

        if ($accion === 'crear' || $accion === 'actualizar') {
            $datosGuardar = $_POST;
            $datosGuardar['id_curso'] = $idCurso;

            // Conservar el video actual si no se selecciona otro.
            $datosGuardar['video_url'] =
                $moduloActual['video_url'] ?? null;

            if (isset($_POST['quitar_video'])) {
                $datosGuardar['video_url'] = null;
            }

            $video = $_FILES['video'] ?? null;

            if (
                $video !== null &&
                (
                    !is_array($video) ||
                    !isset($video['error']) ||
                    !is_int($video['error'])
                )
            ) {
                throw new InvalidArgumentException(
                    'El archivo de video no es válido.'
                );
            }

            if (
                $video !== null &&
                $video['error'] !== UPLOAD_ERR_NO_FILE
            ) {
                if ($video['error'] !== UPLOAD_ERR_OK) {
                    throw new InvalidArgumentException(
                        'No se pudo subir el video. '
                        . 'Revisa el límite de carga de PHP.'
                    );
                }

                $temporal = $video['tmp_name'] ?? '';

                if (
                    !is_string($temporal) ||
                    !is_uploaded_file($temporal)
                ) {
                    throw new InvalidArgumentException(
                        'La carga del archivo no es válida.'
                    );
                }

                // Validar tamaño y tipo real del archivo.
                $tamano = filesize($temporal);
                $tipo = (new finfo(FILEINFO_MIME_TYPE))->file($temporal);

                if (
                    !$tamano ||
                    $tamano > 50 * 1024 * 1024 ||
                    $tipo !== 'video/mp4'
                ) {
                    throw new InvalidArgumentException(
                        'Selecciona un video MP4 de hasta 50 MB.'
                    );
                }

                $carpeta = __DIR__ . '/../../public/uploads/videos';

                if (
                    !is_dir($carpeta) &&
                    !mkdir($carpeta, 0755, true) &&
                    !is_dir($carpeta)
                ) {
                    throw new RuntimeException(
                        'No se pudo crear la carpeta de videos.'
                    );
                }

                // Generar un nombre único para el archivo.
                $nombreArchivo = bin2hex(random_bytes(16)) . '.mp4';
                $archivoNuevo = $carpeta . '/' . $nombreArchivo;

                if (!move_uploaded_file($temporal, $archivoNuevo)) {
                    throw new RuntimeException(
                        'No se pudo guardar el video.'
                    );
                }

                $datosGuardar['video_url'] =
                    'public/uploads/videos/' . $nombreArchivo;
            }

            if ($accion === 'crear') {
                $moduloController->crear($datosGuardar);
                $resultado = 'creado';
            } else {
                if (
                    !$moduloController->actualizar(
                        $idModulo,
                        $datosGuardar
                    )
                ) {
                    throw new RuntimeException(
                        'No se pudo actualizar el módulo.'
                    );
                }

                $resultado = 'actualizado';

                $videoAnterior = $moduloActual['video_url'] ?? null;

                if ($videoAnterior !== ($datosGuardar['video_url'] ?? null)) {
                    eliminarVideoLocal($videoAnterior);
                }
            }

            // El archivo ya está asociado al módulo.
            $archivoNuevo = null;

            header(
                'Location: modulos.php?id_curso='
                . $idCurso . '&ok=' . $resultado
            );
            exit;
        }

        if ($accion === 'estado') {
            if (
                !$moduloController->cambiarEstado(
                    $idModulo,
                    $_POST['estado'] ?? ''
                )
            ) {
                throw new RuntimeException(
                    'No se pudo cambiar el estado del módulo.'
                );
            }

            header(
                'Location: modulos.php?id_curso='
                . $idCurso . '&ok=estado'
            );
            exit;
        }
    }

    if ($idEditar > 0 && $moduloEditar === null) {
        $moduloEditar = $moduloController->obtenerPorId($idEditar);

        if (!$moduloEditar || (int) $moduloEditar['id_curso'] !== $idCurso) {
            $moduloEditar = null;

            throw new InvalidArgumentException(
                'El módulo no existe en este curso.'
            );
        }
    }

    $mensajes = [
        'creado' => 'Módulo creado correctamente.',
        'actualizado' => 'Módulo actualizado correctamente.',
        'estado' => 'Estado del módulo actualizado.'
    ];

    $ok = $_GET['ok'] ?? '';

    if (is_string($ok) && isset($mensajes[$ok])) {
        $mensaje = [
            'tipo' => 'success',
            'texto' => $mensajes[$ok]
        ];
    }
} catch (InvalidArgumentException $exception) {
    if ($archivoNuevo !== null && is_file($archivoNuevo)) {
        unlink($archivoNuevo);
    }

    $mensaje = [
        'tipo' => 'danger',
        'texto' => $exception->getMessage()
    ];
} catch (Throwable $exception) {
    if ($archivoNuevo !== null && is_file($archivoNuevo)) {
        unlink($archivoNuevo);
    }

    error_log('BeatCell - módulos: ' . $exception->getMessage());

    $mensaje = [
        'tipo' => 'danger',
        'texto' => 'No se pudo completar la operación. '
            . 'Revisa la conexión y los permisos de la carpeta de videos.'
    ];
}

if ($moduloController !== null && $idCurso > 0) {
    try {
        $modulos = $moduloController->listarPorCurso($idCurso);
    } catch (Throwable $exception) {
        error_log('BeatCell - módulos (listado): ' . $exception->getMessage());
    }
}

$nombre = $datos['nombre'] ?? ($moduloEditar['nombre'] ?? '');
$descripcion = $datos['descripcion']
    ?? ($moduloEditar['descripcion'] ?? '');
$orden = $datos['orden'] ?? ($moduloEditar['orden'] ?? '');

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Módulos - <?= e($curso['nombre'] ?? 'BeatCell') ?></title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
</head>
<body class="bg-light">

<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Gestión de Módulos</h1>
            <div class="text-muted">
                Curso actual:
                <strong><?= e($curso['nombre'] ?? 'Ninguno seleccionado') ?></strong>
            </div>
        </div>

        <div class="d-flex gap-2 align-items-center">
            <form method="get" action="modulos.php" class="d-flex align-items-center gap-2">
                <label for="id_curso_select" class="form-label mb-0 fw-semibold text-nowrap">Curso:</label>
                <select name="id_curso" id="id_curso_select" class="form-select" onchange="this.form.submit()">
                    <?php if (empty($cursos)): ?>
                        <option value="0">No hay cursos disponibles</option>
                    <?php else: ?>
                        <?php foreach ($cursos as $c): ?>
                            <option value="<?= (int) $c['id_curso'] ?>" <?= (int) $c['id_curso'] === $idCurso ? 'selected' : '' ?>>
                                <?= e($c['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </form>

            <a href="cursos.php" class="btn btn-outline-secondary text-nowrap">Volver a cursos</a>
            <a href="panel.php" class="btn btn-outline-secondary text-nowrap">Panel</a>
        </div>
    </div>

    <?php if ($mensaje): ?>
        <div
            class="alert alert-<?= e($mensaje['tipo']) ?>"
            role="alert"
        >
            <?= e($mensaje['texto']) ?>
        </div>
    <?php endif; ?>

    <?php if (!$curso): ?>
        <div class="alert alert-warning">
            Por favor, selecciona un curso válido para gestionar sus módulos.
        </div>
    <?php else: ?>
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h2 class="h5">
                            <?= $moduloEditar
                                ? 'Editar módulo'
                                : 'Nuevo módulo' ?>
                        </h2>

                        <form method="post" enctype="multipart/form-data">
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= e($_SESSION['csrf_gestion']) ?>"
                            >

                            <input
                                type="hidden"
                                name="accion"
                                value="<?= $moduloEditar
                                    ? 'actualizar'
                                    : 'crear' ?>"
                            >

                            <input
                                type="hidden"
                                name="id_curso"
                                value="<?= $idCurso ?>"
                            >

                            <?php if ($moduloEditar): ?>
                                <input
                                    type="hidden"
                                    name="id_modulo"
                                    value="<?= (int) $moduloEditar['id_modulo'] ?>"
                                >
                            <?php endif; ?>

                            <div class="mb-3">
                                <label for="nombre" class="form-label">
                                    Nombre
                                </label>

                                <input
                                    id="nombre"
                                    type="text"
                                    name="nombre"
                                    class="form-control"
                                    maxlength="150"
                                    value="<?= e($nombre) ?>"
                                    required
                                >
                            </div>

                            <div class="mb-3">
                                <label for="descripcion" class="form-label">
                                    Descripción
                                </label>

                                <textarea
                                    id="descripcion"
                                    name="descripcion"
                                    class="form-control"
                                    rows="4"
                                ><?= e($descripcion) ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="orden" class="form-label">
                                    Orden
                                </label>

                                <input
                                    id="orden"
                                    type="number"
                                    name="orden"
                                    class="form-control"
                                    min="1"
                                    value="<?= e($orden) ?>"
                                    required
                                >
                            </div>

                            <div class="mb-3">
                                <label for="video" class="form-label">
                                    Video del módulo
                                </label>

                                <input
                                    id="video"
                                    type="file"
                                    name="video"
                                    class="form-control"
                                    accept="video/mp4,.mp4"
                                >

                                <div class="form-text">
                                    MP4, máximo 50 MB.
                                    Deja vacío para conservar el video actual.
                                    Si ocurre un error, selecciona nuevamente
                                    el archivo antes de guardar.
                                </div>

                                <?php if (!empty($moduloEditar['video_url'])): ?>
                                    <p class="small mt-2 mb-2">
                                        Este módulo tiene un video asignado.
                                    </p>

                                    <div class="form-check">
                                        <input
                                            id="quitar_video"
                                            type="checkbox"
                                            name="quitar_video"
                                            value="1"
                                            class="form-check-input"
                                            <?= isset($datos['quitar_video'])
                                                ? 'checked'
                                                : '' ?>
                                        >

                                        <label
                                            for="quitar_video"
                                            class="form-check-label"
                                        >
                                            Quitar video del módulo
                                        </label>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <?= $moduloEditar
                                    ? 'Guardar cambios'
                                    : 'Crear módulo' ?>
                            </button>

                            <?php if ($moduloEditar): ?>
                                <a
                                    href="modulos.php?id_curso=<?= $idCurso ?>"
                                    class="btn btn-outline-secondary"
                                >
                                    Cancelar
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body p-0">
                        <?php if (empty($modulos)): ?>
                            <p class="p-3 mb-0 text-muted">
                                Este curso todavía no tiene módulos.
                            </p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                    <tr>
                                        <th>Orden</th>
                                        <th>Módulo</th>
                                        <th>Video</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                    </thead>

                                    <tbody>
                                    <?php foreach ($modulos as $modulo): ?>
                                        <?php
                                        $activo = $modulo['estado'] === 'Activo';
                                        ?>

                                        <tr>
                                            <td>
                                                <?= (int) $modulo['orden'] ?>
                                            </td>

                                            <td>
                                                <strong>
                                                    <?= e($modulo['nombre']) ?>
                                                </strong>

                                                <?php if (!empty($modulo['descripcion'])): ?>
                                                    <div class="small text-muted">
                                                        <?= e($modulo['descripcion']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <?= !empty($modulo['video_url'])
                                                    ? 'Asignado'
                                                    : 'Sin video' ?>
                                            </td>

                                            <td>
                                                <?= e($modulo['estado']) ?>
                                            </td>

                                            <td class="text-end text-nowrap">
                                                <a
                                                    href="modulos.php?id_curso=<?= $idCurso ?>&editar=<?= (int) $modulo['id_modulo'] ?>"
                                                    class="btn btn-sm btn-outline-primary"
                                                >
                                                    Editar
                                                </a>

                                                <form
                                                    method="post"
                                                    class="d-inline"
                                                >
                                                    <input
                                                        type="hidden"
                                                        name="csrf_token"
                                                        value="<?= e($_SESSION['csrf_gestion']) ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="accion"
                                                        value="estado"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="id_curso"
                                                        value="<?= $idCurso ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="id_modulo"
                                                        value="<?= (int) $modulo['id_modulo'] ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="estado"
                                                        value="<?= $activo
                                                            ? 'Inactivo'
                                                            : 'Activo' ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="btn btn-sm btn-outline-<?= $activo
                                                            ? 'danger'
                                                            : 'success' ?>"
                                                    >
                                                        <?= $activo
                                                            ? 'Desactivar'
                                                            : 'Activar' ?>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

</body>
>>>>>>> upstream/main
</html>