<?php
// Burbuja del asistente. Se incluye desde _navegacion.php (no en evaluaciones en curso).
if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}
if (empty($_SESSION['csrf_chat'])) {
	$_SESSION['csrf_chat'] = bin2hex(random_bytes(32));
}
$rutaAssetsChat = __DIR__ . '/../../public/assets/';
?>
<link rel="stylesheet" href="../../public/assets/css/chatbot.css?v=<?= (int) @filemtime($rutaAssetsChat . 'css/chatbot.css') ?>">
<div id="chatbot" class="chatbot" data-endpoint="asistente.php" data-csrf="<?= htmlspecialchars($_SESSION['csrf_chat'], ENT_QUOTES, 'UTF-8') ?>">
	<section id="chatbot-panel" class="chatbot-panel" role="dialog" aria-label="Asistente BeatCell" hidden>
		<header class="chatbot-cabecera">
			<div class="chatbot-titulo">
				<strong>Asistente BeatCell</strong>
				<small><span class="chatbot-en-linea"></span> En línea · Apoyo académico</small>
			</div>
			<div class="chatbot-controles">
				<button type="button" id="chatbot-ampliar" aria-label="Ampliar chat" aria-pressed="false" title="Ampliar chat">□</button>
				<button type="button" id="chatbot-cerrar" aria-label="Cerrar asistente" title="Cerrar">×</button>
			</div>
		</header>
		<div id="chatbot-mensajes" class="chatbot-mensajes" aria-live="polite"></div>
		<div id="chatbot-botones" class="chatbot-botones"></div>
		<form id="chatbot-form" class="chatbot-form" autocomplete="off">
			<input type="text" id="chatbot-texto" maxlength="300" placeholder="Escribe tu pregunta…" aria-label="Mensaje">
			<button type="submit">Enviar</button>
		</form>
	</section>
	<button type="button" id="chatbot-burbuja" class="chatbot-burbuja" aria-label="Abrir asistente" aria-expanded="false">
		💬<span id="chatbot-insignia" class="chatbot-insignia" hidden>0</span>
	</button>
</div>
<script src="../../public/assets/js/chatbot.js?v=<?= (int) @filemtime($rutaAssetsChat . 'js/chatbot.js') ?>" defer></script>
