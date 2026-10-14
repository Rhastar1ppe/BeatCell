<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/Middleware/AuthMiddleware.php';

AuthMiddleware::requireRole(
    ['Estudiante'],
    '../auth/login.php'
);

header('Cache-Control: no-store');

require_once __DIR__ . '/../../src/Controllers/RutaAprendizajeController.php';

$e = static fn($valor): string => htmlspecialchars(
    (string) $valor,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

$rutas = [];
$rutaSeleccionada = null;
$error = '';
$mostrarDetalle = isset($_GET['id']);

try {
    $idRuta = null;

    if ($mostrarDetalle) {
        $valor = $_GET['id'];

        if (!is_string($valor)) {
            throw new InvalidArgumentException(
                'La ruta no existe o no está disponible.'
            );
        }

        $idRuta = filter_var(
            $valor,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($idRuta === false) {
            throw new InvalidArgumentException(
                'La ruta no existe o no está disponible.'
            );
        }
    }

    $controller = new RutaAprendizajeController();

    if ($idRuta !== null) {
        $rutaSeleccionada = $controller
            ->obtenerDetalleParaEstudiante($idRuta);

        if ($rutaSeleccionada === null) {
            throw new InvalidArgumentException(
                'La ruta no existe o no está disponible.'
            );
        }
    } else {
        $rutas = $controller->listarActivas();
    }
} catch (InvalidArgumentException $ex) {
    http_response_code(404);
    $error = $ex->getMessage();
} catch (Throwable $ex) {
    error_log('BeatCell - rutas estudiante: ' . $ex->getMessage());
    http_response_code(503);
    $error = 'No se pudieron cargar las rutas. Inténtalo más tarde.';
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e(
        $rutaSeleccionada['nombre'] ?? 'Rutas de aprendizaje'
    ) ?> | BeatCell</title>
    <link rel="stylesheet" href="../../public/assets/css/rutas.css">
</head>
<body>
<main class="contenedor-rutas">
    <nav class="navegacion-rutas" aria-label="Navegación">
        <a href="principal.php">Volver al inicio</a>
        <a href="cursos.php">Ver cursos</a>

        <?php if ($mostrarDetalle): ?>
            <a href="ruta.php">Ver todas las rutas</a>
        <?php endif; ?>
    </nav>

    <?php if ($error !== ''): ?>
        <header class="encabezado-rutas">
            <h1>Ruta no disponible</h1>
        </header>

        <p class="mensaje-error" role="alert"><?= $e($error) ?></p>

    <?php elseif ($rutaSeleccionada !== null): ?>
        <header class="encabezado-rutas">
            <h1><?= $e($rutaSeleccionada['nombre']) ?></h1>

            <?php if (!empty($rutaSeleccionada['descripcion'])): ?>
                <p><?= nl2br($e($rutaSeleccionada['descripcion'])) ?></p>
            <?php endif; ?>

            <p>
                <strong>Nivel:</strong>
                <?= $e($rutaSeleccionada['nivel'] ?? 'Sin nivel') ?>
            </p>
        </header>

        <section class="tarjeta-ruta" aria-labelledby="titulo-cursos">
            <h2 id="titulo-cursos">Cursos de esta ruta</h2>
            <p>
                Sigue el orden recomendado para avanzar en tu aprendizaje.
                Puedes abrir cualquiera de los cursos disponibles.
            </p>

            <?php if (empty($rutaSeleccionada['cursos'])): ?>
                <p>Esta ruta todavía no tiene cursos activos disponibles.</p>
            <?php else: ?>
                <?php foreach ($rutaSeleccionada['cursos'] as $curso): ?>
                    <article class="curso-ruta">
                        <h3>
                            <?= (int) $curso['orden'] ?>.
                            <?= $e($curso['nombre']) ?>
                        </h3>

                        <?php if (!empty($curso['descripcion'])): ?>
                            <p><?= nl2br($e($curso['descripcion'])) ?></p>
                        <?php endif; ?>

                        <a href="curso.php?id=<?= (int) $curso['id_curso'] ?>">
                            Abrir curso
                        </a>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

    <?php else: ?>
        <header class="encabezado-rutas">
            <h1>Rutas de aprendizaje</h1>
            <p>
                Encuentra cursos agrupados por nivel y organizados
                en un orden recomendado.
            </p>
        </header>

        <section class="lista-rutas" aria-label="Rutas disponibles">
            <?php if (!$rutas): ?>
                <div class="tarjeta-ruta">
                    <p>Todavía no hay rutas de aprendizaje disponibles.</p>
                </div>
            <?php else: ?>
                <?php foreach ($rutas as $ruta): ?>
                    <article class="tarjeta-ruta">
                        <h2><?= $e($ruta['nombre']) ?></h2>

                        <?php if (!empty($ruta['descripcion'])): ?>
                            <p><?= nl2br($e($ruta['descripcion'])) ?></p>
                        <?php endif; ?>

                        <p>
                            <strong>Nivel:</strong>
                            <?= $e($ruta['nivel'] ?? 'Sin nivel') ?>
                        </p>

                        <a href="ruta.php?id=<?= (int) $ruta['id_ruta'] ?>">
                            Ver ruta y sus cursos
                        </a>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>
</body>
</html>