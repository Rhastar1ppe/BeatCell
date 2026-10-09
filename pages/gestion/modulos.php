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

$idCurso = (int) ($_GET['id_curso'] ?? $_POST['id_curso'] ?? 0);
$idEditar = (int) ($_GET['editar'] ?? 0);
$datos = $_POST;
$mensaje = null;
$moduloEditar = null;
$cursoController = null;
$moduloController = null;

try {
    $cursoController = new CursoController();
    $moduloController = new ModuloController();

    $curso = $cursoController->obtenerPorId($idCurso);

    if (!$curso) {
        throw new InvalidArgumentException('El curso no existe.');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!is_string($token) || !hash_equals($_SESSION['csrf_gestion'], $token)) {
            throw new InvalidArgumentException('La sesión del formulario expiró. Recarga la página.');
        }

        $accion = $_POST['accion'] ?? '';
        $idModulo = (int) ($_POST['id_modulo'] ?? 0);

        if ($accion === 'crear') {
            $moduloController->crear($_POST);
            header('Location: modulos.php?id_curso=' . $idCurso . '&ok=creado');
            exit;
        }

        if ($accion === 'actualizar') {
            $moduloController->actualizar($idModulo, $_POST);
            header('Location: modulos.php?id_curso=' . $idCurso . '&ok=actualizado');
            exit;
        }

        if ($accion === 'estado') {
            $moduloController->cambiarEstado($idModulo, $_POST['estado'] ?? '');
            header('Location: modulos.php?id_curso=' . $idCurso . '&ok=estado');
            exit;
        }
    }

    if ($idEditar > 0) {
        $moduloEditar = $moduloController->obtenerPorId($idEditar);

        if (!$moduloEditar || (int) $moduloEditar['id_curso'] !== $idCurso) {
            throw new InvalidArgumentException('El módulo no existe en este curso.');
        }
    }

    $modulos = $moduloController->listarPorCurso($idCurso);

    if (isset($_GET['ok'])) {
        $mensajes = [
            'creado' => 'Módulo creado correctamente.',
            'actualizado' => 'Módulo actualizado correctamente.',
            'estado' => 'Estado del módulo actualizado.'
        ];

        if (isset($mensajes[$_GET['ok']])) {
            $mensaje = ['tipo' => 'success', 'texto' => $mensajes[$_GET['ok']]];
        }
    }
} catch (InvalidArgumentException $e) {
    $mensaje = ['tipo' => 'danger', 'texto' => $e->getMessage()];
    $modulos = $moduloController !== null
        ? $moduloController->listarPorCurso($idCurso)
        : [];
} catch (Throwable $e) {
    error_log('BeatCell - modulos: ' . $e->getMessage());
    $mensaje = ['tipo' => 'danger', 'texto' => 'No se pudo completar la operación.'];
    $modulos = [];
}

$nombre = $datos['nombre'] ?? ($moduloEditar['nombre'] ?? '');
$descripcion = $datos['descripcion'] ?? ($moduloEditar['descripcion'] ?? '');
$orden = $datos['orden'] ?? ($moduloEditar['orden'] ?? '');

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Módulos - <?= e($curso['nombre'] ?? 'BeatCell') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Módulos</h1>
            <div class="text-muted"><?= e($curso['nombre'] ?? '') ?></div>
        </div>
        <a href="cursos.php" class="btn btn-outline-secondary">Volver a cursos</a>
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
                    <h2 class="h5"><?= $moduloEditar ? 'Editar módulo' : 'Nuevo módulo' ?></h2>

                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_gestion']) ?>">
                        <input type="hidden" name="accion" value="<?= $moduloEditar ? 'actualizar' : 'crear' ?>">
                        <input type="hidden" name="id_curso" value="<?= $idCurso ?>">

                        <?php if ($moduloEditar): ?>
                            <input type="hidden" name="id_modulo" value="<?= (int) $moduloEditar['id_modulo'] ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label">Nombre</label>
                            <input type="text" name="nombre" class="form-control"
                                   maxlength="150" value="<?= e($nombre) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Descripción</label>
                            <textarea name="descripcion" class="form-control" rows="4"><?= e($descripcion) ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Orden</label>
                            <input type="number" name="orden" class="form-control"
                                   min="1" value="<?= e($orden) ?>" required>
                        </div>

                        <button class="btn btn-primary">
                            <?= $moduloEditar ? 'Guardar cambios' : 'Crear módulo' ?>
                        </button>

                        <?php if ($moduloEditar): ?>
                            <a href="modulos.php?id_curso=<?= $idCurso ?>"
                               class="btn btn-outline-secondary">Cancelar</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-body p-0">
                    <?php if (empty($modulos)): ?>
                        <p class="p-3 mb-0 text-muted">Este curso todavía no tiene módulos.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>Orden</th>
                                    <th>Módulo</th>
                                    <th>Estado</th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($modulos as $modulo): ?>
                                    <?php $activo = $modulo['estado'] === 'Activo'; ?>
                                    <tr>
                                        <td><?= (int) $modulo['orden'] ?></td>
                                        <td>
                                            <strong><?= e($modulo['nombre']) ?></strong>
                                            <?php if (!empty($modulo['descripcion'])): ?>
                                                <div class="small text-muted"><?= e($modulo['descripcion']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= e($modulo['estado']) ?></td>
                                        <td class="text-end text-nowrap">
                                            <a href="modulos.php?id_curso=<?= $idCurso ?>&editar=<?= (int) $modulo['id_modulo'] ?>"
                                               class="btn btn-sm btn-outline-primary">Editar</a>

                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_gestion']) ?>">
                                                <input type="hidden" name="accion" value="estado">
                                                <input type="hidden" name="id_curso" value="<?= $idCurso ?>">
                                                <input type="hidden" name="id_modulo" value="<?= (int) $modulo['id_modulo'] ?>">
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
