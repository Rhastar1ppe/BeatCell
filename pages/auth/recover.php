<?php
require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../../src/Models/UsuarioModel.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['csrf_recover'])) {
    $_SESSION['csrf_recover'] = bin2hex(random_bytes(32));
}

$db = (new Database())->connect();
$modeloUsuario = new UsuarioModel($db);

$mensaje = '';
$tipoMensaje = 'danger';
$tokenGenerado = '';
$emailSolicitado = '';
$tokenParaReset = trim((string) ($_GET['token'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!is_string($csrfToken) || !hash_equals($_SESSION['csrf_recover'], $csrfToken)) {
        http_response_code(403);
        exit('La sesión del formulario expiró. Recarga la página.');
    }

    $accion = $_POST['accion'] ?? 'request';

    if ($accion === 'request') {
        $emailSolicitado = trim((string) ($_POST['email'] ?? ''));

        if (!filter_var($emailSolicitado, FILTER_VALIDATE_EMAIL)) {
            $mensaje = 'Ingresa un correo electrónico válido.';
        } else {
            $usuario = $modeloUsuario->obtenerPorCorreo($emailSolicitado);

            if ($usuario !== null) {
                $tokenRecuperacion = bin2hex(random_bytes(32));
                $expira = date('Y-m-d H:i:s', time() + 3600);

                if ($modeloUsuario->guardarTokenRecuperacion($emailSolicitado, $tokenRecuperacion, $expira)) {
                    $baseUrl = rtrim((string) getenv('BEATCELL_BASE_URL'), '/');
                    $correoRemitente = (string) getenv('BEATCELL_MAIL_FROM');
                    $esUrlValida = filter_var($baseUrl, FILTER_VALIDATE_URL) !== false
                        && in_array(parse_url($baseUrl, PHP_URL_SCHEME), ['http', 'https'], true);
                    $mostrarTokenLocal = getenv('BEATCELL_ENV') === 'development'
                        && getenv('BEATCELL_SHOW_RECOVERY_TOKEN') === 'true';

                    if ($mostrarTokenLocal) {
                        $tokenGenerado = $tokenRecuperacion;
                    } elseif ($esUrlValida && filter_var($correoRemitente, FILTER_VALIDATE_EMAIL) && function_exists('mail')) {
                        $enlace = $baseUrl . '/pages/auth/recover.php?token=' . rawurlencode($tokenRecuperacion);
                        $asunto = 'Recuperación de contraseña de BeatCell';
                        $cuerpo = "Usa este enlace para cambiar tu contraseña. Expira en una hora:\n\n" . $enlace;
                        $cabeceras = 'From: ' . $correoRemitente . "\r\n" . 'Content-Type: text/plain; charset=UTF-8';

                        if (!@mail($emailSolicitado, $asunto, $cuerpo, $cabeceras)) {
                            error_log('BeatCell - no se pudo enviar el correo de recuperación.');
                        }
                    } else {
                        error_log('BeatCell - configura BEATCELL_BASE_URL y BEATCELL_MAIL_FROM para enviar recuperaciones.');
                    }
                }
            }

            $mensaje = 'Si el correo está registrado, recibirás instrucciones para continuar con la recuperación.';
            $tipoMensaje = 'info';
        }
    }

    if ($accion === 'reset') {
        $tokenParaReset = trim((string) ($_POST['token'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($tokenParaReset === '') {
            $mensaje = 'No se encontró el token de recuperación.';
        } elseif (strlen($password) < 8) {
            $mensaje = 'La nueva contraseña debe tener al menos 8 caracteres.';
        } elseif ($password !== $confirmPassword) {
            $mensaje = 'Las contraseñas no coinciden.';
        } else {
            try {
                $usuario = $modeloUsuario->obtenerPorToken($tokenParaReset);
                if ($usuario === null) {
                    $mensaje = 'El token de recuperación no es válido o ya expiró.';
                } else {
                    $expira = $usuario['password_reset_expira'] ?? null;
                    if (!is_string($expira) || $expira === '' || strtotime($expira) < time()) {
                        $mensaje = 'El enlace de recuperación ya expiró. Solicita uno nuevo.';
                    } elseif ($modeloUsuario->cambiarPasswordPorToken($tokenParaReset, $password)) {
                        $mensaje = 'Tu contraseña fue actualizada correctamente. Ya puedes iniciar sesión.';
                        $tipoMensaje = 'success';
                        $tokenParaReset = '';
                    } else {
                        $mensaje = 'No se pudo actualizar la contraseña. Inténtalo de nuevo.';
                    }
                }
            } catch (Throwable $e) {
                error_log('BeatCell - error al recuperar contraseña: ' . $e->getMessage());
                $mensaje = 'Ocurrió un error al procesar la recuperación.';
            }
        }
    }
}
$e = static fn($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BeatCell - Recuperar Contraseña</title>
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

        <h4 class="text-center mb-3">Recuperar contraseña</h4>

        <?php if ($mensaje !== ''): ?>
            <div class="alert alert-<?= $e($tipoMensaje) ?>" role="alert"><?= $e($mensaje) ?></div>
        <?php endif; ?>

        <?php if ($tokenGenerado !== ''): ?>
            <div class="alert alert-info" role="alert">
                Token de recuperación (modo local):
                <strong><?= $e($tokenGenerado) ?></strong>
                <p class="mb-0">Usa este valor en el formulario de cambio de contraseña.</p>
            </div>
        <?php elseif ($tokenParaReset !== ''): ?>
            <div class="alert alert-warning" role="alert">
                Se detectó un token de recuperación. Completa la nueva contraseña.
            </div>
        <?php endif; ?>

        <?php if ($mensaje === 'Tu contraseña fue actualizada correctamente. Ya puedes iniciar sesión.'): ?>
            <div class="auth-footer">
                <a href="login.php">Ir al login</a>
            </div>
        <?php else: ?>
            <?php if ($tokenGenerado !== '' || $tokenParaReset !== ''): ?>
                <form action="recover.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $e($_SESSION['csrf_recover']) ?>">
                    <input type="hidden" name="accion" value="reset">
                    <input type="hidden" name="token" value="<?= $e($tokenParaReset !== '' ? $tokenParaReset : $tokenGenerado) ?>">

                    <div class="mb-3">
                        <label class="form-label">Nueva contraseña</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" name="password" class="form-control" placeholder="Mínimo 8 caracteres" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Confirmar contraseña</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                            <input type="password" name="confirm_password" class="form-control" placeholder="Repite la contraseña" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">Cambiar contraseña</button>
                </form>
            <?php else: ?>
                <p class="text-center text-muted mb-4">Ingresa tu correo para generar un enlace de recuperación.</p>
                <form action="recover.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $e($_SESSION['csrf_recover']) ?>">
                    <input type="hidden" name="accion" value="request">
                    <div class="mb-3">
                        <label class="form-label">Correo Electrónico</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input type="email" name="email" class="form-control" value="<?= $e($emailSolicitado) ?>" placeholder="correo@ejemplo.com" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Enviar instrucciones</button>
                </form>
            <?php endif; ?>

            <div class="auth-footer">
                <a href="login.php">Volver al login</a>
            </div>
        <?php endif; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
