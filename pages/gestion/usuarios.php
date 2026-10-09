<?php

require_once __DIR__ . '/../../src/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../src/Controllers/UsuarioController.php';

$sesionUsuario = AuthMiddleware::requireRole(['Administrador'], '../auth/login.php');

if (empty($_SESSION['csrf_gestion'])) {
    $_SESSION['csrf_gestion'] = bin2hex(random_bytes(32));
}

function e($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

$controller = null;
$usuarios = [];
$usuarioEditar = null;
$mensaje = null;
$enviado = [];
$idEditar = (int) ($_GET['editar'] ?? 0);

try {
    $controller = new UsuarioController();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!is_string($token) || !hash_equals($_SESSION['csrf_gestion'], $token)) {
            throw new InvalidArgumentException('La sesión del formulario expiró. Recarga la página.');
        }

        $accion = $_POST['accion'] ?? '';
        $id = (int) ($_POST['id_usuario'] ?? 0);

        if ($id === (int) $sesionUsuario['id_usuario']) {
            if ($accion === 'actualizar' && ($_POST['rol'] ?? '') !== 'Administrador') {
                throw new InvalidArgumentException('No puedes cambiar tu propio rol de administrador.');
            }
            if ($accion === 'estado' && ($_POST['estado'] ?? '') !== 'Activo') {
                throw new InvalidArgumentException('No puedes desactivar tu propia cuenta.');
            }
        }

        if ($accion === 'crear') {
            $controller->crear($_POST);
            header('Location: usuarios.php?ok=creado');
            exit;
        }

        if ($accion === 'actualizar') {
            $controller->actualizar($id, $_POST);
            header('Location: usuarios.php?ok=actualizado');
            exit;
        }

        if ($accion === 'password') {
            $controller->cambiarPassword($id, (string) ($_POST['password'] ?? ''));
            header('Location: usuarios.php?ok=password');
            exit;
        }

        if ($accion === 'estado') {
            $controller->cambiarEstado($id, (string) ($_POST['estado'] ?? ''));
            header('Location: usuarios.php?ok=estado');
            exit;
        }
    }

    $usuarios = $controller->listar();
    if ($idEditar > 0) {
        $usuarioEditar = $controller->obtenerPorId($idEditar);
    }

    $mensajes = [
        'creado' => 'Usuario creado correctamente.',
        'actualizado' => 'Usuario actualizado correctamente.',
        'password' => 'Contraseña actualizada correctamente.',
        'estado' => 'Estado actualizado correctamente.'
    ];

    if (isset($mensajes[$_GET['ok'] ?? ''])) {
        $mensaje = ['tipo' => 'success', 'texto' => $mensajes[$_GET['ok']]];
    }
} catch (InvalidArgumentException $e) {
    $mensaje = ['tipo' => 'danger', 'texto' => $e->getMessage()];
    $enviado = $_POST;
    if (($enviado['accion'] ?? '') === 'actualizar') {
        $idEditar = (int) ($enviado['id_usuario'] ?? 0);
        $usuarioEditar = $controller->obtenerPorId($idEditar);
    }
} catch (Throwable $e) {
    error_log('BeatCell - usuarios: ' . $e->getMessage());
    $mensaje = ['tipo' => 'danger', 'texto' => 'No se pudo completar la operación.'];
}

$datos = $enviado ?: ($usuarioEditar ?: []);
$editandoUsuarioActual = $usuarioEditar
    && (int) $usuarioEditar['id_usuario'] === (int) $sesionUsuario['id_usuario'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - BeatCell</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body class="bg-light">
<div class="container py-4">
    <header class="pb-3 mb-4 border-bottom d-flex justify-content-between align-items-center">
        <h1><i class="bi bi-people-fill text-primary"></i> Gestión de Usuarios</h1>
        <a href="panel.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Volver al Panel
        </a>
    </header>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= e($mensaje['tipo']) ?>" role="alert">
            <?= e($mensaje['texto']) ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <?= $usuarioEditar ? 'Editar usuario' : 'Nuevo usuario' ?>
                </div>
                <div class="card-body">
                    <form method="post" action="usuarios.php">
                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_gestion']) ?>">
                        <input type="hidden" name="accion" value="<?= $usuarioEditar ? 'actualizar' : 'crear' ?>">
                        <?php if ($usuarioEditar): ?>
                            <input type="hidden" name="id_usuario" value="<?= (int) $usuarioEditar['id_usuario'] ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label">Código</label>
                            <input type="text" name="codigo" class="form-control" maxlength="20" value="<?= e($datos['codigo'] ?? '') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">DNI</label>
                            <input type="text" name="dni" class="form-control" maxlength="8" value="<?= e($datos['dni'] ?? '') ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nombres</label>
                            <input type="text" name="nombres" class="form-control" maxlength="100" value="<?= e($datos['nombres'] ?? '') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Apellidos</label>
                            <input type="text" name="apellidos" class="form-control" maxlength="100" value="<?= e($datos['apellidos'] ?? '') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Correo</label>
                            <input type="email" name="correo" class="form-control" maxlength="200" value="<?= e($datos['correo'] ?? '') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Rol</label>
                            <select name="rol" class="form-select" <?= $editandoUsuarioActual ? 'disabled' : '' ?>>
                                <?php foreach (['Estudiante', 'Docente', 'Administrador'] as $rol): ?>
                                    <option value="<?= $rol ?>" <?= ($datos['rol'] ?? 'Estudiante') === $rol ? 'selected' : '' ?>><?= $rol ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($editandoUsuarioActual): ?>
                                <input type="hidden" name="rol" value="Administrador">
                                <div class="form-text">No puedes cambiar tu propio rol.</div>
                            <?php endif; ?>
                        </div>

                        <?php if (!$usuarioEditar): ?>
                            <div class="mb-3">
                                <label class="form-label">Contraseña</label>
                                <input type="password" name="password" class="form-control" minlength="8" required>
                            </div>
                        <?php endif; ?>

                        <button class="btn btn-primary" type="submit">
                            <?= $usuarioEditar ? 'Guardar cambios' : 'Crear usuario' ?>
                        </button>
                        <?php if ($usuarioEditar): ?>
                            <a href="usuarios.php" class="btn btn-outline-secondary">Cancelar</a>
                        <?php endif; ?>
                    </form>

                    <?php if ($usuarioEditar): ?>
                        <hr>
                        <form method="post" action="usuarios.php">
                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_gestion']) ?>">
                            <input type="hidden" name="accion" value="password">
                            <input type="hidden" name="id_usuario" value="<?= (int) $usuarioEditar['id_usuario'] ?>">
                            <label class="form-label">Nueva contraseña</label>
                            <div class="input-group">
                                <input type="password" name="password" class="form-control" minlength="8" required>
                                <button class="btn btn-outline-primary" type="submit">Cambiar</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header">Usuarios registrados</div>
                <div class="card-body p-0">
                    <?php if (!$usuarios): ?>
                        <p class="text-muted p-3 mb-0">No hay usuarios registrados.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                <tr>
                                    <th>Usuario</th>
                                    <th>Correo</th>
                                    <th>Rol</th>
                                    <th>Estado</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <?php $activo = $usuario['estado'] === 'Activo'; ?>
                                    <tr>
                                        <td>
                                            <strong><?= e($usuario['nombres'] . ' ' . $usuario['apellidos']) ?></strong>
                                            <div class="small text-muted"><?= e($usuario['codigo']) ?></div>
                                        </td>
                                        <td><?= e($usuario['correo']) ?></td>
                                        <td><?= e($usuario['rol']) ?></td>
                                        <td>
                                            <span class="badge text-bg-<?= $activo ? 'success' : 'secondary' ?>">
                                                <?= e($usuario['estado']) ?>
                                            </span>
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <a href="usuarios.php?editar=<?= (int) $usuario['id_usuario'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-pencil"></i> Editar
                                            </a>
                                            <?php if ((int) $usuario['id_usuario'] === (int) $sesionUsuario['id_usuario']): ?>
                                                <span class="badge text-bg-info">Tu cuenta</span>
                                            <?php else: ?>
                                                <form method="post" action="usuarios.php" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_gestion']) ?>">
                                                    <input type="hidden" name="accion" value="estado">
                                                    <input type="hidden" name="id_usuario" value="<?= (int) $usuario['id_usuario'] ?>">
                                                    <input type="hidden" name="estado" value="<?= $activo ? 'Inactivo' : 'Activo' ?>">
                                                    <button class="btn btn-sm btn-outline-<?= $activo ? 'danger' : 'success' ?>" type="submit">
                                                        <?= $activo ? 'Desactivar' : 'Activar' ?>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
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
