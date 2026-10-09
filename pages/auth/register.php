<?php
require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../../src/Models/UsuarioModel.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['csrf_register'])) {
    $_SESSION['csrf_register'] = bin2hex(random_bytes(32));
}

$errores = [];
$datos = [
    'dni' => '',
    'nombres' => '',
    'apellidos' => '',
    'email' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_register'], $token)) {
        $errores[] = 'La sesión del formulario expiró. Recarga la página.';
    }

    foreach ($datos as $campo => $_) {
        $datos[$campo] = trim((string) ($_POST[$campo] ?? ''));
    }
    $password = (string) ($_POST['password'] ?? '');

    if (!preg_match('/^[0-9]{8}$/D', $datos['dni'])) {
        $errores[] = 'El DNI debe contener exactamente 8 números.';
    }
    if ($datos['nombres'] === '' || mb_strlen($datos['nombres']) > 100) {
        $errores[] = 'Ingresa nombres válidos (máximo 100 caracteres).';
    }
    if ($datos['apellidos'] === '' || mb_strlen($datos['apellidos']) > 100) {
        $errores[] = 'Ingresa apellidos válidos (máximo 100 caracteres).';
    }
    if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($datos['email']) > 200) {
        $errores[] = 'Ingresa un correo electrónico válido.';
    }
    if (strlen($password) < 8) {
        $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
    }

    if (!$errores) {
        try {
            $db = (new Database())->connect();
            $stmt = $db->prepare(
                'SELECT dni, correo FROM usuarios WHERE dni = ? OR correo = ? LIMIT 1'
            );
            $stmt->execute([$datos['dni'], $datos['email']]);
            $usuarioExistente = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($usuarioExistente) {
                if ($usuarioExistente['dni'] === $datos['dni']) {
                    $errores[] = 'Ya existe una cuenta registrada con ese DNI.';
                } else {
                    $errores[] = 'Ya existe una cuenta registrada con ese correo.';
                }
            } else {
                $codigo = 'BC' . strtoupper(bin2hex(random_bytes(6)));
                $usuario = new UsuarioModel($db);
                $usuario->crear(
                    $codigo,
                    $datos['dni'],
                    $datos['nombres'],
                    $datos['apellidos'],
                    $datos['email'],
                    $password
                );

                header('Location: login.php?registro=exitoso');
                exit;
            }
        } catch (Throwable $e) {
            error_log('BeatCell - error al registrar usuario: ' . $e->getMessage());
            $errores[] = 'No se pudo completar el registro. Verifica la conexión con la base de datos e inténtalo de nuevo.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BeatCell - Registro de Usuario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../public/assets/css/login.css">
</head>
<body>
<div class="bg-image">
    <div class="auth-card">
        <div class="brand-logo">
            <h2>BeatCell</h2>
            <p>Academia de Tecnología Celular</p>
        </div>

        <h4 class="text-center mb-4">Crear Cuenta</h4>
        <?php if ($errores): ?>
            <div class="alert alert-danger" role="alert">
                <?php foreach ($errores as $error): ?>
                    <div><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <form action="register.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_register'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="mb-3">
                <label class="form-label">DNI</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-card-text"></i></span>
                    <input type="text" name="dni" class="form-control" inputmode="numeric" maxlength="8" pattern="[0-9]{8}" title="Ingresa exactamente 8 números" value="<?= htmlspecialchars($datos['dni'], ENT_QUOTES, 'UTF-8') ?>" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 8)" required>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nombres</label>
                    <input type="text" name="nombres" class="form-control" value="<?= htmlspecialchars($datos['nombres'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Apellidos</label>
                    <input type="text" name="apellidos" class="form-control" value="<?= htmlspecialchars($datos['apellidos'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Correo Electrónico</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($datos['email'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Contraseña</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" name="password" class="form-control" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Solicitar Registro</button>
        </form>
        <div class="auth-footer">
            <p>¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a></p>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
