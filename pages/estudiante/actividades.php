<?php
require_once __DIR__ . '/../../src/Middleware/AuthMiddleware.php';
$sesionUsuario = AuthMiddleware::requireRole(['Estudiante'], '../auth/login.php');
require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../../src/Models/TiempoActividad.php';
$actividades = [];
$errorCarga = null;
try {
    $db = (new Database())->connect();
    $stmt = $db->prepare("SELECT a.id_actividad, a.titulo, a.descripcion, a.tipo, a.modo_tiempo, a.tiempo_limite, a.puntaje_minimo,
        (SELECT COUNT(*) FROM preguntas p WHERE p.id_actividad = a.id_actividad AND p.estado = 'Activo') AS preguntas_registradas,
        EXISTS (
            SELECT 1 FROM resultados r
            WHERE r.id_actividad = a.id_actividad AND r.id_usuario = ?
        ) AS realizada
        FROM actividades a
        WHERE a.estado = 'Activo'
            AND EXISTS (
                SELECT 1 FROM preguntas p
                WHERE p.id_actividad = a.id_actividad AND p.estado = 'Activo'
            )
        ORDER BY a.id_actividad DESC");
    $stmt->execute([(int) $sesionUsuario['id_usuario']]);
    $actividades = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('BeatCell - error al cargar actividades del estudiante: ' . $e->getMessage());
    $errorCarga = 'No se pudieron cargar las actividades. Inténtalo de nuevo más tarde.';
}
$e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Actividades | BeatCell</title>
    <link rel="stylesheet" href="../../public/assets/css/actividades.css">
    <link rel="stylesheet" href="../../public/assets/css/estudiante.css?v=<?= filemtime(__DIR__ . '/../../public/assets/css/estudiante.css') ?>">
</head>
<body>
<?php require __DIR__ . '/_navegacion.php'; ?>
<main class="contenedor-actividades">
    <section class="encabezado-actividades"><h1>Actividades</h1><p>Selecciona una actividad para comenzar.</p></section>
    <?php if ($errorCarga): ?><p class="mensaje-error" role="alert"><?= $e($errorCarga) ?></p><?php endif; ?>
    <section class="lista-actividades">
    <?php if (!$errorCarga && !$actividades): ?>
        <p>No hay actividades activas disponibles.</p>
    <?php elseif ($actividades): foreach ($actividades as $actividad): ?>
        <article class="tarjeta-actividad<?= (int) $actividad['realizada'] === 1 ? ' actividad-realizada' : '' ?>">
            <?php if ((int) $actividad['realizada'] === 1): ?>
                <span class="estado-actividad realizada" aria-label="Actividad realizada">
                    <span aria-hidden="true">✓</span> Realizada
                </span>
            <?php endif; ?>
            <span class="actividad-tipo"><?= $e($actividad['tipo']) ?></span>
            <h2><?= $e($actividad['titulo']) ?></h2>
            <?php if (!empty($actividad['descripcion'])): ?><p><?= $e($actividad['descripcion']) ?></p><?php endif; ?>
            <p>Preguntas: <?= (int)$actividad['preguntas_registradas'] ?></p>
            <p>Tiempo: <?= $e(TiempoActividad::describir($actividad['tiempo_limite'], $actividad['modo_tiempo'] ?? null))  ?></p>
            <a class="btn-resolver" href="actividad.php?id=<?= (int)$actividad['id_actividad'] ?>">
                <?= (int) $actividad['realizada'] === 1 ? 'Ver estado' : 'Resolver actividad' ?>
            </a>
        </article>
    <?php endforeach; endif; ?>
    </section>
</main>
</body></html>
