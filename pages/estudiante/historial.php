<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/Middleware/AuthMiddleware.php';
$sesionUsuario = AuthMiddleware::requireRole(['Estudiante'], '../auth/login.php');
require_once __DIR__ . '/../../src/Controllers/ResultadoController.php';

$idUsuario = (int) $sesionUsuario['id_usuario'];
$hayId = isset($_GET['id']);
$idResultado = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
	'options' => ['min_range' => 1],
]);
$detalle = null;
$resultados = [];
$error = '';

try {
	$controller = new ResultadoController();
	if ($hayId && $idResultado !== false && $idResultado !== null) {
		$detalle = $controller->obtenerDetalleParaUsuario($idResultado, $idUsuario);
		if ($detalle === null) {
			http_response_code(404);
		}
	} elseif ($hayId) {
		http_response_code(400);
	} else {
		$resultados = $controller->listarPorUsuario($idUsuario);
	}
} catch (Throwable $exception) {
	error_log('BeatCell - historial estudiante: ' . $exception->getMessage());
	$error = 'No se pudo cargar el historial. Inténtalo de nuevo más tarde.';
}

$e = static fn($valor): string => htmlspecialchars(
	(string) $valor,
	ENT_QUOTES | ENT_SUBSTITUTE,
	'UTF-8'
);
$urlCss = '../../public/assets/css/historial.css?v=' . filemtime(__DIR__ . '/../../public/assets/css/historial.css');
?>
<!doctype html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Historial de resultados | BeatCell</title>
	<link rel="stylesheet" href="<?= $e($urlCss) ?>">
	<link rel="stylesheet" href="../../public/assets/css/estudiante.css?v=<?= filemtime(__DIR__ . '/../../public/assets/css/estudiante.css') ?>">
</head>
<body class="pagina-historial">
<?php require __DIR__ . '/_navegacion.php'; ?>
<main class="contenedor-historial">
	<header class="encabezado-historial">
		<div>
			<p class="sobrelinea">MI APRENDIZAJE</p>
			<h1>Historial de resultados</h1>
			<p>Aquí puedes revisar las actividades que ya realizaste.</p>
		</div>
	</header>

	<?php if ($error !== ''): ?>
		<section class="estado-vacio" role="alert">
			<h2>No se pudo cargar el historial</h2>
			<p><?= $e($error) ?></p>
		</section>
	<?php elseif ($hayId && !$detalle): ?>
		<section class="estado-vacio" role="status">
			<h2>Resultado no encontrado</h2>
			<p>Ese resultado no existe o no pertenece a tu cuenta.</p>
			<a class="boton-principal" href="historial.php">Volver al historial</a>
		</section>
	<?php elseif ($detalle): ?>
		<a class="enlace-volver" href="historial.php">&larr; Volver al historial</a>
		<section class="detalle-resultado">
			<span class="estado estado-<?= $e(strtolower($detalle['estado'])) ?>"><?= $e($detalle['estado']) ?></span>
			<h2><?= $e($detalle['actividad']) ?></h2>
			<p><?= $e($detalle['curso']) ?> &middot; <?= $e($detalle['tema']) ?></p>
			<div class="resumen-resultado">
				<strong><?= (int) $detalle['puntaje_obtenido'] ?> / <?= (int) $detalle['puntaje_total'] ?></strong>
				<span><?= $e(number_format((float) $detalle['porcentaje'], 2)) ?>%</span>
				<span><?= (int) $detalle['respuestas_correctas'] ?> de <?= (int) $detalle['total_preguntas'] ?> correctas</span>
			</div>
		</section>

		<?php if ($detalle['respuestas'] === []): ?>
			<section class="estado-vacio"><p>No hay respuestas detalladas registradas para este intento.</p></section>
		<?php else: ?>
			<section class="lista-respuestas" aria-label="Detalle de respuestas">
				<?php foreach ($detalle['respuestas'] as $respuesta): ?>
					<article class="respuesta <?= !empty($respuesta['correcta']) ? 'correcta' : 'incorrecta' ?>">
						<h3><?= $e($respuesta['pregunta']) ?></h3>
						<?php if (!empty($respuesta['respuesta_texto'])): ?>
							<p><strong>Tu respuesta:</strong> <?= $e($respuesta['respuesta_texto']) ?></p>
						<?php elseif (!empty($respuesta['opcion_texto']) || !empty($respuesta['opcion_imagen'])): ?>
							<p><strong>Tu respuesta:</strong> <?= $e($respuesta['opcion_texto']) ?></p>
							<?php if (!empty($respuesta['opcion_imagen'])): ?>
								<img class="respuesta-imagen" src="../../public/assets/img/preguntas/<?= $e(basename($respuesta['opcion_imagen'])) ?>" alt="Imagen de tu respuesta">
							<?php endif; ?>
						<?php else: ?>
							<p><strong>Tu respuesta:</strong> Sin respuesta</p>
						<?php endif; ?>
						<span><?= !empty($respuesta['correcta']) ? 'Correcta' : 'Incorrecta' ?> &middot; <?= (int) $respuesta['puntos_obtenidos'] ?> pts.</span>
					</article>
				<?php endforeach; ?>
			</section>
		<?php endif; ?>
	<?php elseif ($resultados === []): ?>
		<section class="estado-vacio">
			<h2>Aún no tienes resultados</h2>
			<p>Cuando completes una actividad, tu resultado aparecerá aquí.</p>
		</section>
	<?php else: ?>
		<section class="tabla-resultados">
			<table>
				<thead>
				<tr>
					<th>Actividad</th>
					<th>Curso</th>
					<th>Puntaje</th>
					<th>Porcentaje</th>
					<th>Estado</th>
					<th>Fecha</th>
					<th><span class="solo-lector">Detalle</span></th>
				</tr>
				</thead>
				<tbody>
				<?php foreach ($resultados as $resultado): ?>
					<tr>
						<td><?= $e($resultado['actividad']) ?></td>
						<td><?= $e($resultado['curso']) ?></td>
						<td><?= (int) $resultado['puntaje_obtenido'] ?> / <?= (int) $resultado['puntaje_total'] ?></td>
						<td><?= $e(number_format((float) $resultado['porcentaje'], 2)) ?>%</td>
						<td><?= $e($resultado['estado']) ?></td>
						<td><?= $e($resultado['fecha']) ?></td>
						<td><a href="historial.php?id=<?= (int) $resultado['id_resultado'] ?>">Ver detalle</a></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</section>
	<?php endif; ?>
</main>
</body>
</html>
