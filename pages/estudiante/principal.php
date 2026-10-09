<?php
// La sesión contiene los datos guardados al iniciar sesión.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Cache-Control: no-store');

// Solo un estudiante autenticado puede entrar a esta página.
if (empty($_SESSION['id_usuario'])) {
    header('Location: ../auth/login.php');
    exit;
}
if (($_SESSION['rol'] ?? '') !== 'Estudiante') {
    header('Location: ../../public/index.php');
    exit;
}

// Se escapan los datos antes de mostrarlos en HTML.
$usuario = $_SESSION['usuario'] ?? [];
$nombre = trim(($usuario['nombres'] ?? '') . ' ' . ($usuario['apellidos'] ?? ''));
$nombre = $nombre !== '' ? $nombre : 'Estudiante';
$escapar = static fn($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');

// Token para que el cierre de sesión provenga del formulario del sistema.
if (empty($_SESSION['csrf_logout'])) {
    $_SESSION['csrf_logout'] = bin2hex(random_bytes(32));
}

// Consulta únicamente los datos del estudiante autenticado.
require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../../src/Models/ResultadoModel.php';
$resumen = null;
$ultimosResultados = [];
$errorDatos = '';
try {
    $db = (new Database())->connect();
    $idUsuario = (int) $_SESSION['id_usuario'];
    $consulta = $db->prepare(
        "SELECT
            (SELECT COUNT(DISTINCT id_actividad) FROM resultados WHERE id_usuario = ?) AS realizadas,
            (SELECT COUNT(*) FROM actividades a
                WHERE a.estado = 'Activo'
                AND NOT EXISTS (SELECT 1 FROM resultados r
                    WHERE r.id_actividad = a.id_actividad AND r.id_usuario = ?)) AS pendientes,
            (SELECT COUNT(*) FROM certificados WHERE id_usuario = ?) AS certificados"
    );
    $consulta->execute([$idUsuario, $idUsuario, $idUsuario]);
    $datosResumen = $consulta->fetch();
    $modeloResultado = new ResultadoModel($db);
    $ultimosResultados = array_slice($modeloResultado->obtenerPorUsuario($idUsuario), 0, 5);
    $resumen = $datosResumen;
} catch (Throwable $e) {
    error_log('BeatCell - inicio del estudiante: ' . $e->getMessage());
    $errorDatos = 'No se pudo cargar tu resumen. Verifica la conexión e inténtalo de nuevo.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio del estudiante | BeatCell</title>
    <link rel="stylesheet" href="../../public/assets/css/principal.css">
</head>
<body>
<main class="contenedor">
    <header>
        <div>
            <p class="marca">BeatCell</p>
            <p class="subtitulo">Academia de Tecnología Celular</p>
        </div>
        <form action="../auth/logout.php" method="POST">
            <input type="hidden" name="csrf_logout" value="<?= $escapar($_SESSION['csrf_logout']) ?>">
            <button type="submit">Cerrar sesión</button>
        </form>
    </header>

    <section class="bienvenida" aria-labelledby="bienvenida">
        <h1 id="bienvenida">Bienvenido, <?= $escapar($nombre) ?></h1>
        <p>Desde aquí puedes consultar tus actividades y revisar el avance de tu aprendizaje.</p>
        <div class="datos">
            <p><strong>Código:</strong> <?= $escapar($usuario['codigo'] ?? 'No disponible') ?><br>
            <strong>Correo:</strong> <?= $escapar($usuario['correo'] ?? 'No disponible') ?><br>
            <strong>Rol:</strong> Estudiante</p>
        </div>
    </section>

    <?php if ($errorDatos !== ''): ?>
        <p class="error" role="alert"><?= $escapar($errorDatos) ?></p>
    <?php else: ?>
        <section class="resumen" aria-label="Resumen del estudiante">
            <div class="contador"><strong><?= (int) ($resumen['realizadas'] ?? 0) ?></strong>Actividades realizadas</div>
            <div class="contador"><strong><?= (int) ($resumen['pendientes'] ?? 0) ?></strong>Actividades pendientes</div>
            <div class="contador"><strong><?= (int) ($resumen['certificados'] ?? 0) ?></strong>Certificados obtenidos</div>
        </section>
        <p class="datos">Una actividad se considera realizada cuando tiene un resultado, aunque esté desaprobada. Las pendientes son actividades activas que aún no has realizado.</p>
    <?php endif; ?>

    <nav class="opciones" aria-label="Opciones del estudiante">

        <article class="tarjeta">
            <h2>Cursos</h2>
            <p>Explora cursos activos, módulos y temas.</p>
            <a class="boton" href="cursos.php">Ver cursos</a>
        </article>

        <article class="tarjeta">
            <h2>Actividades</h2>
            <p>Consulta las actividades disponibles y sus indicaciones.</p>
            <a class="boton" href="actividades.php">Ver actividades</a>
        </article>
        
        <article class="tarjeta">
            <h2>Mi progreso</h2>
            <p>Revisa tu porcentaje de avance por curso y por tema.</p>
            <a class="boton" href="progreso.php">Ver mi progreso</a>
        </article>

        <article class="tarjeta">
            <h2>Historial</h2>
            <p>Consulta tus resultados y el detalle de las respuestas.</p>
            <a class="boton" href="historial.php">Ver mi historial</a>
        </article>

        <article class="tarjeta">
            <h2>Rutas de Aprendizaje</h2>
            <p>Reviza tu Aprendizaje</p>
            <a class="boton" href="ruta.php">Ver mi Rutas</a>
        </article>
    </nav>
    <?php if ($errorDatos === ''): ?>
        <section class="tarjeta resultados" aria-labelledby="resultados">
            <h2 id="resultados">Últimos resultados</h2>
            <?php if ($ultimosResultados === []): ?>
                <p>Todavía no tienes resultados registrados.</p>
            <?php else: ?>
                <div class="tabla">
                    <table>
                        <caption>Tus cinco resultados más recientes</caption>
                        <thead><tr><th scope="col">Actividad</th><th scope="col">Puntaje</th><th scope="col">Porcentaje</th><th scope="col">Estado</th><th scope="col">Fecha</th></tr></thead>
                        <tbody>
                        <?php foreach ($ultimosResultados as $resultado): ?>
                            <tr>
                                <td><?= $escapar($resultado['actividad']) ?></td>
                                <td><?= (int) $resultado['puntaje_obtenido'] ?> / <?= (int) $resultado['puntaje_total'] ?></td>
                                <td><?= $escapar(number_format((float) $resultado['porcentaje'], 2)) ?> %</td>
                                <td><?= $escapar($resultado['estado']) ?></td>
                                <td><?= $escapar($resultado['fecha']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
    <footer>Panel del estudiante · BeatCell</footer>
</main>
</body>
</html>
