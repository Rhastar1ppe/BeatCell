<?php
require_once __DIR__ . '/../../src/Middleware/AuthMiddleware.php';
$sesionUsuario = AuthMiddleware::requireRole(['Estudiante'], '../auth/login.php');
require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../../src/Controllers/ResultadoController.php';
require_once __DIR__ . '/../../src/Models/TiempoActividad.php';

$db = (new Database())->connect();
$idUsuario = (int) $sesionUsuario['id_usuario'];
$idActividad = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$e = static fn($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
$error = '';
$resultado = null;

if ($idActividad === false || $idActividad === null || $idActividad < 1) {
    header('Location: actividades.php');
    exit;
}

$idActividad = (int) $idActividad;
$stmt = $db->prepare("SELECT * FROM actividades WHERE id_actividad = ? AND estado = 'Activo'");
$stmt->execute([$idActividad]);
$actividad = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$actividad) {
    http_response_code(404);
    exit('La actividad no existe o no está disponible.');
}

$stmt = $db->prepare('SELECT 1 FROM resultados WHERE id_usuario = ? AND id_actividad = ? LIMIT 1');
$stmt->execute([$idUsuario, $idActividad]);
$yaRespondida = (bool) $stmt->fetchColumn();
$stmt = $db->prepare("SELECT * FROM preguntas WHERE id_actividad = ? AND estado = 'Activo' ORDER BY id_pregunta ASC");
$stmt->execute([$idActividad]);
$preguntas = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($preguntas as &$pregunta) {
    $stmtOpciones = $db->prepare('SELECT id_opcion, texto, imagen FROM opciones_respuesta WHERE id_pregunta = ? ORDER BY id_opcion ASC');
    $stmtOpciones->execute([(int) $pregunta['id_pregunta']]);
    $pregunta['opciones'] = $stmtOpciones->fetchAll(PDO::FETCH_ASSOC);
}
unset($pregunta);

$modoTiempo = TiempoActividad::normalizarModo($actividad['modo_tiempo'] ?? null, $actividad['tiempo_limite'] ?? null);
$segundosConfigurados = max(0, (int) ($actividad['tiempo_limite'] ?? 0));
$porPregunta = $modoTiempo === TiempoActividad::POR_PREGUNTA;
// Segundos máximos para toda la actividad: el tiempo total configurado o, en modo "por pregunta", segundos × preguntas.
$tiempoLimite = TiempoActividad::segundosTotales($segundosConfigurados, $modoTiempo, count($preguntas));
// En modo "por pregunta" cada reloj corre en el navegador; el servidor solo exige que el total no se pase
// de preguntas × segundos más esta holgura (latencia de red y de carga de la página).
$holguraPorPregunta = $porPregunta ? 10 + count($preguntas) : 0;
$claveTemporizador = $idUsuario . ':' . $idActividad;
if (!$yaRespondida && $preguntas && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['temporizadores_actividad'][$claveTemporizador] ??= [
        'inicio' => time(),
        'token' => bin2hex(random_bytes(32)),
    ];
}
$temporizador = $_SESSION['temporizadores_actividad'][$claveTemporizador] ?? null;
if (!is_array($temporizador)) {
    $temporizador = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$yaRespondida) {
    $tokenEnviado = $_POST['token'] ?? '';
    $tokenValido = $temporizador !== null
        && is_string($tokenEnviado)
        && isset($temporizador['token'])
        && hash_equals($temporizador['token'], $tokenEnviado);

    if (!$tokenValido) {
        $error = 'La sesión de la actividad venció. Vuelve a abrirla para continuar.';
    } elseif (!$preguntas) {
        $error = 'Esta actividad todavía no tiene preguntas activas.';
    } else {
        $tiempoTranscurrido = time() - (int) $temporizador['inicio'];
        $tiempoAgotado = $tiempoLimite > 0 && $tiempoTranscurrido >= $tiempoLimite + $holguraPorPregunta;
        $dentroDelMargenEnvio = $tiempoLimite > 0
            && $tiempoTranscurrido <= $tiempoLimite + $holguraPorPregunta + 2;
        $aceptarRespuestasEnviadas = !$tiempoAgotado || $dentroDelMargenEnvio;
        // En modo "por pregunta" una pregunta puede quedar sin responder porque se acabó su tiempo.
        $permiteSinResponder = $porPregunta || $tiempoAgotado;
        $enviadas =$aceptarRespuestasEnviadas ? ($_POST['respuestas'] ?? []) : [];$respuestasCalculadas = [];
        $puntajeTotal = 0;

        if (!is_array($enviadas)) {$error = 'Las respuestas enviadas no tienen un formato válido.';
        }

        foreach ($preguntas as$pregunta) {
            if ($error !== '') {
                break;
            }

            $idPregunta = (int)$pregunta['id_pregunta'];
            $puntos = max(0, (int)$pregunta['puntos']);
            $puntajeTotal +=$puntos;
            $correcta = false;
            $idOpcionRespuesta = null;
            $respuestaTexto = null;

            if (($pregunta['tipo'] ?? '') === 'completar') {$entrada = $enviadas[$idPregunta] ?? null;
                if (!is_string($entrada) || trim($entrada) === '') {
                    if (!$permiteSinResponder) {$error = 'Responde todas las preguntas antes de enviar la actividad.';
                        break;
                    }
                } else {
                    $respuestaTexto = trim($entrada);
                    $stmtCorrecta =$db->prepare('SELECT texto FROM opciones_respuesta WHERE id_pregunta = ? AND correcta = 1 LIMIT 1');
                    $stmtCorrecta->execute([$idPregunta]);
                    $esperada = trim((string)$stmtCorrecta->fetchColumn());
                    $correcta =$esperada !== ''
                        && mb_strtolower($respuestaTexto, 'UTF-8') === mb_strtolower($esperada, 'UTF-8');
                }
            } else {$entrada = $enviadas[$idPregunta] ?? null;
                $idOpcion = is_scalar($entrada)
                    ? filter_var($entrada, FILTER_VALIDATE_INT)
                    : false;
                if ($idOpcion === false ||$idOpcion < 1) {
                    if (!$permiteSinResponder) {$error = 'Responde todas las preguntas antes de enviar la actividad.';
                        break;
                    }
                } else {
                    $stmtCorrecta =$db->prepare('SELECT correcta FROM opciones_respuesta WHERE id_opcion = ? AND id_pregunta = ?');
                    $stmtCorrecta->execute([$idOpcion,$idPregunta]);
                    $valorCorrecta =$stmtCorrecta->fetchColumn();
                    if ($valorCorrecta === false) {$error = 'Una de las opciones enviadas no pertenece a esta pregunta.';
                        break;
                    }

                    $idOpcionRespuesta = (int)$idOpcion;
                    $correcta = (bool)$valorCorrecta;
                }
            }

            $respuestasCalculadas[] = [
                'id_pregunta' => $idPregunta,
                'id_opcion' => $idOpcionRespuesta,
                'respuesta_texto' => $respuestaTexto,
                'correcta' => $correcta,
                'puntos' => $puntos,
                'puntos_obtenidos' => $correcta ? $puntos : 0,
            ];
        }

        if ($error === '') {
            try {
                $db->beginTransaction();$controller = new ResultadoController(null, null, $db);$idResultado = $controller->registrarResultado($idUsuario, $idActividad,$respuestasCalculadas, [
                    'puntaje_total' => $puntajeTotal,
                    'total_preguntas' => count($preguntas),
                    'umbral_aprobacion' => (float) ($actividad['puntaje_minimo'] ?? 70),
                ]);

                $db->commit();

                unset($_SESSION['temporizadores_actividad'][$claveTemporizador]);
                $stmt =$db->prepare('SELECT puntaje_obtenido, puntaje_total, porcentaje, estado FROM resultados WHERE id_resultado = ? AND id_usuario = ?');
                $stmt->execute([$idResultado, $idUsuario]);$resultado = $stmt->fetch(PDO::FETCH_ASSOC);$yaRespondida = true;
                if ($tiempoAgotado) {
                    $error =$aceptarRespuestasEnviadas
                        ? 'Se agotó el tiempo; se registraron las respuestas enviadas.'
                        : 'Se agotó el tiempo antes de recibir las respuestas; el intento se registró sin ellas.';
                }
            } catch (DomainException $ex) {
                if ($db->inTransaction()) {$db->rollBack();
                }
                unset($_SESSION['temporizadores_actividad'][$claveTemporizador]);$yaRespondida = true;
                $error =$ex->getMessage();
            } catch (Throwable $ex) {
                if ($db->inTransaction()) {$db->rollBack();
                }
                error_log('BeatCell - error al guardar respuestas de actividad: ' . $ex->getMessage());$error = 'No se pudo guardar la evaluación. Verifica los datos e inténtalo de nuevo.';
            }
        }
    }
}

$tiempoRestante = $temporizador !== null &&$tiempoLimite > 0
    ? max(0, $tiempoLimite - (time() - (int)$temporizador['inicio']))
    : 0;
$actividadEnCurso = !$yaRespondida && $preguntas !== [] && $temporizador !== null;
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e($actividad['titulo']) ?> | BeatCell</title>
    <link rel="stylesheet" href="../../public/assets/css/actividades.css?v=<?= filemtime(__DIR__ . '/../../public/assets/css/actividades.css') ?>">
    <link rel="stylesheet" href="../../public/assets/css/estudiante.css?v=<?= filemtime(__DIR__ . '/../../public/assets/css/estudiante.css') ?>">
<?php if ($porPregunta): ?>
    <noscript><style>.oculta { display: block !important; }</style></noscript>
<?php endif; ?>
</head>

<body>
<?php require __DIR__ . '/_navegacion.php'; ?>
<main>
<h1><?= $e($actividad['titulo']) ?></h1>
<?php if (!empty($actividad['descripcion'])): ?><p class="descripcion-actividad"><?= $e($actividad['descripcion']) ?></p><?php endif; ?>
<?php if ($error): ?><p role="alert" class="mensaje-error"><?= $e($error) ?></p><?php endif; ?>

<?php if ($resultado): ?>
    <section class="tarjeta-resultado">
        <h2>Resultado guardado</h2>
        <p><strong>Puntaje:</strong> <?= (int)$resultado['puntaje_obtenido'] ?> de <?= (int)$resultado['puntaje_total'] ?></p>
        <p><strong>Porcentaje:</strong> <?= $e($resultado['porcentaje']) ?>%</p>
        <p><strong>Estado:</strong> <?= $e($resultado['estado']) ?></p>
    </section>
<?php elseif ($yaRespondida): ?>
    <p class="mensaje-info">Ya realizaste el intento permitido para esta actividad.</p>
<?php elseif (!$preguntas): ?>
    <p class="mensaje-info">Esta actividad todavía no tiene preguntas activas.</p>
<?php elseif (!$temporizador): ?>
    <p role="alert" class="mensaje-error">La sesión de la actividad venció. <a href="actividad.php?id=<?= $idActividad ?>">Vuelve a abrirla</a>.</p>
<?php else: ?>
<?php if ($tiempoLimite > 0 && !$porPregunta): ?>
    <p id="contador-tiempo" role="timer" aria-live="polite" data-segundos-restantes="<?= $tiempoRestante ?>"></p>
<?php endif; ?>

<form id="form-actividad" method="post" action=""<?php if ($porPregunta): ?> data-segundos-pregunta="<?= $segundosConfigurados ?>" data-segundos-totales-restantes="<?= $tiempoRestante + $holguraPorPregunta ?>"<?php endif; ?>>
<input type="hidden" name="token" value="<?= $e($temporizador['token']) ?>">

<?php if ($porPregunta): ?>
    <p class="mensaje-info">Cada pregunta tiene <?= $e(TiempoActividad::formatearDuracion($segundosConfigurados)) ?> para responderse. Al terminar su tiempo pasarás solo a la siguiente y no podrás volver atrás.</p>
    <div id="barra-pregunta" class="barra-pregunta" role="timer">
        <div class="barra-pregunta-datos">
            <span id="barra-pregunta-numero">Pregunta 1 de <?= count($preguntas) ?></span>
            <span id="barra-pregunta-reloj">Tiempo: <?= $e(TiempoActividad::formatearDuracion($segundosConfigurados)) ?></span>
        </div>
        <div class="barra-pregunta-pista"><div id="barra-pregunta-progreso" class="barra-pregunta-progreso"></div></div>
    </div>
<?php endif; ?>

<?php foreach ($preguntas as $i =>$pregunta): ?>
    <article class="tarjeta-pregunta<?= ($porPregunta && $i > 0) ? ' oculta' : '' ?>">
        <header class="encabezado-pregunta">
            <span class="numero-pregunta">Pregunta <?= ($i + 1) ?></span>
            <span class="puntos-pregunta"><?= (int)$pregunta['puntos'] ?> pts.</span>
        </header>
        
        <h2 class="texto-pregunta"><?= $e($pregunta['pregunta']) ?></h2>

        <?php if (!empty($pregunta['audio'])): ?>
            <audio class="audio-pregunta" controls preload="none" playsinline>
                <source src="../../public/assets/audio/preguntas/<?= $e(basename($pregunta['audio'])) ?>">
                Tu navegador no soporta la reproducción de audio.
            </audio>
        <?php endif; ?>

        <?php if (!empty($pregunta['imagen'])): ?>
            <div class="contenedor-imagen-pregunta">
                <img src="../../public/assets/img/preguntas/<?= $e(basename($pregunta['imagen'])) ?>" alt="Imagen de la pregunta" class="imagen-pregunta">
            </div>
        <?php endif; ?>

        <div class="opciones-contenedor<?= (($pregunta['tipo'] ?? '') === 'imagen') ? ' opciones-imagenes' : '' ?>">
        <?php if (($pregunta['tipo'] ?? '') === 'completar'): ?>
            <div class="campo-completar">
                <label for="p_<?= (int)$pregunta['id_pregunta'] ?>">Tu respuesta:</label>
                <input type="text" id="p_<?= (int)$pregunta['id_pregunta'] ?>" name="respuestas[<?= (int)$pregunta['id_pregunta'] ?>]" placeholder="Escribe tu respuesta..." <?= $porPregunta ? '' : 'required' ?>>
            </div>
        <?php else: foreach ($pregunta['opciones'] as$opcion): ?>
            <label class="opcion-respuesta<?= (($pregunta['tipo'] ?? '') === 'imagen') ? ' opcion-respuesta-imagen' : '' ?>">
                <input type="radio" name="respuestas[<?= (int)$pregunta['id_pregunta'] ?>]" value="<?= (int)$opcion['id_opcion'] ?>" <?= $porPregunta ? '' : 'required' ?>>
                <span class="texto-opcion"><?= $e($opcion['texto'] ?? '') ?></span>
                <?php if (!empty($opcion['imagen'])): ?>
                    <img src="../../public/assets/img/preguntas/<?= $e(basename($opcion['imagen'])) ?>" alt="Imagen de opción" class="imagen-opcion">
                <?php endif; ?>
            </label>
        <?php endforeach; endif; ?>
        </div>
        <?php if ($porPregunta && $i < count($preguntas) - 1): ?>
            <button type="button" class="btn-siguiente" data-accion="siguiente">Siguiente pregunta →</button>
        <?php endif; ?>
    </article>
<?php endforeach; ?>

<button type="submit" id="btn-enviar" class="btn-enviar<?= $porPregunta ? ' oculta' : '' ?>" onclick="return confirm('¿Enviar tus respuestas? Solo tienes un intento.')">Enviar evaluación</button>
</form>

<?php if ($porPregunta): ?>
<script>
    (function () {
        const formulario = document.getElementById('form-actividad');
        const tarjetas = Array.from(formulario.querySelectorAll('.tarjeta-pregunta'));
        const barra = document.getElementById('barra-pregunta');
        const etiqueta = document.getElementById('barra-pregunta-numero');
        const reloj = document.getElementById('barra-pregunta-reloj');
        const progreso = document.getElementById('barra-pregunta-progreso');
        const botonEnviar = document.getElementById('btn-enviar');
        const segundosPorPregunta = Number(formulario.dataset.segundosPregunta);
        const enviarAlExpirar = <?= $_SERVER['REQUEST_METHOD'] === 'GET' ? 'true' : 'false' ?>;
        let totalRestante = Number(formulario.dataset.segundosTotalesRestantes);
        let actual = 0;
        let restante = segundosPorPregunta;
        let intervalo = null;

        function pintarReloj() {
            const minutos = Math.floor(restante / 60);
            const segundos = String(restante % 60).padStart(2, '0');
            reloj.textContent = 'Tiempo: ' + minutos + ':' + segundos;
            progreso.style.width = (restante / segundosPorPregunta * 100) + '%';
            barra.classList.toggle('urgente', restante <= 5);
        }

        function mostrar(indice) {
            actual = indice;
            restante = segundosPorPregunta;
            tarjetas.forEach((tarjeta, i) => tarjeta.classList.toggle('oculta', i !== indice));
            etiqueta.textContent = 'Pregunta ' + (indice + 1) + ' de ' + tarjetas.length;
            botonEnviar.classList.toggle('oculta', indice !== tarjetas.length - 1);
            pintarReloj();
            if (indice > 0) { window.scrollTo(0, 0); }
        }

        function terminar() {
            window.clearInterval(intervalo);
            if (enviarAlExpirar) {
                formulario.submit();
                return;
            }
            reloj.textContent = 'Tiempo agotado. Envía la actividad para registrar el resultado.';
            botonEnviar.classList.remove('oculta');
        }

        function avanzar() {
            if (actual < tarjetas.length - 1) {
                mostrar(actual + 1);
            } else {
                terminar();
            }
        }

        formulario.addEventListener('click', (evento) => {
            if (evento.target.closest('[data-accion="siguiente"]')) { avanzar(); }
        });

        intervalo = window.setInterval(() => {
            totalRestante -= 1;
            restante -= 1;
            if (totalRestante <= 0) { terminar(); return; }
            if (restante <= 0) { avanzar(); return; }
            pintarReloj();
        }, 1000);

        mostrar(0);
    })();
</script>
<?php endif; ?>

<?php if ($tiempoLimite > 0 && !$porPregunta): ?>
<script>
    const contadorTiempo = document.getElementById('contador-tiempo');
    const formularioActividad = document.getElementById('form-actividad');
    const enviarAlExpirar = <?= $_SERVER['REQUEST_METHOD'] === 'GET' ? 'true' : 'false' ?>;
    let segundosRestantes = Number(contadorTiempo.dataset.segundosRestantes);

    function actualizarContador() {
        const minutos = Math.floor(segundosRestantes / 60);
        const segundos = String(segundosRestantes % 60).padStart(2, '0');
        contadorTiempo.textContent = `Tiempo restante: ${minutos}:${segundos}`;
    }

    actualizarContador();
    if (segundosRestantes <= 0) {
        if (enviarAlExpirar) {
            formularioActividad.submit();
        } else {
            contadorTiempo.textContent = 'Tiempo agotado. Envía la actividad para registrar el resultado.';
        }
    } else {
        const intervalo = window.setInterval(() => {
            segundosRestantes -= 1;
            actualizarContador();
            if (segundosRestantes <= 0) {
                window.clearInterval(intervalo);
                if (enviarAlExpirar) {
                    formularioActividad.submit();
                } else {
                    contadorTiempo.textContent = 'Tiempo agotado. Envía la actividad para registrar el resultado.';
                }
            }
        }, 1000);
    }
</script>
<?php endif; ?>
<?php endif; ?>
</main></body></html>
