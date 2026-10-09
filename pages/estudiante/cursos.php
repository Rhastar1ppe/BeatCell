<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/Middleware/AuthMiddleware.php';
AuthMiddleware::requireRole(['Estudiante'], '../auth/login.php');
header('Cache-Control: no-store');
require_once __DIR__ . '/../../database/database.php';

$cursos = [];
$error = '';

try {
	$db = (new Database())->connect();
	$consulta = $db->query(
		"SELECT c.id_curso, c.nombre, c.descripcion,
				(SELECT COUNT(*) FROM modulos m
				 WHERE m.id_curso = c.id_curso AND m.estado = 'Activo') AS total_modulos
		 FROM cursos c
		 WHERE c.estado = 'Activo'
		 ORDER BY c.nombre, c.id_curso"
	);
	$cursos = $consulta->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $exception) {
	error_log('BeatCell - cursos estudiante: ' . $exception->getMessage());
	$error = 'No se pudieron cargar los cursos. Inténtalo nuevamente más tarde.';
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
	<title>Cursos | BeatCell</title>
	<link rel="stylesheet" href="../../public/assets/css/cursos.css?v=<?= filemtime(__DIR__ . '/../../public/assets/css/cursos.css') ?>">
	<link rel="stylesheet" href="../../public/assets/css/estudiante.css?v=<?= filemtime(__DIR__ . '/../../public/assets/css/estudiante.css') ?>">
</head>
<body>
<?php require __DIR__ . '/_navegacion.php'; ?>
<main class="contenedor-cursos">
	<header class="encabezado-cursos">
		<p class="marca">BeatCell · Aprendizaje</p>
		<h1>Cursos disponibles</h1>
		<p>Selecciona un curso para consultar sus módulos, temas y actividades.</p>
	</header>

	<?php if ($error !== ''): ?>
		<p class="aviso aviso-error" role="alert"><?= $e($error) ?></p>
	<?php elseif ($cursos === []): ?>
		<p class="aviso">Todavía no hay cursos activos disponibles.</p>
	<?php else: ?>
		<section class="lista-cursos" aria-label="Cursos activos">
			<?php foreach ($cursos as $curso): ?>
				<article class="tarjeta-curso">
					<p class="curso-etiqueta">Curso</p>
					<h2><?= $e($curso['nombre']) ?></h2>
					<?php if (!empty($curso['descripcion'])): ?>
						<p class="texto"><?= $e($curso['descripcion']) ?></p>
					<?php endif; ?>
					<p class="curso-modulos">Módulos activos: <?= (int) $curso['total_modulos'] ?></p>
					<a class="boton" href="curso.php?id=<?= (int) $curso['id_curso'] ?>">Abrir curso</a>
				</article>
			<?php endforeach; ?>
		</section>
	<?php endif; ?>
</main>
</body>
</html>
