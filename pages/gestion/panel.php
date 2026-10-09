<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/Middleware/AuthMiddleware.php';
$sesionUsuario = AuthMiddleware::requireRole(['Docente', 'Administrador'], '../auth/login.php');
require_once __DIR__ . '/../../database/database.php';

if (empty($_SESSION['csrf_logout'])) {
    $_SESSION['csrf_logout'] = bin2hex(random_bytes(32));
}

// Escapa un valor para imprimirlo de forma segura dentro del HTML.
function e($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

// -----------------------------------------------------
// Cifras del sistema (si la base no responde, se muestran guiones)
// -----------------------------------------------------
$cifras = [
    'cursos'      => null,
    'temas'       => null,
    'actividades' => null,
    'preguntas'   => null,
    'estudiantes' => null,
];

try {
    $db = (new Database())->connect();
    $fila = $db->query(
        "SELECT
            (SELECT COUNT(*) FROM cursos WHERE estado = 'Activo')       AS cursos,
            (SELECT COUNT(*) FROM temas WHERE estado = 'Activo')        AS temas,
            (SELECT COUNT(*) FROM actividades WHERE estado = 'Activo')  AS actividades,
            (SELECT COUNT(*) FROM preguntas WHERE estado = 'Activo')    AS preguntas,
            (SELECT COUNT(*) FROM usuarios WHERE rol = 'Estudiante')    AS estudiantes"
    )->fetch();

    if (is_array($fila)) {
        foreach ($cifras as $clave => $_) {
            $cifras[$clave] = (int) $fila[$clave];
        }
    }
} catch (Throwable $e) {
    error_log('BeatCell - panel: ' . $e->getMessage());
}

// Etiquetas y adornos de las cifras.
$etiquetasCifras = [
    'cursos'      => ['Cursos activos', 'bi-journal-bookmark'],
    'temas'       => ['Temas activos', 'bi-bookmarks'],
    'actividades' => ['Actividades activas', 'bi-journal-check'],
    'preguntas'   => ['Preguntas activas', 'bi-question-circle'],
    'estudiantes' => ['Estudiantes', 'bi-people'],
];

// -----------------------------------------------------
// Secciones del panel.
// Una tarjeta se vuelve enlace sola cuando su archivo ya tiene contenido;
// mientras esté vacío se muestra como "En desarrollo".
// -----------------------------------------------------
$secciones = [
    'Contenido académico' => [
        ['cursos.php',      'Cursos',      'bi-journal-bookmark', 'Crea los cursos y activa o desactiva su publicación.'],
        ['modulos.php',     'Módulos',     'bi-collection',       'Divide cada curso en módulos y define su orden.'],
        ['temas.php',       'Temas',       'bi-bookmarks',        'Organiza los temas de cada módulo y su material de apoyo.'],
        ['actividades.php', 'Actividades', 'bi-journal-check',    'Configura cuestionarios, prácticas y evaluaciones.'],
        ['preguntas.php',   'Preguntas',   'bi-question-circle',  'Registra preguntas con opciones, imágenes y explicación.'],
    ],
    'Seguimiento' => [
        ['rutas.php',       'Rutas de aprendizaje', 'bi-signpost-split', 'Arma rutas que ordenan varios cursos por nivel.'],
        ['progreso.php',    'Progreso',             'bi-graph-up',       'Consulta el avance de cada estudiante por curso y tema.'],
        ['resultados.php',  'Resultados',           'bi-clipboard-data', 'Revisa los intentos y notas de cada actividad.'],
    ],
    'Administración' => [
        ['usuarios.php',    'Usuarios',    'bi-person-gear',      'Administra estudiantes, docentes y administradores.'],
    ],
];

if ($sesionUsuario['rol'] !== 'Administrador') {
    unset($secciones['Administración']);
}

// Indica si el archivo de una sección ya tiene contenido.
function paginaLista(string $archivo): bool
{
    $ruta = __DIR__ . '/' . $archivo;
    return is_file($ruta) && (int) filesize($ruta) > 0;
}
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de gestión - BeatCell</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .tarjeta-modulo { transition: transform .15s ease, box-shadow .15s ease; }
        a.tarjeta-modulo:hover { transform: translateY(-3px); box-shadow: 0 .6rem 1.2rem rgba(0, 0, 0, .12) !important; }
        a.tarjeta-modulo:focus-visible { outline: 3px solid #0d6efd; outline-offset: 2px; }
        .tarjeta-modulo.en-desarrollo { background: #f4f5f7; }
        .icono-modulo {
            width: 2.75rem; height: 2.75rem; flex: none;
            display: flex; align-items: center; justify-content: center;
            border-radius: .75rem; font-size: 1.35rem;
            background: #e8f0ff; color: #0d6efd;
        }
        .en-desarrollo .icono-modulo { background: #e3e6ea; color: #6c757d; }
        @media (prefers-reduced-motion: reduce) { .tarjeta-modulo { transition: none; } }
    </style>
</head>
<body class="bg-light">
<div class="container py-4">

    <header class="pb-3 mb-4 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h1 class="h3 mb-0 fw-bold">BeatCell</h1>
            <p class="text-muted mb-0">Panel de gestión</p>
        </div>
        <form action="../auth/logout.php" method="POST">
            <input type="hidden" name="csrf_logout" value="<?= e($_SESSION['csrf_logout']) ?>">
            <button type="submit" class="btn btn-outline-secondary">
                <i class="bi bi-box-arrow-left"></i> Cerrar sesión
            </button>
        </form>
    </header>

    <!-- Cifras -->
    <section aria-label="Resumen del sistema" class="mb-5">
        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-5 g-3">
            <?php foreach ($etiquetasCifras as $clave => [$etiqueta, $icono]): ?>
                <div class="col">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <i class="bi <?= e($icono) ?> text-primary fs-4" aria-hidden="true"></i>
                            <p class="display-6 fw-bold mb-0">
                                <?= $cifras[$clave] === null ? '—' : (int) $cifras[$clave] ?>
                            </p>
                            <p class="text-muted small mb-0"><?= e($etiqueta) ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($cifras['cursos'] === null): ?>
            <p class="text-danger small mt-2 mb-0">
                No se pudo leer la base de datos. Verifica que MySQL esté encendido y que la base beatcell esté importada.
            </p>
        <?php endif; ?>
    </section>

    <!-- Secciones -->
    <?php foreach ($secciones as $titulo => $modulos): ?>
        <section class="mb-5" aria-labelledby="seccion-<?= e(md5($titulo)) ?>">
            <h2 class="h5 mb-3" id="seccion-<?= e(md5($titulo)) ?>"><?= e($titulo) ?></h2>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">
                <?php foreach ($modulos as [$archivo, $nombre, $icono, $descripcion]): ?>
                    <?php $lista = paginaLista($archivo); ?>
                    <div class="col">
                        <?php if ($lista): ?>
                            <a href="<?= e($archivo) ?>" class="card tarjeta-modulo shadow-sm h-100 text-decoration-none text-body">
                        <?php else: ?>
                            <div class="card tarjeta-modulo en-desarrollo h-100 text-secondary" aria-disabled="true">
                        <?php endif; ?>
                                <div class="card-body d-flex gap-3">
                                    <span class="icono-modulo" aria-hidden="true"><i class="bi <?= e($icono) ?>"></i></span>
                                    <div>
                                        <h3 class="h6 fw-bold mb-1">
                                            <?= e($nombre) ?>
                                            <?php if (!$lista): ?>
                                                <span class="badge text-bg-secondary fw-normal ms-1">En desarrollo</span>
                                            <?php endif; ?>
                                        </h3>
                                        <p class="small mb-0"><?= e($descripcion) ?></p>
                                    </div>
                                </div>
                        <?php if ($lista): ?>
                            </a>
                        <?php else: ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>

</div>
</body>
</html>