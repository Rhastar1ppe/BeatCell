<?php

require_once __DIR__ . '/../../src/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../../src/Controllers/TemaController.php';
require_once __DIR__ . '/../../src/Controllers/ModuloController.php';

AuthMiddleware::requireRole(['Docente', 'Administrador'], '../auth/login.php');

if (empty($_SESSION['csrf_gestion'])) {
    $_SESSION['csrf_gestion'] = bin2hex(random_bytes(32));
}

function e($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

$temaController = null;
$moduloController = null;
$modulos = [];
$temas = [];
$mensaje = null;
$datos = $_POST;
$temaEditar = null;

try {
    $db = (new Database())->connect();
    $temaController = new TemaController($db);
    $moduloController = new ModuloController();

    $modulos = $moduloController->listarTodos();
    $temas = $temaController->listarTodos();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!is_string($token) || !hash_equals($_SESSION['csrf_gestion'], $token)) {
            throw new InvalidArgumentException('La sesión del formulario expiró. Recarga la página.');
        }

        $accion = $_POST['accion'] ?? '';
        $idTema = (int) ($_POST['id_tema'] ?? 0);

        if ($accion === 'crear') {
            $resultado = $temaController->crear($_POST);
            $mensaje = $resultado['success']
                ? ['tipo' => 'success', 'texto' => $resultado['mensaje']]
                : ['tipo' => 'danger', 'texto' => $resultado['mensaje']];
        } elseif ($accion === 'actualizar') {
            $resultado = $temaController->actualizar($idTema, $_POST);
            $mensaje = $resultado['success']
                ? ['tipo' => 'success', 'texto' => $resultado['mensaje']]
                : ['tipo' => 'danger', 'texto' => $resultado['mensaje']];
        } elseif ($accion === 'estado') {
            $resultado = $temaController->cambiarEstado($idTema, $_POST['estado'] ?? '');
            $mensaje = $resultado['success']
                ? ['tipo' => 'success', 'texto' => $resultado['mensaje']]
                : ['tipo' => 'danger', 'texto' => $resultado['mensaje']];
        }

        $modulos = $moduloController->listarTodos();
        $temas = $temaController->listarTodos();
    }

    $idEditar = (int) ($_GET['editar'] ?? 0);
    if ($idEditar > 0) {
        $temaEditar = $temaController->buscarPorId($idEditar);
        if (!$temaEditar) {
            throw new InvalidArgumentException('El tema no existe.');
        }
    }
} catch (InvalidArgumentException $e) {
    $mensaje = ['tipo' => 'danger', 'texto' => $e->getMessage()];
} catch (Throwable $e) {
    error_log('BeatCell - temas: ' . $e->getMessage());
    $mensaje = ['tipo' => 'danger', 'texto' => 'No se pudo cargar la gestión de temas.'];
}

$nombre = $datos['nombre'] ?? ($temaEditar['nombre'] ?? '');
$descripcion = $datos['descripcion'] ?? ($temaEditar['descripcion'] ?? '');
$material = $datos['material_apoyo'] ?? ($temaEditar['material_apoyo'] ?? '');
$orden = $datos['orden'] ?? ($temaEditar['orden'] ?? '');
$idModulo = (int) ($datos['id_modulo'] ?? ($temaEditar['id_modulo'] ?? 0));

$modulosPorCurso = [];
foreach ($modulos as $modulo) {
    $curso = $modulo['curso_nombre'] ?? 'Sin curso';
    $modulosPorCurso[$curso][] = $modulo;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Temas - BeatCell</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Temas</h1>
            <p class="text-muted mb-0">Organiza los temas de cada módulo.</p>
        </div>
        <a href="panel.php" class="btn btn-outline-secondary">Volver al panel</a>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= e($mensaje['tipo']) ?>"><?= e($mensaje['texto']) ?></div>
    <?php endif; ?>

    <?php if (empty($modulos)): ?>
        <div class="alert alert-warning">
            Primero crea al menos un módulo para poder registrar temas.
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-3"><?= $temaEditar ? 'Editar tema' : 'Nuevo tema' ?></h2>

                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_gestion']) ?>">
                        <input type="hidden" name="accion" value="<?= $temaEditar ? 'actualizar' : 'crear' ?>">
                        <?php if ($temaEditar): ?>
                            <input type="hidden" name="id_tema" value="<?= (int) $temaEditar['id_tema'] ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label">Módulo</label>
                            <select name="id_modulo" class="form-select" required <?= empty($modulos) ? 'disabled' : '' ?>>
                                <option value="">Selecciona un módulo</option>
                                <?php foreach ($modulosPorCurso as $curso => $lista): ?>
                                    <optgroup label="<?= e($curso) ?>">
                                        <?php foreach ($lista as $modulo): ?>
                                            <option value="<?= (int) $modulo['id_modulo'] ?>"
                                                <?= $idModulo === (int) $modulo['id_modulo'] ? 'selected' : '' ?>>
                                                <?= e($modulo['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nombre del tema</label>
                            <input type="text" name="nombre" class="form-control" maxlength="200"
                                   value="<?= e($nombre) ?>" placeholder="Ej. Variables y tipos de datos" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Descripción</label>
                            <textarea name="descripcion" class="form-control" rows="3"
                                      placeholder="Explicación breve del tema"><?= e($descripcion) ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Material de apoyo</label>
                            <textarea name="material_apoyo" class="form-control" rows="3"
                                      placeholder="Ej. Video, enlace o material para repasar"><?= e($material) ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Orden</label>
                            <input type="number" name="orden" class="form-control" min="1"
                                   value="<?= e($orden) ?>" required>
                        </div>

                        <button class="btn btn-primary" <?= empty($modulos) ? 'disabled' : '' ?>>
                            <?= $temaEditar ? 'Guardar cambios' : 'Crear tema' ?>
                        </button>

                        <?php if ($temaEditar): ?>
                            <a href="temas.php" class="btn btn-outline-secondary">Cancelar</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <?php if (empty($temas)): ?>
                        <div class="p-4 text-muted">
                            Todavía no hay temas. Puedes empezar con <strong>Variables y tipos de datos</strong>,
                            <strong>Condicionales</strong> u <strong>Operaciones básicas</strong>.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>Curso</th>
                                    <th>Módulo</th>
                                    <th>Tema</th>
                                    <th>Orden</th>
                                    <th>Estado</th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($temas as $tema): ?>
                                    <?php $activo = $tema['estado'] === 'Activo'; ?>
                                    <tr>
                                        <td><?= e($tema['curso']) ?></td>
                                        <td><?= e($tema['modulo']) ?></td>
                                        <td>
                                            <strong><?= e($tema['nombre']) ?></strong>
                                            <?php if (!empty($tema['descripcion'])): ?>
                                                <div class="small text-muted"><?= e($tema['descripcion']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= (int) $tema['orden'] ?></td>
                                        <td><?= e($tema['estado']) ?></td>
                                        <td class="text-end text-nowrap">
                                            <a href="temas.php?editar=<?= (int) $tema['id_tema'] ?>"
                                               class="btn btn-sm btn-outline-primary">Editar</a>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_gestion']) ?>">
                                                <input type="hidden" name="accion" value="estado">
                                                <input type="hidden" name="id_tema" value="<?= (int) $tema['id_tema'] ?>">
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
