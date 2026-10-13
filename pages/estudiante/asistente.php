<?php
// Evitar que advertencias o errores de PHP corrompan la respuesta JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

try {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    // 1. Validar que el estudiante haya iniciado sesión
    $usuario = $_SESSION['usuario'] ?? null;
    if (!$usuario || empty($usuario['id_usuario'])) {
        echo json_encode(['ok' => false, 'error' => 'Sesión no válida. Inicia sesión nuevamente.']);
        exit;
    }

    // 2. Validar token CSRF del chat
    $csrfPost = $_POST['csrf_chat'] ?? '';
    if (empty($_SESSION['csrf_chat']) || !hash_equals($_SESSION['csrf_chat'], $csrfPost)) {
        echo json_encode(['ok' => false, 'error' => 'Token de seguridad inválido.']);
        exit;
    }

    // 3. Incluir la clase Database desde database/database.php
    $dbPath = __DIR__ . '/../../database/database.php';
    if (!file_exists($dbPath)) {
        echo json_encode(['ok' => false, 'error' => 'No se encontró el archivo database/database.php']);
        exit;
    }

    require_once $dbPath;

    $db = null;
    if (class_exists('Database')) {
        $database = new Database();
        $db = $database->connect();
    }

    if (!($db instanceof PDO)) {
        echo json_encode(['ok' => false, 'error' => 'No se pudo obtener una instancia PDO válida desde la clase Database.']);
        exit;
    }

    // 4. Incluir el servicio del chatbot
    $servicePath = __DIR__ . '/../../src/Services/ChatBotService.php';
    if (!file_exists($servicePath)) {
        echo json_encode(['ok' => false, 'error' => 'No se encontró ChatBotService.php en src/Services/']);
        exit;
    }

    require_once $servicePath;

    $idUsuario = (int) $usuario['id_usuario'];
    $chatService = new ChatBotService($db, $idUsuario);

    $accion = $_POST['accion'] ?? '';

    // 5. Manejar acción de inicio del chat
    if ($accion === 'iniciar') {
        $saludo = $chatService->saludoInicial();
        $avisos = $chatService->contarAvisos();

        echo json_encode([
            'ok' => true,
            'historial' => [
                ['remitente' => 'Bot', 'mensaje' => $saludo['texto']]
            ],
            'botones' => $saludo['botones'],
            'avisos' => $avisos
        ]);
        exit;
    }

    // 6. Manejar envío de mensajes del usuario
    if ($accion === 'mensaje') {
        $mensajeUsuario = trim($_POST['mensaje'] ?? '');
        
        if ($mensajeUsuario === '') {
            echo json_encode(['ok' => false, 'error' => 'El mensaje no puede estar vacío.']);
            exit;
        }

        $respuesta = $chatService->responder($mensajeUsuario);
        $avisos = $chatService->contarAvisos();

        echo json_encode([
            'ok' => true,
            'respuesta' => [
                'texto' => $respuesta['texto'],
                'enlaces' => $respuesta['enlaces'],
                'botones' => $respuesta['botones']
            ],
            'avisos' => $avisos
        ]);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Acción no reconocida.']);

} catch (Throwable $e) {
    echo json_encode([
        'ok' => false,
        'error' => 'Error en el servidor: ' . $e->getMessage()
    ]);
}