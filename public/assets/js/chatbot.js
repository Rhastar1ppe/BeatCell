<<<<<<< HEAD
(function () {
	'use strict';
	var raiz = document.getElementById('chatbot');
	if (!raiz) { return; }

	var panel = document.getElementById('chatbot-panel');
	var burbuja = document.getElementById('chatbot-burbuja');
	var cerrar = document.getElementById('chatbot-cerrar');
	var ampliar = document.getElementById('chatbot-ampliar');
	var cuerpo = document.getElementById('chatbot-mensajes');
	var botones = document.getElementById('chatbot-botones');
	var form = document.getElementById('chatbot-form');
	var campo = document.getElementById('chatbot-texto');
	var insignia = document.getElementById('chatbot-insignia');
	var endpoint = raiz.dataset.endpoint;
	var csrf = raiz.dataset.csrf;
	var iniciado = false;
	var ocupado = false;
	var datosIniciales = null;

	function pedir(datos) {
		var cuerpoPost = new URLSearchParams(datos);
		cuerpoPost.append('csrf_chat', csrf);
		return fetch(endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: cuerpoPost.toString()
		}).then(function (r) { return r.json(); });
	}

	function bajar() { cuerpo.scrollTop = cuerpo.scrollHeight; }

	function burbujaMensaje(remitente, texto) {
		var div = document.createElement('div');
		div.className = 'chatbot-msg chatbot-msg--' + (remitente === 'Usuario' ? 'usuario' : 'bot');
		div.textContent = texto; // textContent: nunca interpreta HTML
		cuerpo.appendChild(div);
		return div;
	}

	function agregarEnlaces(enlaces) {
		if (!enlaces || !enlaces.length) { return; }
		var caja = document.createElement('div');
		caja.className = 'chatbot-enlaces';
		enlaces.forEach(function (e) {
			var a = document.createElement('a');
			a.href = e.url;
			a.textContent = e.texto;
			caja.appendChild(a);
		});
		cuerpo.appendChild(caja);
	}

	function pintarBotones(lista) {
		botones.textContent = '';
		(lista || []).forEach(function (texto) {
			var b = document.createElement('button');
			b.type = 'button';
			b.textContent = texto;
			b.addEventListener('click', function () { enviar(texto); });
			botones.appendChild(b);
		});
	}

	function pintarInsignia(n) {
		n = parseInt(n, 10) || 0;
		insignia.textContent = n > 9 ? '9+' : String(n);
		insignia.hidden = n < 1;
	}

	function error(texto) {
		burbujaMensaje('Bot', texto || 'No se pudo conectar. Inténtalo de nuevo.');
		bajar();
	}

	function mostrarInicio(r) {
		if (!r || !r.ok) { iniciado = false; return error(r && r.error); }
		cuerpo.textContent = '';
		r.historial.forEach(function (m) { burbujaMensaje(m.remitente, m.mensaje); });
		pintarBotones(r.botones);
		pintarInsignia(r.avisos);
		bajar();
		iniciado = true;
	}

	function iniciar() {
		if (datosIniciales) {
			mostrarInicio(datosIniciales);
			datosIniciales = null;
			return Promise.resolve();
		}
		return pedir({ accion: 'iniciar' }).then(mostrarInicio).catch(function () { iniciado = false; error(); });
	}

	function enviar(texto) {
		texto = (texto || '').trim();
		if (!texto || ocupado) { return; }
		ocupado = true;
		burbujaMensaje('Usuario', texto);
		campo.value = '';
		bajar();
		pedir({ accion: 'mensaje', mensaje: texto }).then(function (r) {
			if (!r.ok) { return error(r.error); }
			burbujaMensaje('Bot', r.respuesta.texto);
			agregarEnlaces(r.respuesta.enlaces);
			pintarBotones(r.respuesta.botones);
			pintarInsignia(r.avisos);
			bajar();
		}).catch(function () { error(); }).then(function () { ocupado = false; });
	}

	function alternar(abrir) {
		panel.hidden = !abrir;
		burbuja.setAttribute('aria-expanded', abrir ? 'true' : 'false');
		if (abrir) {
			if (!iniciado) { iniciar(); }
			campo.focus();
		}
	}

	burbuja.addEventListener('click', function () { alternar(panel.hidden); });
	cerrar.addEventListener('click', function () { alternar(false); burbuja.focus(); });
	if (ampliar) {
		ampliar.addEventListener('click', function () {
			var grande = panel.classList.toggle('chatbot-panel--grande');
			ampliar.setAttribute('aria-pressed', grande ? 'true' : 'false');
			ampliar.setAttribute('aria-label', grande ? 'Reducir chat' : 'Ampliar chat');
			ampliar.title = grande ? 'Reducir chat' : 'Ampliar chat';
			ampliar.textContent = grande ? '▣' : '□';
		});
	}
	document.addEventListener('keydown', function (ev) {
		if (ev.key === 'Escape' && !panel.hidden) { alternar(false); burbuja.focus(); }
	});
	form.addEventListener('submit', function (ev) { ev.preventDefault(); enviar(campo.value); });

	// Prepara el saludo y la insignia sin abrir el panel.
	pedir({ accion: 'iniciar' }).then(function (r) {
		if (r.ok) { datosIniciales = r; pintarInsignia(r.avisos); }
	}).catch(function () {});
})();
=======
(function () {
	'use strict';
	var raiz = document.getElementById('chatbot');
	if (!raiz) { return; }

	var panel = document.getElementById('chatbot-panel');
	var burbuja = document.getElementById('chatbot-burbuja');
	var cerrar = document.getElementById('chatbot-cerrar');
	var ampliar = document.getElementById('chatbot-ampliar');
	var cuerpo = document.getElementById('chatbot-mensajes');
	var botones = document.getElementById('chatbot-botones');
	var form = document.getElementById('chatbot-form');
	var campo = document.getElementById('chatbot-texto');
	var insignia = document.getElementById('chatbot-insignia');
	var endpoint = raiz.dataset.endpoint;
	var csrf = raiz.dataset.csrf;
	var iniciado = false;
	var ocupado = false;
	var datosIniciales = null;

	function pedir(datos) {
		var cuerpoPost = new URLSearchParams(datos);
		cuerpoPost.append('csrf_chat', csrf);
		return fetch(endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: cuerpoPost.toString()
		}).then(function (r) { return r.json(); });
	}

	function bajar() { cuerpo.scrollTop = cuerpo.scrollHeight; }

	function burbujaMensaje(remitente, texto) {
		var div = document.createElement('div');
		div.className = 'chatbot-msg chatbot-msg--' + (remitente === 'Usuario' ? 'usuario' : 'bot');
		div.textContent = texto; // textContent: nunca interpreta HTML
		cuerpo.appendChild(div);
		return div;
	}

	function agregarEnlaces(enlaces) {
		if (!enlaces || !enlaces.length) { return; }
		var caja = document.createElement('div');
		caja.className = 'chatbot-enlaces';
		enlaces.forEach(function (e) {
			var a = document.createElement('a');
			a.href = e.url;
			a.textContent = e.texto;
			caja.appendChild(a);
		});
		cuerpo.appendChild(caja);
	}

	function pintarBotones(lista) {
		botones.textContent = '';
		(lista || []).forEach(function (texto) {
			var b = document.createElement('button');
			b.type = 'button';
			b.textContent = texto;
			b.addEventListener('click', function () { enviar(texto); });
			botones.appendChild(b);
		});
	}

	function pintarInsignia(n) {
		n = parseInt(n, 10) || 0;
		insignia.textContent = n > 9 ? '9+' : String(n);
		insignia.hidden = n < 1;
	}

	function error(texto) {
		burbujaMensaje('Bot', texto || 'No se pudo conectar. Inténtalo de nuevo.');
		bajar();
	}

	function mostrarInicio(r) {
		if (!r || !r.ok) { iniciado = false; return error(r && r.error); }
		cuerpo.textContent = '';
		r.historial.forEach(function (m) { burbujaMensaje(m.remitente, m.mensaje); });
		pintarBotones(r.botones);
		pintarInsignia(r.avisos);
		bajar();
		iniciado = true;
	}

	function iniciar() {
		if (datosIniciales) {
			mostrarInicio(datosIniciales);
			datosIniciales = null;
			return Promise.resolve();
		}
		return pedir({ accion: 'iniciar' }).then(mostrarInicio).catch(function () { iniciado = false; error(); });
	}

	function enviar(texto) {
		texto = (texto || '').trim();
		if (!texto || ocupado) { return; }
		ocupado = true;
		burbujaMensaje('Usuario', texto);
		campo.value = '';
		bajar();
		pedir({ accion: 'mensaje', mensaje: texto }).then(function (r) {
			if (!r.ok) { return error(r.error); }
			burbujaMensaje('Bot', r.respuesta.texto);
			agregarEnlaces(r.respuesta.enlaces);
			pintarBotones(r.respuesta.botones);
			pintarInsignia(r.avisos);
			bajar();
		}).catch(function () { error(); }).then(function () { ocupado = false; });
	}

	function alternar(abrir) {
		panel.hidden = !abrir;
		burbuja.setAttribute('aria-expanded', abrir ? 'true' : 'false');
		if (abrir) {
			if (!iniciado) { iniciar(); }
			campo.focus();
		}
	}

	burbuja.addEventListener('click', function () { alternar(panel.hidden); });
	cerrar.addEventListener('click', function () { alternar(false); burbuja.focus(); });
	if (ampliar) {
		ampliar.addEventListener('click', function () {
			var grande = panel.classList.toggle('chatbot-panel--grande');
			ampliar.setAttribute('aria-pressed', grande ? 'true' : 'false');
			ampliar.setAttribute('aria-label', grande ? 'Reducir chat' : 'Ampliar chat');
			ampliar.title = grande ? 'Reducir chat' : 'Ampliar chat';
			ampliar.textContent = grande ? '▣' : '□';
		});
	}
	document.addEventListener('keydown', function (ev) {
		if (ev.key === 'Escape' && !panel.hidden) { alternar(false); burbuja.focus(); }
	});
	form.addEventListener('submit', function (ev) { ev.preventDefault(); enviar(campo.value); });

	// Prepara el saludo y la insignia sin abrir el panel.
	pedir({ accion: 'iniciar' }).then(function (r) {
		if (r.ok) { datosIniciales = r; pintarInsignia(r.avisos); }
	}).catch(function () {});
})();
>>>>>>> upstream/main
