<?php

require_once __DIR__ . '/../../src/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../src/Controllers/CursoController.php';

AuthMiddleware::requireRole(['Docente', 'Administrador'], '../auth/login.php');

if (empty($_SESSION['csrf_gestion'])) {
    $_SESSION['csrf_gestion'] = bin2hex(random_bytes(32));
}

function e($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

$mensaje = null;
$datos = $_POST;
$cursoEditar = null;
$idEditar = (int) ($_GET['editar'] ?? 0);

try {
    $controller = new CursoController();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!is_string($token) || !hash_equals($_SESSION['csrf_gestion'], $token)) {
            throw new InvalidArgumentException('La sesión del formulario expiró. Recarga la página.');
        }

        $accion = $_POST['accion'] ?? '';
        $idCurso = (int) ($_POST['id_curso'] ?? 0);

        if ($accion === 'crear') {
            $controller->crear($_POST);
            header('Location: cursos.php?ok=creado');
            exit;
        }

        if ($accion === 'actualizar') {
            $controller->actualizar($idCurso, $_POST);
            header('Location: cursos.php?ok=actualizado');
            exit;
        }

        if ($accion === 'estado') {
            $controller->cambiarEstado($idCurso, $_POST['estado'] ?? '');
            header('Location: cursos.php?ok=estado');
            exit;
        }
    }

    $cursos = $controller->listar();

    if ($idEditar > 0) {
        $cursoEditar = $controller->obtenerPorId($idEditar);

        if (!$cursoEditar) {
            $mensaje = ['tipo' => 'danger', 'texto' => 'El curso no existe.'];
            $idEditar = 0;
        }
    }

    if (isset($_GET['ok'])) {
        $mensajes = [
            'creado' => 'Curso creado correctamente.',
            'actualizado' => 'Curso actualizado correctamente.',
            'estado' => 'Estado del curso actualizado.'
        ];

        if (isset($mensajes[$_GET['ok']])) {
            $mensaje = ['tipo' => 'success', 'texto' => $mensajes[$_GET['ok']]];
        }
    }
} catch (InvalidArgumentException $e) {
    $mensaje = ['tipo' => 'danger', 'texto' => $e->getMessage()];
    $idEditar = (int) ($datos['id_curso'] ?? 0);

    if ($idEditar > 0) {
        $cursoEditar = [
            'id_curso' => $idEditar,
            'nombre' => $datos['nombre'] ?? '',
            'descripcion' => $datos['descripcion'] ?? ''
        ];
    }

    $cursos = $controller->listar();
} catch (Throwable $e) {
    error_log('BeatCell - cursos: ' . $e->getMessage());
    $mensaje = ['tipo' => 'danger', 'texto' => 'No se pudo completar la operación.'];
    $cursos = [];
}

$nombre = $datos['nombre'] ?? ($cursoEditar['nombre'] ?? '');
$descripcion = $datos['descripcion'] ?? ($cursoEditar['descripcion'] ?? '');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Cursos - BeatCell</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Gestión de Cursos</h1>
        <a href="panel.php" class="btn btn-outline-secondary">Volver al panel</a>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= e($mensaje['tipo']) ?>">
            <?= e($mensaje['texto']) ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <h2 class="h5"><?= $cursoEditar ? 'Editar curso' : 'Nuevo curso' ?></h2>

                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_gestion']) ?>">
                        <input type="hidden" name="accion" value="<?= $cursoEditar ? 'actualizar' : 'crear' ?>">

                        <?php if ($cursoEditar): ?>
                            <input type="hidden" name="id_curso" value="<?= (int) $cursoEditar['id_curso'] ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label">Nombre</label>
                            <input type="text" name="nombre" class="form-control"
                                   maxlength="100" value="<?= e($nombre) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Descripción</label>
                            <textarea name="descripcion" class="form-control" rows="4"><?= e($descripcion) ?></textarea>
                        </div>

                        <button class="btn btn-primary">
                            <?= $cursoEditar ? 'Guardar cambios' : 'Crear curso' ?>
                        </button>

                        <?php if ($cursoEditar): ?>
                            <a href="cursos.php" class="btn btn-outline-secondary">Cancelar</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-body p-0">
                    <?php if (empty($cursos)): ?>
                        <p class="p-3 mb-0 text-muted">No hay cursos registrados.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>Curso</th>
                                    <th>Módulos</th>
                                    <th>Estado</th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($cursos as $curso): ?>
                                    <?php $activo = $curso['estado'] === 'Activo'; ?>
                                    <tr>
                                        <td>
                                            <strong><?= e($curso['nombre']) ?></strong>
                                            <?php if (!empty($curso['descripcion'])): ?>
                                                <div class="small text-muted"><?= e($curso['descripcion']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= (int) $curso['total_modulos'] ?></td>
                                        <td><?= e($curso['estado']) ?></td>
                                        <td class="text-end text-nowrap">
                                            <a href="modulos.php?id_curso=<?= (int) $curso['id_curso'] ?>"
                                               class="btn btn-sm btn-outline-secondary">Módulos</a>

                                            <a href="cursos.php?editar=<?= (int) $curso['id_curso'] ?>"
                                               class="btn btn-sm btn-outline-primary">Editar</a>

                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_gestion']) ?>">
                                                <input type="hidden" name="accion" value="estado">
                                                <input type="hidden" name="id_curso" value="<?= (int) $curso['id_curso'] ?>">
                                                <input type="hidden" name="estado" value="<?= $activo ? 'Inactivo' : 'Activo' ?>">
                                                <button class="btn btn-sm btn-outline-<?= $activo ? 'danger' : 'success' ?>">
                                                    <?= $activo ? 'Desactivar' : 'Activar' ?>
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
</div>

</body>
</html>
