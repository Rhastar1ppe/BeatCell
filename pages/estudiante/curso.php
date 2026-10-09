<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/Middleware/AuthMiddleware.php';
$sesionUsuario = AuthMiddleware::requireRole(['Estudiante'], '../auth/login.php');
header('Cache-Control: no-store');
require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../../src/Models/TiempoActividad.php';

$idCurso = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
	'options' => ['min_range' => 1],
]);
$curso = null;
$modulos = [];
$temasPorModulo = [];
$actividadesPorTema = [];
$error = '';

if ($idCurso === false || $idCurso === null) {
	http_response_code(404);
	$error = 'El curso no existe o no está disponible.';
} else {
	try {
		$db = (new Database())->connect();

		$consulta = $db->prepare(
			"SELECT id_curso, nombre, descripcion
			 FROM cursos WHERE id_curso = ? AND estado = 'Activo'"
		);
		$consulta->execute([$idCurso]);
		$curso = $consulta->fetch(PDO::FETCH_ASSOC);

		if (!$curso) {
			http_response_code(404);
			$error = 'El curso no existe o no está disponible.';
		} else {
			$consulta = $db->prepare(
				"SELECT m.* FROM modulos m
				 WHERE m.id_curso = ? AND m.estado = 'Activo'
				 ORDER BY m.orden, m.id_modulo"
			);
			$consulta->execute([$idCurso]);
			$modulos = $consulta->fetchAll(PDO::FETCH_ASSOC);

			$consulta = $db->prepare(
				"SELECT t.* FROM temas t
				 INNER JOIN modulos m ON m.id_modulo = t.id_modulo
				 WHERE m.id_curso = ? AND m.estado = 'Activo' AND t.estado = 'Activo'
				 ORDER BY m.orden, t.orden, t.id_tema"
			);
			$consulta->execute([$idCurso]);
			foreach ($consulta->fetchAll(PDO::FETCH_ASSOC) as $tema) {
				$temasPorModulo[(int) $tema['id_modulo']][] = $tema;
			}

			$consulta = $db->prepare(
				"SELECT a.*,
						(SELECT COUNT(*) FROM preguntas p
						 WHERE p.id_actividad = a.id_actividad AND p.estado = 'Activo') AS preguntas_activas,
						EXISTS (
							SELECT 1 FROM resultados r
							WHERE r.id_actividad = a.id_actividad AND r.id_usuario = ?
						) AS realizada
				 FROM actividades a
				 INNER JOIN temas t ON t.id_tema = a.id_tema
				 INNER JOIN modulos m ON m.id_modulo = t.id_modulo
				 WHERE m.id_curso = ? AND m.estado = 'Activo'
				   AND t.estado = 'Activo' AND a.estado = 'Activo'
				 ORDER BY m.orden, t.orden, a.id_actividad"
			);
			$consulta->execute([(int) $sesionUsuario['id_usuario'], $idCurso]);
			foreach ($consulta->fetchAll(PDO::FETCH_ASSOC) as $actividad) {
				$actividadesPorTema[(int) $actividad['id_tema']][] = $actividad;
			}
		}
	} catch (Throwable $exception) {
		error_log('BeatCell - detalle curso estudiante: ' . $exception->getMessage());
		http_response_code(503);
		$error = 'No se pudo cargar el curso. Inténtalo nuevamente más tarde.';
	}
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
	<title><?= $e($curso['nombre'] ?? 'Curso') ?> | BeatCell</title>
	<link rel="stylesheet" href="../../public/assets/css/cursos.css?v=<?= filemtime(__DIR__ . '/../../public/assets/css/cursos.css') ?>">
	<link rel="stylesheet" href="../../public/assets/css/estudiante.css?v=<?= filemtime(__DIR__ . '/../../public/assets/css/estudiante.css') ?>">
</head>
<body>
<?php require __DIR__ . '/_navegacion.php'; ?>
<main class="contenedor-cursos">
	<?php if ($error !== ''): ?>
		<h1>Curso no disponible</h1>
		<p class="aviso aviso-error" role="alert"><?= $e($error) ?></p>
	<?php else: ?>
		<header class="encabezado-cursos">
			<p class="marca">BeatCell · Curso</p>
			<h1><?= $e($curso['nombre']) ?></h1>
			<?php if (!empty($curso['descripcion'])): ?><p><?= $e($curso['descripcion']) ?></p><?php endif; ?>
		</header>

		<?php if ($modulos === []): ?>
			<p class="aviso">Este curso todavía no tiene módulos activos.</p>
		<?php else: ?>
			<section class="buscador-curso" aria-label="Buscar contenido del curso">
				<label for="buscar-contenido-curso">Buscar módulo, tema o actividad</label>
				<div class="campo-busqueda-curso">
					<span aria-hidden="true" class="icono-busqueda"></span>
					<input
						id="buscar-contenido-curso"
						type="search"
						placeholder="Escribe un nombre o palabra clave"
						autocomplete="off"
						aria-describedby="estado-busqueda-curso"
					>
				</div>
				<p id="estado-busqueda-curso" class="estado-busqueda-curso" role="status" aria-live="polite">
					Escribe para filtrar el contenido de este curso.
				</p>
			</section>
			<section class="lista-modulos" aria-label="Módulos del curso">
				<?php foreach ($modulos as $modulo): ?>
					<details class="modulo" data-course-module>
						<summary>
							<span class="modulo-titulo">
								<span class="etiqueta-jerarquia">Módulo <?= (int) $modulo['orden'] ?></span>
								<span><?= $e($modulo['nombre']) ?></span>
							</span>
						</summary>
						<div class="modulo-contenido">
							<?php if (!empty($modulo['descripcion'])): ?><p class="texto"><?= $e($modulo['descripcion']) ?></p><?php endif; ?>

							<?php $temas = $temasPorModulo[(int) $modulo['id_modulo']] ?? []; ?>
							<?php if ($temas === []): ?>
								<p class="aviso">Este módulo todavía no tiene temas activos.</p>
							<?php else: ?>
								<div class="lista-temas">
									<?php foreach ($temas as $tema): ?>
										<details class="tema" data-course-topic>
											<summary>
												<span class="tema-titulo">
													<span class="etiqueta-jerarquia">Tema <?= (int) $tema['orden'] ?></span>
													<span><?= $e($tema['nombre']) ?></span>
												</span>
											</summary>
											<div class="tema-contenido">
												<?php if (!empty($tema['descripcion'])): ?><p class="texto"><?= $e($tema['descripcion']) ?></p><?php endif; ?>
												<?php if (!empty($tema['material_apoyo'])): ?>
													<h4>Material de apoyo</h4>
													<p class="texto material-apoyo"><?= nl2br($e($tema['material_apoyo'])) ?></p>
												<?php endif; ?>

												<?php $actividades = $actividadesPorTema[(int) $tema['id_tema']] ?? []; ?>
												<h4>Actividades</h4>
												<?php if ($actividades === []): ?>
													<p class="aviso">Este tema todavía no tiene actividades activas.</p>
												<?php else: ?>
													<div class="lista-actividades-curso">
														<?php foreach ($actividades as $actividad): ?>
															<article class="actividad-curso<?= (int) $actividad['realizada'] === 1 ? ' actividad-realizada' : '' ?>" data-course-activity>
																<?php if ((int) $actividad['realizada'] === 1): ?>
																	<span class="estado-actividad realizada" aria-label="Actividad realizada">
																		<span aria-hidden="true">✓</span> Realizada
																	</span>
																<?php endif; ?>
																<h5><?= $e($actividad['titulo']) ?></h5>
																<?php if (!empty($actividad['descripcion'])): ?><p class="texto"><?= $e($actividad['descripcion']) ?></p><?php endif; ?>
																<p class="actividad-meta">
																	Tipo: <?= $e($actividad['tipo']) ?> ·
																	Preguntas: <?= (int) $actividad['preguntas_activas'] ?> ·
																	Tiempo: <?= $e(TiempoActividad::describir($actividad['tiempo_limite'], $actividad['modo_tiempo'] ?? null)) ?> ·
																	Mínimo para aprobar: <?= $e($actividad['puntaje_minimo'] ?? 70) ?>%
																</p>
																<?php if ((int) $actividad['preguntas_activas'] > 0): ?>
																	<a class="boton" href="actividad.php?id=<?= (int) $actividad['id_actividad'] ?>">
																		<?= (int) $actividad['realizada'] === 1 ? 'Ver estado' : 'Resolver actividad' ?>
																	</a>
																<?php else: ?>
																	<p class="aviso">Esta actividad todavía no tiene preguntas activas.</p>
																<?php endif; ?>
															</article>
														<?php endforeach; ?>
													</div>
												<?php endif; ?>
											</div>
										</details>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</div>
					</details>
				<?php endforeach; ?>
			</section>
			<p class="aviso aviso-sin-resultados" hidden>No se encontró contenido con esa búsqueda.</p>
		<?php endif; ?>
	<?php endif; ?>
</main>
<?php if ($error === '' && $modulos !== []): ?>
	<script src="../../public/assets/js/cursos.js?v=<?= filemtime(__DIR__ . '/../../public/assets/js/cursos.js') ?>" defer></script>
<?php endif; ?>
</body>
</html>
