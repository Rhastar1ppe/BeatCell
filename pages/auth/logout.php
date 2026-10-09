<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Solo se permite cerrar sesión desde un formulario POST válido.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Utiliza el botón Cerrar sesión.');
}
$token = $_POST['csrf_logout'] ?? '';
if (!is_string($token) || empty($_SESSION['csrf_logout']) || !hash_equals($_SESSION['csrf_logout'], $token)) {
    http_response_code(403);
    exit('Solicitud no válida. Vuelve a la página de inicio e inténtalo otra vez.');
}

// Elimina los datos de sesión del navegador.
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $parametros = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $parametros['path'], $parametros['domain'], $parametros['secure'], $parametros['httponly']);
}
session_destroy();
header('Location: login.php');
exit;
