<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/Middleware/AuthMiddleware.php';
AuthMiddleware::requireRole(['Docente', 'Administrador'], '../auth/login.php');
require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../../src/Controllers/ProgresoController.php';

$buscar = isset($_GET['buscar']) && is_string($_GET['buscar']) ? trim($_GET['buscar']) : '';
$detalleSolicitado = array_key_exists('estudiante', $_GET);
$idEstudiante = null;
if ($detalleSolicitado && is_scalar($_GET['estudiante'])) {
	$idEstudiante = filter_var(
		$_GET['estudiante'],
		FILTER_VALIDATE_INT,
		['options' => ['min_range' => 1]]
	);
}
$estudiantes = [];
$estudiante = null;
$cursos = [];
$error = '';
$noEncontrado = false;

try {
	$db = (new Database())->connect();

	if ($detalleSolicitado) {
		if ($idEstudiante === false || $idEstudiante === null) {
			http_response_code(404);
			$noEncontrado = true;
		} else {
			$consulta = $db->prepare(
				"SELECT id_usuario, codigo, nombres, apellidos, correo, estado
				 FROM usuarios WHERE id_usuario = ? AND rol = 'Estudiante' LIMIT 1"
			);
			$consulta->execute([(int) $idEstudiante]);
			$estudiante = $consulta->fetch(PDO::FETCH_ASSOC) ?: null;

			if ($estudiante === null) {
				http_response_code(404);
				$noEncontrado = true;
			} else {
				$cursos = (new ProgresoController($db))->obtenerPorUsuario((int) $idEstudiante);
			}
		}
	} else {
		$sql = "SELECT u.id_usuario, u.codigo, u.nombres, u.apellidos, u.correo, u.estado,
				   COUNT(DISTINCT c.id_curso) AS total_cursos,
				   COALESCE(SUM(CASE WHEN pc.estado = 'Completado' THEN 1 ELSE 0 END), 0) AS cursos_completados,
				   ROUND(COALESCE(AVG(CASE WHEN c.id_curso IS NOT NULL THEN COALESCE(pc.porcentaje, 0) END), 0), 0) AS progreso_general
				FROM usuarios u
				LEFT JOIN cursos c ON c.estado = 'Activo'
				LEFT JOIN progreso_curso pc ON pc.id_usuario = u.id_usuario AND pc.id_curso = c.id_curso
				WHERE u.rol = 'Estudiante'";
		$parametros = [];

		if ($buscar !== '') {
			$sql .= " AND (u.codigo LIKE ? OR u.nombres LIKE ? OR u.apellidos LIKE ?
				OR CONCAT(u.nombres, ' ', u.apellidos) LIKE ? OR u.correo LIKE ?)";
			$coincidencia = '%' . $buscar . '%';
			$parametros = array_fill(0, 5, $coincidencia);
		}

		$sql .= " GROUP BY u.id_usuario, u.codigo, u.nombres, u.apellidos, u.correo, u.estado
				  ORDER BY u.apellidos, u.nombres";
		$consulta = $db->prepare($sql);
		$consulta->execute($parametros);
		$estudiantes = $consulta->fetchAll(PDO::FETCH_ASSOC);
	}
} catch (Throwable $exception) {
	error_log('BeatCell - progreso gestion: ' . $exception->getMessage());
	$error = 'No se pudo cargar el progreso. Verifica la conexión con la base de datos.';
}

$e = static fn($valor): string => htmlspecialchars(
	(string) $valor,
	ENT_QUOTES | ENT_SUBSTITUTE,
	'UTF-8'
);
$clasesEstado = [
	'No iniciado' => 'secondary',
	'En progreso' => 'primary',
	'Completado' => 'success',
	'Reforzar' => 'warning text-dark',
];
$urlVolver = 'progreso.php' . ($buscar !== '' ? '?buscar=' . rawurlencode($buscar) : '');
?>
<!doctype html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Progreso de estudiantes | BeatCell</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container py-4">
	<header class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
		<div>
			<p class="text-primary mb-1">SEGUIMIENTO</p>
			<h1 class="h3 mb-1">Progreso de estudiantes</h1>
			<p class="text-muted mb-0">Avance registrado por curso para estudiantes activos.</p>
		</div>
		<a class="btn btn-outline-secondary" href="panel.php">Volver al panel</a>
	</header>

	<div class="alert alert-info" role="note">
		La base aún no asigna cursos a docentes; por eso este panel muestra el progreso global de los estudiantes.
	</div>

	<?php if ($error !== ''): ?>
		<div class="alert alert-danger" role="alert"><?= $e($error) ?></div>
	<?php elseif ($noEncontrado): ?>
		<div class="alert alert-warning" role="status">No se encontró al estudiante solicitado.</div>
		<a class="btn btn-primary" href="<?= $e($urlVolver) ?>">Volver a estudiantes</a>
	<?php elseif ($estudiante !== null): ?>
		<a class="btn btn-link px-0 mb-3" href="<?= $e($urlVolver) ?>">&larr; Volver a estudiantes</a>
		<section class="card shadow-sm mb-4">
			<div class="card-body d-flex flex-wrap justify-content-between gap-3">
				<div>
					<p class="text-muted small mb-1">ESTUDIANTE</p>
					<h2 class="h4 mb-1"><?= $e($estudiante['nombres'] . ' ' . $estudiante['apellidos']) ?></h2>
					<p class="text-muted mb-1"><?= $e($estudiante['codigo']) ?> &middot; <?= $e($estudiante['correo']) ?></p>
					<span class="badge <?= $estudiante['estado'] === 'Activo' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $e($estudiante['estado']) ?></span>
				</div>
				<?php
				$porcentajeGeneral = $cursos === [] ? 0 : (int) round(array_sum(array_map(
					static fn($curso): float => min(100, max(0, (float) ($curso['porcentaje'] ?? 0))),
					$cursos
				)) / count($cursos));
				$cursosCompletados = count(array_filter($cursos, static fn($curso): bool => ($curso['estado'] ?? '') === 'Completado'));
				?>
				<div class="text-md-end"><span class="text-muted d-block">Avance promedio</span><strong class="display-6"><?= $porcentajeGeneral ?>%</strong><div class="text-muted small"><?= $cursosCompletados ?> de <?= count($cursos) ?> cursos completados</div></div>
			</div>
		</section>
		<?php if ($cursos === []): ?>
			<div class="alert alert-info">No hay cursos activos para mostrar.</div>
		<?php else: ?>
			<h2 class="h5 mb-3">Avance por curso</h2>
			<div class="d-flex flex-column gap-3">
				<?php foreach ($cursos as $curso): ?>
					<?php
					$avance = min(100, max(0, (float) ($curso['porcentaje'] ?? 0)));
					$estado = (string) ($curso['estado'] ?? 'No iniciado');
					$badge = $clasesEstado[$estado] ?? 'secondary';
					$temas = isset($curso['temas']) && is_array($curso['temas']) ? $curso['temas'] : [];
					?>
					<details class="card shadow-sm">
						<summary class="card-header bg-white d-flex flex-wrap justify-content-between gap-2">
							<strong><?= $e($curso['curso'] ?? 'Curso') ?></strong>
							<span><span class="badge text-bg-<?= $e($badge) ?>"><?= $e($estado) ?></span> <?= number_format($avance, 0) ?>%</span>
						</summary>
						<div class="card-body">
							<?php if ($temas === []): ?>
								<p class="text-muted mb-0">Este curso no tiene temas activos.</p>
							<?php else: foreach ($temas as $tema): ?>
								<?php $avanceTema = min(100, max(0, (float) ($tema['porcentaje'] ?? 0))); $estadoTema = (string) ($tema['estado'] ?? 'No iniciado'); $badgeTema = $clasesEstado[$estadoTema] ?? 'secondary'; ?>
								<div class="border-bottom py-3">
									<div class="d-flex flex-wrap justify-content-between gap-2"><strong><?= $e($tema['tema'] ?? 'Tema') ?></strong><span class="badge text-bg-<?= $e($badgeTema) ?>"><?= $e($estadoTema) ?></span></div>
									<div class="text-muted small mt-1"><?= (int) ($tema['intentos'] ?? 0) ?> intentos &middot; promedio <?= number_format((float) ($tema['promedio'] ?? 0), 1) ?>%</div>
									<div class="progress mt-2" role="progressbar" aria-label="Avance de <?= $e($tema['tema'] ?? 'tema') ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) round($avanceTema) ?>"><div class="progress-bar" style="width: <?= $avanceTema ?>%"></div></div>
								</div>
							<?php endforeach; endif; ?>
						</div>
					</details>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	<?php else: ?>
		<section class="card shadow-sm">
			<div class="table-responsive">
				<form class="row g-2 p-3" method="get" action="progreso.php" role="search">
					<div class="col-sm-9"><label class="visually-hidden" for="buscar-estudiante">Buscar estudiante</label><input class="form-control" id="buscar-estudiante" name="buscar" type="search" value="<?= $e($buscar) ?>" placeholder="Buscar por nombre, código o correo"></div>
					<div class="col-sm-3 d-grid"><button class="btn btn-primary" type="submit">Buscar</button></div>
				</form>
				<table class="table table-hover align-middle mb-0">
					<thead class="table-light">
					<tr>
						<th>Estudiante</th>
						<th>Código</th>
						<th>Estado</th>
						<th>Cursos completados</th>
						<th>Avance promedio</th>
						<th></th>
					</tr>
					</thead>
					<tbody>
					<?php foreach ($estudiantes as $fila): ?>
						<?php $porcentaje = min(100, max(0, (float) ($fila['progreso_general'] ?? 0))); ?>
						<tr>
							<td><?= $e($fila['nombres'] . ' ' . $fila['apellidos']) ?><div class="small text-muted"><?= $e($fila['correo']) ?></div></td>
							<td><?= $e($fila['codigo']) ?></td>
							<td><span class="badge <?= $fila['estado'] === 'Activo' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $e($fila['estado']) ?></span></td>
							<td><?= (int) $fila['cursos_completados'] ?> / <?= (int) $fila['total_cursos'] ?></td>
							<td>
								<div class="d-flex align-items-center gap-2">
									<div class="progress flex-grow-1" role="progressbar" aria-label="Avance de <?= $e($fila['nombres'] . ' ' . $fila['apellidos']) ?>"
										 aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) round($porcentaje) ?>">
										<div class="progress-bar" style="width: <?= $porcentaje ?>%"></div>
									</div>
									<span><?= number_format($porcentaje, 0) ?>%</span>
								</div>
							</td>
							<td><a class="btn btn-sm btn-outline-primary" href="<?= $e('progreso.php?' . http_build_query(array_filter(['estudiante' => (int) $fila['id_usuario'], 'buscar' => $buscar !== '' ? $buscar : null], static fn($valor) => $valor !== null))) ?>">Ver detalle</a></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<?php if ($estudiantes === []): ?><p class="p-3 mb-0 text-muted"><?= $buscar !== '' ? 'No se encontraron estudiantes.' : 'Todavía no hay estudiantes registrados.' ?></p><?php endif; ?>
			</div>
		</section>
	<?php endif; ?>
</main>
</body>
</html>
