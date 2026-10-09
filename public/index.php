<?php
session_start();

function beatcell_redirect(string $path): void {
    header('Location: ' . $path);
    exit;
}

$rol = $_SESSION['usuario']['rol']
    ?? $_SESSION['rol']
    ?? $_SESSION['usuario_rol']
    ?? null;

if (is_array($rol)) {
    $rol = $rol['rol'] ?? null;
}

if (empty($rol)) {
    beatcell_redirect('../pages/auth/login.php');
}

switch ($rol) {
    case 'Estudiante':
        beatcell_redirect('../pages/estudiante/principal.php');
        break;

    case 'Docente':
        beatcell_redirect('../pages/gestion/panel.php');
        break;

    case 'Administrador':
        beatcell_redirect('../pages/gestion/panel.php');
        break;

    default:
        beatcell_redirect('../pages/auth/login.php');
        break;
}
