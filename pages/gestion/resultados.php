<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/Middleware/AuthMiddleware.php';
AuthMiddleware::requireRole(['Docente', 'Administrador'], '../auth/login.php');
require_once __DIR__ . '/../../src/Controllers/ResultadoController.php';

$hayId = isset($_GET['id']);
$idResultado = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
	'options' => ['min_range' => 1],
]);
$resultados = [];
$detalle = null;
$error = '';

try {
	$controller = new ResultadoController();
	if ($hayId && $idResultado !== false && $idResultado !== null) {
		$detalle = $controller->obtenerDetalleParaGestion($idResultado);
		if ($detalle === null) {
			http_response_code(404);
		}
	} elseif ($hayId) {
		http_response_code(400);
	} else {
		$resultados = $controller->listarParaGestion();
	}
} catch (Throwable $exception) {
	error_log('BeatCell - resultados gestion: ' . $exception->getMessage());
	$error = 'No se pudieron cargar los resultados.';
}

$e = static fn($valor): string => htmlspecialchars(
	(string) $valor,
	ENT_QUOTES | ENT_SUBSTITUTE,
	'UTF-8'
);
?>
<!doctype html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Resultados | BeatCell</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container py-4">
	<header class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
		<div>
			<p class="text-primary mb-1">SEGUIMIENTO</p>
			<h1 class="h3 mb-1">Resultados</h1>
			<p class="text-muted mb-0">Revisión de notas y respuestas registradas.</p>
		</div>
		<a class="btn btn-outline-secondary" href="panel.php">Volver al panel</a>
	</header>

	<?php if ($error !== ''): ?>
		<div class="alert alert-danger" role="alert"><?= $e($error) ?></div>
	<?php elseif ($hayId && !$detalle): ?>
		<div class="alert alert-warning" role="status">El resultado solicitado no existe.</div>
		<a href="resultados.php" class="btn btn-primary">Volver a resultados</a>
	<?php elseif ($detalle): ?>
		<a href="resultados.php" class="btn btn-link px-0 mb-3">&larr; Volver a resultados</a>

		<section class="card shadow-sm mb-4">
			<div class="card-body">
				<div class="d-flex justify-content-between gap-3 flex-wrap">
					<div>
						<h2 class="h5 mb-1"><?= $e($detalle['actividad']) ?></h2>
						<p class="text-muted mb-1"><?= $e($detalle['curso']) ?> &middot; <?= $e($detalle['tema']) ?></p>
						<p class="mb-0"><?= $e($detalle['nombres'] . ' ' . $detalle['apellidos']) ?> &middot; <?= $e($detalle['codigo']) ?></p>
					</div>
					<div class="text-md-end">
						<strong class="fs-4"><?= $e(number_format((float) $detalle['porcentaje'], 2)) ?>%</strong>
						<div><?= (int) $detalle['puntaje_obtenido'] ?> / <?= (int) $detalle['puntaje_total'] ?> &middot; <?= $e($detalle['estado']) ?></div>
					</div>
				</div>
			</div>
		</section>

		<?php if ($detalle['respuestas'] === []): ?>
			<div class="alert alert-info">No hay respuestas detalladas registradas.</div>
		<?php else: ?>
			<section class="card shadow-sm">
				<div class="card-body">
					<h2 class="h5 mb-3">Respuestas</h2>
					<div class="table-responsive">
						<table class="table align-middle mb-0">
							<thead><tr><th>Pregunta</th><th>Respuesta</th><th>Estado</th><th>Puntos</th></tr></thead>
							<tbody>
							<?php foreach ($detalle['respuestas'] as $respuesta): ?>
								<tr>
									<td><?= $e($respuesta['pregunta']) ?></td>
									<td>
										<?php if (!empty($respuesta['respuesta_texto'])): ?>
											<?= $e($respuesta['respuesta_texto']) ?>
										<?php elseif (!empty($respuesta['opcion_texto'])): ?>
											<?= $e($respuesta['opcion_texto']) ?>
										<?php elseif (empty($respuesta['opcion_imagen'])): ?>
											Sin respuesta
										<?php endif; ?>
										<?php if (!empty($respuesta['opcion_imagen'])): ?>
											<img class="respuesta-imagen" src="../../public/assets/img/preguntas/<?= $e(basename($respuesta['opcion_imagen'])) ?>" alt="Imagen de la opción elegida">
										<?php endif; ?>
									</td>
									<td><?= !empty($respuesta['correcta']) ? 'Correcta' : 'Incorrecta' ?></td>
									<td><?= (int) $respuesta['puntos_obtenidos'] ?> / <?= (int) $respuesta['puntos'] ?></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
			</section>
		<?php endif; ?>
	<?php elseif ($resultados === []): ?>
		<section class="card shadow-sm"><div class="card-body">Todavía no hay resultados registrados.</div></section>
	<?php else: ?>
		<section class="card shadow-sm">
			<div class="card-body">
				<div class="table-responsive">
					<table class="table align-middle mb-0">
						<thead><tr><th>Estudiante</th><th>Curso</th><th>Actividad</th><th>Puntaje</th><th>Porcentaje</th><th>Estado</th><th>Fecha</th><th>Detalle</th></tr></thead>
						<tbody>
						<?php foreach ($resultados as $resultado): ?>
							<tr>
								<td><?= $e($resultado['nombres'] . ' ' . $resultado['apellidos']) ?></td>
								<td><?= $e($resultado['curso']) ?></td>
								<td><?= $e($resultado['actividad']) ?></td>
								<td><?= (int) $resultado['puntaje_obtenido'] ?> / <?= (int) $resultado['puntaje_total'] ?></td>
								<td><?= $e(number_format((float) $resultado['porcentaje'], 2)) ?>%</td>
								<td><?= $e($resultado['estado']) ?></td>
								<td><?= $e($resultado['fecha']) ?></td>
								<td><a href="resultados.php?id=<?= (int) $resultado['id_resultado'] ?>">Ver</a></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		</section>
		<p class="text-muted small mt-3">Aún no existe una asignación docente-curso; por ello, los docentes autorizados pueden ver resultados de todo el sistema.</p>
	<?php endif; ?>
</main>
</body>
</html>
