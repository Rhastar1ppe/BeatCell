<?php

require_once __DIR__ . '/../../src/Middleware/AuthMiddleware.php';
$sesionUsuario = AuthMiddleware::requireRole(['Estudiante'], '../auth/login.php');
require_once __DIR__ . '/../../src/Controllers/ProgresoController.php';

$controller = new ProgresoController();
$controller->mostrar((int) $sesionUsuario['id_usuario']);
