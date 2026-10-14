<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/Middleware/AuthMiddleware.php';

AuthMiddleware::requireRole(
    ['Docente', 'Administrador'],
    '../auth/login.php'
);

require_once __DIR__ . '/../../src/Controllers/RutaAprendizajeController.php';

if (empty($_SESSION['csrf_rutas'])) {
    $_SESSION['csrf_rutas'] = bin2hex(random_bytes(32));
}

$e = static fn($valor): string => htmlspecialchars(
    (string) $valor,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

$enteroPositivo = static function ($valor, string $campo): int {
    if (!is_string($valor) && !is_int($valor)) {
        throw new InvalidArgumentException(
            "{$campo} debe ser un entero mayor que cero."
        );
    }

    $numero = filter_var(
        $valor,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($numero === false) {
        throw new InvalidArgumentException(
            "{$campo} debe ser un entero mayor que cero."
        );
    }

    return $numero;
};

$controller = null;
$rutas = [];
$rutaSeleccionada = null;
$cursosRuta = [];
$cursosDisponibles = [];
$error = '';
$mensaje = '';
$idRuta = null;
$datosFormulario = [
    'nombre' => '',
    'descripcion' => '',
    'nivel' => 'Básico',
];

$mensajes = [
    'creada' => 'Ruta creada correctamente.',
    'actualizada' => 'Ruta actualizada correctamente.',
    'estado' => 'Estado actualizado correctamente.',
    'agregado' => 'Curso añadido a la ruta.',
    'orden' => 'Orden actualizado correctamente.',
    'quitado' => 'Curso retirado de la ruta.',
];

$codigoMensaje = $_GET['ok'] ?? '';
if (is_string($codigoMensaje)) {
    $mensaje = $mensajes[$codigoMensaje] ?? '';
}

try {
    if (isset($_GET['id'])) {
        $idRuta = $enteroPositivo($_GET['id'], 'La ruta');
    }

    $controller = new RutaAprendizajeController();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';

        if (
            !is_string($token)
            || !hash_equals($_SESSION['csrf_rutas'], $token)
        ) {
            throw new InvalidArgumentException(
                'La sesión del formulario expiró. Recarga la página.'
            );
        }

        $accion = $_POST['accion'] ?? '';

        if (!is_string($accion)) {
            throw new InvalidArgumentException('La operación no es válida.');
        }

        if ($accion === 'crear' || $accion === 'actualizar') {
            foreach (array_keys($datosFormulario) as $campo) {
                $valor = $_POST[$campo] ?? '';

                if (!is_string($valor)) {
                    throw new InvalidArgumentException(
                        'Los datos del formulario no son válidos.'
                    );
                }

                $datosFormulario[$campo] = $valor;
            }
        }

        if ($accion === 'crear') {
            $idCreado = $controller->crear($datosFormulario);
            header('Location: rutas.php?id=' . $idCreado . '&ok=creada');
            exit;
        }

        $idOperacion = $enteroPositivo(
            $_POST['id_ruta'] ?? null,
            'La ruta'
        );
        $idRuta = $idOperacion;

        switch ($accion) {
            case 'actualizar':
                $controller->actualizar($idOperacion, $datosFormulario);
                $resultadoOperacion = 'actualizada';
                break;

            case 'estado':
                $estado = $_POST['estado'] ?? '';

                if (!is_string($estado)) {
                    throw new InvalidArgumentException(
                        'El estado no es válido.'
                    );
                }

                $controller->cambiarEstado($idOperacion, $estado);
                $resultadoOperacion = 'estado';
                break;

            case 'agregar':
                $controller->agregarCurso($idOperacion, $_POST);
                $resultadoOperacion = 'agregado';
                break;

            case 'orden':
                $idCurso = $enteroPositivo(
                    $_POST['id_curso'] ?? null,
                    'El curso'
                );

                $controller->cambiarOrden(
                    $idOperacion,
                    $idCurso,
                    $_POST
                );
                $resultadoOperacion = 'orden';
                break;

            case 'quitar':
                $idCurso = $enteroPositivo(
                    $_POST['id_curso'] ?? null,
                    'El curso'
                );

                $controller->quitarCurso($idOperacion, $idCurso);
                $resultadoOperacion = 'quitado';
                break;

            default:
                throw new InvalidArgumentException(
                    'La operación solicitada no es válida.'
                );
        }

        header(
            'Location: rutas.php?id=' . $idOperacion
            . '&ok=' . $resultadoOperacion
        );
        exit;
    }
} catch (InvalidArgumentException $ex) {
    $error = $ex->getMessage();
} catch (Throwable $ex) {
    error_log('BeatCell - gestión de rutas: ' . $ex->getMessage());
    $error = 'No se pudo completar la operación. Revisa la conexión y las tablas de rutas.';
}

/* Cargar la información incluso si una operación no pudo completarse. */
if ($controller !== null) {
    try {
        $rutas = $controller->listarParaGestion();

        if ($idRuta !== null) {
            $rutaSeleccionada = $controller->obtenerPorId($idRuta);

            if ($rutaSeleccionada === null) {
                $error = 'La ruta seleccionada no existe.';
            } else {
                $cursosRuta = $controller->obtenerCursos($idRuta);
                $cursosDisponibles = $controller->obtenerCursosDisponibles(
                    $idRuta
                );

                $conservarFormulario = (
                    $_SERVER['REQUEST_METHOD'] === 'POST'
                    && ($_POST['accion'] ?? '') === 'actualizar'
                    && $error !== ''
                );

                if (!$conservarFormulario) {
                    $datosFormulario = [
                        'nombre' => $rutaSeleccionada['nombre'],
                        'descripcion' => $rutaSeleccionada['descripcion'] ?? '',
                        'nivel' => $rutaSeleccionada['nivel'] ?? 'Básico',
                    ];
                }
            }
        }
    } catch (Throwable $ex) {
        error_log('BeatCell - consulta de rutas: ' . $ex->getMessage());
        $error = 'No se pudo cargar la información de las rutas.';
    }
}

$ordenSugerido = 1;
foreach ($cursosRuta as $curso) {
    $ordenSugerido = max($ordenSugerido, (int) $curso['orden'] + 1);
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rutas de aprendizaje | BeatCell</title>
    <link rel="stylesheet" href="../../public/assets/css/rutas.css">
</head>
<body>
<main class="contenedor-rutas">
    <nav class="navegacion-rutas">
        <a href="panel.php">Volver al panel</a>
        <a href="rutas.php">Nueva ruta</a>
    </nav>

    <header class="encabezado-rutas">
        <h1>Rutas de aprendizaje</h1>
        <p>Agrupa cursos y establece el orden recomendado para estudiarlos.</p>
    </header>

    <?php if ($error !== ''): ?>
        <p class="mensaje-error" role="alert"><?= $e($error) ?></p>
    <?php elseif ($mensaje !== ''): ?>
        <p class="mensaje-exito" role="status"><?= $e($mensaje) ?></p>
    <?php endif; ?>

    <section class="tarjeta-ruta">
        <h2><?= $rutaSeleccionada ? 'Editar ruta' : 'Crear ruta' ?></h2>

        <form method="post" action="rutas.php<?= $rutaSeleccionada
            ? '?id=' . (int) $rutaSeleccionada['id_ruta']
            : '' ?>">
            <input type="hidden" name="csrf_token"
                   value="<?= $e($_SESSION['csrf_rutas']) ?>">
            <input type="hidden" name="accion"
                   value="<?= $rutaSeleccionada ? 'actualizar' : 'crear' ?>">

            <?php if ($rutaSeleccionada): ?>
                <input type="hidden" name="id_ruta"
                       value="<?= (int) $rutaSeleccionada['id_ruta'] ?>">
            <?php endif; ?>

            <div class="campo">
                <label for="nombre">Nombre</label>
                <input type="text" id="nombre" name="nombre"
                       maxlength="150" required
                       value="<?= $e($datosFormulario['nombre']) ?>">
            </div>

            <div class="campo">
                <label for="descripcion">Descripción</label>
                <textarea id="descripcion" name="descripcion"
                          rows="4"><?= $e($datosFormulario['descripcion']) ?></textarea>
            </div>

            <div class="campo">
                <label for="nivel">Nivel</label>
                <select id="nivel" name="nivel" required>
                    <?php foreach (['Básico', 'Intermedio', 'Avanzado'] as $nivel): ?>
                        <option value="<?= $e($nivel) ?>"
                            <?= $datosFormulario['nivel'] === $nivel
                                ? 'selected' : '' ?>>
                            <?= $e($nivel) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit">
                <?= $rutaSeleccionada ? 'Guardar cambios' : 'Crear ruta' ?>
            </button>
        </form>
    </section>

    <section class="lista-rutas">
        <h2>Rutas registradas</h2>

        <?php if (!$rutas): ?>
            <p>Todavía no hay rutas registradas.</p>
        <?php else: ?>
            <?php foreach ($rutas as $ruta): ?>
                <article class="tarjeta-ruta">
                    <h3><?= $e($ruta['nombre']) ?></h3>
                    <p><?= $e($ruta['descripcion'] ?? '') ?></p>
                    <p>
                        Nivel: <?= $e($ruta['nivel'] ?? 'Sin nivel') ?> |
                        Estado: <?= $e($ruta['estado']) ?> |
                        Cursos: <?= (int) $ruta['total_cursos'] ?>
                    </p>

                    <a href="rutas.php?id=<?= (int) $ruta['id_ruta'] ?>">
                        Editar y gestionar cursos
                    </a>

                    <form method="post" action="rutas.php">
                        <input type="hidden" name="csrf_token"
                               value="<?= $e($_SESSION['csrf_rutas']) ?>">
                        <input type="hidden" name="accion" value="estado">
                        <input type="hidden" name="id_ruta"
                               value="<?= (int) $ruta['id_ruta'] ?>">
                        <input type="hidden" name="estado"
                               value="<?= $ruta['estado'] === 'Activo'
                                   ? 'Inactivo' : 'Activo' ?>">

                        <button type="submit">
                            <?= $ruta['estado'] === 'Activo'
                                ? 'Desactivar' : 'Activar' ?>
                        </button>
                    </form>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <?php if ($rutaSeleccionada): ?>
        <section class="tarjeta-ruta">
            <h2>Cursos de <?= $e($rutaSeleccionada['nombre']) ?></h2>

            <?php if (!$cursosDisponibles): ?>
                <p>No hay cursos activos disponibles para añadir.</p>
            <?php else: ?>
                <form method="post"
                      action="rutas.php?id=<?= (int) $idRuta ?>">
                    <input type="hidden" name="csrf_token"
                           value="<?= $e($_SESSION['csrf_rutas']) ?>">
                    <input type="hidden" name="accion" value="agregar">
                    <input type="hidden" name="id_ruta"
                           value="<?= (int) $idRuta ?>">

                    <div class="campo">
                        <label for="id_curso">Curso</label>
                        <select id="id_curso" name="id_curso" required>
                            <option value="">Selecciona un curso</option>
                            <?php foreach ($cursosDisponibles as $curso): ?>
                                <option value="<?= (int) $curso['id_curso'] ?>">
                                    <?= $e($curso['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="campo">
                        <label for="orden_nuevo">Orden</label>
                        <input type="number" id="orden_nuevo" name="orden"
                               min="1" max="2147483647"
                               value="<?= $ordenSugerido ?>" required>
                    </div>

                    <button type="submit">Añadir curso</button>
                </form>
            <?php endif; ?>

            <h3>Orden de los cursos</h3>
            <p>Para cambiar una posición, utiliza un número de orden libre.</p>

            <?php if (!$cursosRuta): ?>
                <p>Esta ruta todavía no tiene cursos.</p>
            <?php else: ?>
                <?php foreach ($cursosRuta as $curso): ?>
                    <article class="curso-ruta">
                        <h4>
                            <?= (int) $curso['orden'] ?>.
                            <?= $e($curso['nombre']) ?>
                        </h4>
                        <p>Estado: <?= $e($curso['estado']) ?></p>

                        <form method="post"
                              action="rutas.php?id=<?= (int) $idRuta ?>">
                            <input type="hidden" name="csrf_token"
                                   value="<?= $e($_SESSION['csrf_rutas']) ?>">
                            <input type="hidden" name="accion" value="orden">
                            <input type="hidden" name="id_ruta"
                                   value="<?= (int) $idRuta ?>">
                            <input type="hidden" name="id_curso"
                                   value="<?= (int) $curso['id_curso'] ?>">

                            <label for="orden_<?= (int) $curso['id_curso'] ?>">
                                Orden
                            </label>
                            <input type="number"
                                   id="orden_<?= (int) $curso['id_curso'] ?>"
                                   name="orden" min="1" max="2147483647"
                                   value="<?= (int) $curso['orden'] ?>" required>

                            <button type="submit">Guardar orden</button>
                        </form>

                        <form method="post"
                              action="rutas.php?id=<?= (int) $idRuta ?>">
                            <input type="hidden" name="csrf_token"
                                   value="<?= $e($_SESSION['csrf_rutas']) ?>">
                            <input type="hidden" name="accion" value="quitar">
                            <input type="hidden" name="id_ruta"
                                   value="<?= (int) $idRuta ?>">
                            <input type="hidden" name="id_curso"
                                   value="<?= (int) $curso['id_curso'] ?>">

                            <button type="submit">
                                Quitar de la ruta
                            </button>
                        </form>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>
</body>
</html>