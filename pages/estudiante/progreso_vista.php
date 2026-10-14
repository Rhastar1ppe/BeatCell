<?php
$cursos = isset($cursos) && is_array($cursos) ? $cursos : [];
$escapar = static fn($valor): string => htmlspecialchars(
    (string) $valor,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
$porcentajeSeguro = static fn($valor): float => min(100, max(0, (float) $valor));
$totalCursos = count($cursos);
$cursosCompletados = 0;
$sumaPorcentajes = 0.0;

foreach ($cursos as $curso) {
    $avance = $porcentajeSeguro($curso['porcentaje'] ?? 0);
    $sumaPorcentajes += $avance;
    if (($curso['estado'] ?? '') === 'Completado') {
        $cursosCompletados++;
    }
}

$avanceGeneral = $totalCursos > 0 ? round($sumaPorcentajes / $totalCursos) : 0;
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$baseUrl = preg_replace('~/(?:public|pages)/.*$~', '', $scriptName) ?? '';
$baseUrl = rtrim($baseUrl, '/');
$urlCss = $baseUrl . '/public/assets/css/progreso.css';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mi progreso | BeatCell</title>
    <link rel="stylesheet" href="<?= $escapar($urlCss) ?>">
</head>
<body class="pagina-progreso">
    <main class="contenedor-progreso">
        <a href="principal.php" class="btn-salida">&larr; Volver al panel</a>
        <header class="encabezado-progreso">
            <div>
                <p class="sobrelinea">TU APRENDIZAJE</p>
                <h1>Mi progreso</h1>
                <p class="descripcion-pagina">Revisa cuánto has avanzado en tus cursos y temas.</p>
            </div>
            <div class="resumen-general" aria-label="Avance promedio de tus cursos">
                <span class="resumen-etiqueta">Avance promedio</span>
                <strong><?= $avanceGeneral ?>%</strong>
                <span class="resumen-detalle"><?= $cursosCompletados ?> de <?= $totalCursos ?> cursos completados</span>
            </div>
        </header>

        <?php if ($cursos === []): ?>
            <section class="estado-vacio" aria-live="polite">
                <span class="icono-vacio" aria-hidden="true">♪</span>
                <h2>Aún no hay cursos disponibles</h2>
                <p>Cuando haya cursos activos, aquí podrás seguir tu avance.</p>
            </section>
        <?php else: ?>
            <section class="lista-cursos" aria-label="Progreso por curso">
                <?php foreach ($cursos as $indice => $curso): ?>
                    <?php
                    $avanceCurso = $porcentajeSeguro($curso['porcentaje'] ?? 0);
                    $estadoCurso = (string) ($curso['estado'] ?? 'No iniciado');
                    $claseEstadoCurso = strtolower(str_replace(' ', '-', $estadoCurso));
                    $temas = isset($curso['temas']) && is_array($curso['temas'])
                        ? $curso['temas']
                        : [];
                    ?>
                    <details class="tarjeta-curso" <?= $indice === 0 ? 'open' : '' ?>>
                        <summary class="encabezado-curso">
                            <span class="curso-indicador" aria-hidden="true">♪</span>
                            <span class="curso-info">
                                <span class="curso-nombre"><?= $escapar($curso['curso'] ?? 'Curso') ?></span>
                                <span class="curso-meta"><?= count($temas) ?> temas</span>
                            </span>
                            <span class="estado estado-<?= $escapar($claseEstadoCurso) ?>"><?= $escapar($estadoCurso) ?></span>
                            <span class="curso-porcentaje"><?= number_format($avanceCurso, 0) ?>%</span>
                            <span class="flecha-detalle" aria-hidden="true"></span>
                            <span class="barra-progreso curso-barra" role="progressbar"
                                aria-label="Avance de <?= $escapar($curso['curso'] ?? 'curso') ?>"
                                aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) round($avanceCurso) ?>">
                                <span style="width: <?= $avanceCurso ?>%"></span>
                            </span>
                        </summary>

                        <div class="temas-curso">
                            <?php if ($temas === []): ?>
                                <p class="sin-temas">Este curso todavía no tiene temas activos.</p>
                            <?php else: ?>
                                <?php foreach ($temas as $tema): ?>
                                    <?php
                                    $avanceTema = $porcentajeSeguro($tema['porcentaje'] ?? 0);
                                    $estadoTema = (string) ($tema['estado'] ?? 'No iniciado');
                                    $claseEstadoTema = strtolower(str_replace(' ', '-', $estadoTema));
                                    ?>
                                    <article class="fila-tema">
                                        <div class="tema-titulo">
                                            <span class="punto-tema" aria-hidden="true"></span>
                                            <div>
                                                <h3><?= $escapar($tema['tema'] ?? 'Tema') ?></h3>
                                                <p>
                                                    <?= (int) ($tema['intentos'] ?? 0) ?> intentos
                                                    <span aria-hidden="true">·</span>
                                                    Promedio <?= number_format((float) ($tema['promedio'] ?? 0), 1) ?>%
                                                </p>
                                            </div>
                                        </div>
                                        <span class="estado estado-<?= $escapar($claseEstadoTema) ?>"><?= $escapar($estadoTema) ?></span>
                                        <div class="tema-avance">
                                            <span><?= number_format($avanceTema, 0) ?>%</span>
                                            <span class="barra-progreso" role="progressbar"
                                                aria-label="Avance de <?= $escapar($tema['tema'] ?? 'tema') ?>"
                                                aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) round($avanceTema) ?>">
                                                <span style="width: <?= $avanceTema ?>%"></span>
                                            </span>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </details>
                <?php endforeach; ?>
            </section>
            <p class="nota-progreso">Cada actividad se registra una sola vez; al finalizarla, cuenta para tu avance.</p>
        <?php endif; ?>
    </main>
</body>
</html>