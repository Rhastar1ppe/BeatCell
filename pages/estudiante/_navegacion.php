<?php
require_once __DIR__ . '/../../src/Support/ViewHelpers.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

$usuarioNavegacion = $_SESSION['usuario'] ?? [];
$nombreNavegacion = trim(
	(string) ($usuarioNavegacion['nombres'] ?? '') . ' ' .
	(string) ($usuarioNavegacion['apellidos'] ?? '')
);
$nombreNavegacion = $nombreNavegacion !== '' ? $nombreNavegacion : 'Estudiante';

if (empty($_SESSION['csrf_logout'])) {
	$_SESSION['csrf_logout'] = bin2hex(random_bytes(32));
}

$archivoNavegacion = basename($_SERVER['SCRIPT_NAME'] ?? '');
$paginaNavegacion = match ($archivoNavegacion) {
	'principal.php' => 'inicio',
	'cursos.php', 'curso.php' => 'cursos',
	'actividades.php', 'actividad.php' => 'actividades',
	'progreso.php', 'progreso_vista.php' => 'progreso',
	'historial.php' => 'historial',
	default => '',
};
$actividadEnCurso = $actividadEnCurso ?? false;

$enlacesNavegacion = [
	'inicio' => ['Inicio', 'principal.php'],
	'cursos' => ['Cursos', 'cursos.php'],
	'actividades' => ['Actividades', 'actividades.php'],
	'progreso' => ['Mi progreso', 'progreso.php'],
	'historial' => ['Historial', 'historial.php'],
];
?>
<div class="barra-estudiante">
	<?php if ($actividadEnCurso): ?>
		<div class="marca-estudiante">
			<span class="marca-estudiante-nombre">BeatCell</span>
			<span class="marca-estudiante-subtitulo">Academia de Tecnología Celular</span>
		</div>
		<span class="estado-evaluacion">Evaluación en curso</span>
	<?php else: ?>
		<a class="marca-estudiante" href="principal.php" aria-label="BeatCell, ir al inicio">
			<span class="marca-estudiante-nombre">BeatCell</span>
			<span class="marca-estudiante-subtitulo">Academia de Tecnología Celular</span>
		</a>
		<nav class="navegacion-estudiante" aria-label="Navegación del estudiante">
			<?php foreach ($enlacesNavegacion as $clave => [$texto, $destino]): ?>
				<a href="<?= e($destino) ?>"<?= $paginaNavegacion === $clave ? ' aria-current="page"' : '' ?>>
					<?= e($texto) ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<div class="cuenta-estudiante">
			<span class="nombre-estudiante"><?= e($nombreNavegacion) ?></span>
			<form action="../auth/logout.php" method="post">
				<input type="hidden" name="csrf_logout" value="<?= e($_SESSION['csrf_logout']) ?>">
				<button type="submit">Cerrar sesión</button>
			</form>
		</div>
	<?php endif; ?>
</div>

<?php if (!$actividadEnCurso) { require __DIR__ . '/_chatbot.php'; } ?>