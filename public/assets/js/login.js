
(() => {
	'use strict';

	try {
		iniciar();
	} catch (error) {
		// Ante cualquier fallo se desactivan las mejoras y el login nativo sigue disponible.
		if (window.console && typeof window.console.warn === 'function') {
			window.console.warn('BeatCell login.js: mejoras desactivadas.', error);
		}
	}

	function iniciar() {
		const campoCorreo = document.querySelector('form input[name="email"]');
		const campoClave = document.querySelector('form input[name="password"]');
		if (!campoCorreo || !campoClave) {
			return;
		}

		const formulario = campoClave.form;
		if (!formulario || formulario !== campoCorreo.form) {
			return;
		}
		if (formulario.dataset.bcLogin === 'listo') {
			return; 
		}
		formulario.dataset.bcLogin = 'listo';

		const botonEnviar = formulario.querySelector('button[type="submit"], input[type="submit"]');
		const contenidoOriginal = botonEnviar && botonEnviar.tagName === 'BUTTON' ? botonEnviar.innerHTML : null;
		let enviando = false;
		let temporizadorSeguridad = null;

		inyectarEstilos();
		mejorarAccesibilidad();

		formulario.noValidate = true;

		/* ---------- Alerta propia para errores de validación ---------- */

		const alerta = document.createElement('div');
		alerta.className = 'alert alert-danger bc-login-alerta';
		alerta.setAttribute('role', 'alert');
		alerta.hidden = true;
		formulario.parentNode.insertBefore(alerta, formulario);

		const quitarMarca = (campo) => {
			campo.classList.remove('is-invalid');
			campo.removeAttribute('aria-invalid');
		};

		const ocultarAlerta = () => {
			alerta.hidden = true;
			alerta.textContent = '';
			quitarMarca(campoCorreo);
			quitarMarca(campoClave);
		};

		const mostrarError = (mensaje, campo) => {
			alerta.textContent = mensaje;
			alerta.hidden = false;
			campo.classList.add('is-invalid');
			campo.setAttribute('aria-invalid', 'true');
			campo.focus();
		};

		/* ---------- Validación (solo ayuda; el servidor valida igualmente) ---------- */

		const validar = () => {
			campoCorreo.value = campoCorreo.value.trim();

			if (campoCorreo.value === '') {
				mostrarError('Ingresa tu correo electrónico.', campoCorreo);
				return false;
			}
			if (typeof campoCorreo.checkValidity === 'function' && !campoCorreo.checkValidity()) {
				mostrarError('Escribe un correo válido, por ejemplo correo@ejemplo.com.', campoCorreo);
				return false;
			}
			if (campoClave.value === '') {
				mostrarError('Ingresa tu contraseña.', campoClave);
				return false;
			}
			return true;
		};

		/* ---------- Ver / ocultar contraseña ---------- */

		const grupoClave = campoClave.closest('.input-group');
		if (grupoClave) {
			const iconoVer = '<svg viewBox="0 0 16 16" aria-hidden="true"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.17 8C2.03 6.76 4.6 3.5 8 3.5S13.97 6.76 14.83 8C13.97 9.24 11.4 12.5 8 12.5S2.03 9.24 1.17 8"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/></svg>';
			const iconoOcultar = '<svg viewBox="0 0 16 16" aria-hidden="true"><path d="M13.36 11.65 15.35 13.65l-.7.7-14-14 .7-.7 2.2 2.2C4.6 1.2 6.2.5 8 .5c5 0 8 5.5 8 5.5s-.9 1.65-2.64 3.2zM8 1.5c-1.4 0-2.7.5-3.8 1.2l1.1 1.1A3.5 3.5 0 0 1 11.5 8c0 .7-.2 1.3-.5 1.8l1.6 1.6C14 10.3 14.8 9 15 8.5 14.1 7.2 11.6 1.5 8 1.5M.65 8.5C1.5 9.8 4 14.5 8 14.5c1 0 1.9-.3 2.7-.7l-1.1-1.1A3.5 3.5 0 0 1 4.5 7.5c0-.6.2-1.2.5-1.7L3.6 4.4C1.8 5.9.9 7.5.65 8.5"/></svg>';

			const botonVer = document.createElement('button');
			botonVer.type = 'button';
			botonVer.className = 'bc-login-ver';
			botonVer.setAttribute('aria-label', 'Mostrar contraseña');
			botonVer.setAttribute('aria-pressed', 'false');
			botonVer.innerHTML = iconoVer;

			campoClave.classList.add('bc-login-con-ver');
			grupoClave.appendChild(botonVer);

			botonVer.addEventListener('click', () => {
				const mostrar = campoClave.type === 'password';
				campoClave.type = mostrar ? 'text' : 'password';
				botonVer.setAttribute('aria-pressed', String(mostrar));
				botonVer.setAttribute('aria-label', mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña');
				botonVer.innerHTML = mostrar ? iconoOcultar : iconoVer;
				campoClave.focus();
			});
		}

		/* ---------- Aviso de Bloq Mayús ---------- */

		const avisoMayusculas = document.createElement('div');
		avisoMayusculas.className = 'bc-login-aviso';
		avisoMayusculas.setAttribute('role', 'status');
		avisoMayusculas.textContent = 'El bloqueo de mayúsculas está activado.';
		avisoMayusculas.hidden = true;
		(grupoClave || campoClave).insertAdjacentElement('afterend', avisoMayusculas);

		const revisarMayusculas = (evento) => {
			if (typeof evento.getModifierState === 'function') {
				avisoMayusculas.hidden = !evento.getModifierState('CapsLock');
			}
		};
		campoClave.addEventListener('keydown', revisarMayusculas);
		campoClave.addEventListener('keyup', revisarMayusculas);
		campoClave.addEventListener('blur', () => {
			avisoMayusculas.hidden = true;
		});

		/* ---------- Edición ---------- */

		campoCorreo.addEventListener('input', () => quitarMarca(campoCorreo));
		campoClave.addEventListener('input', () => quitarMarca(campoClave));
		campoCorreo.addEventListener('blur', () => {
			campoCorreo.value = campoCorreo.value.trim();
		});

		/* ---------- Envío ---------- */

		// No se usa "disabled": un botón deshabilitado deja de enviar su name/value
		// y podría alterar lo que recibe el servidor. Se bloquea con una bandera.
		const restablecer = () => {
			enviando = false;
			window.clearTimeout(temporizadorSeguridad);
			if (botonEnviar) {
				botonEnviar.classList.remove('bc-login-ocupado');
				botonEnviar.removeAttribute('aria-busy');
				botonEnviar.removeAttribute('aria-disabled');
				if (contenidoOriginal !== null) {
					botonEnviar.innerHTML = contenidoOriginal;
				}
			}
		};

		formulario.addEventListener('submit', (evento) => {
			try {
				if (enviando) {
					evento.preventDefault();
					return;
				}

				ocultarAlerta();
				if (!validar()) {
					evento.preventDefault();
					return;
				}

				enviando = true;
				if (botonEnviar) {
					botonEnviar.classList.add('bc-login-ocupado');
					botonEnviar.setAttribute('aria-busy', 'true');
					botonEnviar.setAttribute('aria-disabled', 'true');
					if (contenidoOriginal !== null) {
						botonEnviar.innerHTML =
							'<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Ingresando…';
					}
				}
				// Si la red falla y la página no avanza, el botón no queda bloqueado para siempre.
				temporizadorSeguridad = window.setTimeout(restablecer, 15000);
			} catch (error) {
				// Un error aquí nunca debe impedir el envío normal del formulario.
				enviando = false;
			}
		});

		// Al volver con "Atrás", el navegador puede restaurar la página con el botón bloqueado.
		window.addEventListener('pageshow', (evento) => {
			if (evento.persisted) {
				restablecer();
			}
		});

		/* ---------- Enfoque inicial ---------- */

		if (document.querySelector('.auth-card .alert-danger:not(.bc-login-alerta)')) {
			// El servidor devuelve el correo escrito; tras un error lo que falta es la contraseña.
			(campoCorreo.value === '' ? campoCorreo : campoClave).focus();
		} else if (document.querySelector('.auth-card .alert-success')) {
			campoCorreo.focus();
		}

		// Tras registrarse, se limpia la URL para que al recargar no reaparezca el aviso.
		try {
			const direccion = new URL(window.location.href);
			if (direccion.searchParams.has('registro') && window.history && window.history.replaceState) {
				direccion.searchParams.delete('registro');
				window.history.replaceState(null, '', direccion.pathname + direccion.search + direccion.hash);
			}
		} catch (error) {
			/* Sin acceso al historial: no pasa nada. */
		}

		/* ---------- Auxiliares ---------- */

		// Asocia cada etiqueta con su campo y activa el autocompletado del navegador,
		// solo donde el HTML original no lo trae.
		function mejorarAccesibilidad() {
			[[campoCorreo, 'bc-login-correo', 'username'], [campoClave, 'bc-login-clave', 'current-password']]
				.forEach(([campo, idPropio, autocompletar]) => {
					if (!campo.id) {
						campo.id = idPropio;
					}
					if (!campo.hasAttribute('autocomplete')) {
						campo.setAttribute('autocomplete', autocompletar);
					}
					const contenedor = campo.closest('.mb-3, .form-group');
					const anterior = (campo.closest('.input-group') || campo).previousElementSibling;
					const etiqueta = (contenedor && contenedor.querySelector('label'))
						|| (anterior && anterior.tagName === 'LABEL' ? anterior : null);
					if (etiqueta && !etiqueta.htmlFor) {
						etiqueta.htmlFor = campo.id;
					}
				});
		}

		function inyectarEstilos() {
			if (document.getElementById('bc-login-estilos')) {
				return;
			}
			const estilos = document.createElement('style');
			estilos.id = 'bc-login-estilos';
			estilos.textContent = [
				'.bc-login-ver{display:flex;align-items:center;justify-content:center;padding:0 .9rem;border:1px solid #ced4da;border-left:none;border-radius:0 10px 10px 0;background:#f8f9fa;color:#6c757d;cursor:pointer}',
				'.bc-login-ver:hover{background:#eef3ff;color:#0d6efd}',
				'.bc-login-ver:focus-visible{outline:2px solid #0d6efd;outline-offset:-2px}',
				'.bc-login-ver svg{width:1.1em;height:1.1em;fill:currentColor}',
				'.input-group > .form-control.bc-login-con-ver{border-right:none;border-radius:0}',
				'.bc-login-aviso{margin-top:.35rem;font-size:.85rem;color:#b26a00}',
				'.bc-login-aviso[hidden],.bc-login-alerta[hidden]{display:none}',
				'.bc-login-ocupado{pointer-events:none;opacity:.85}',
			].join('\n');
			document.head.appendChild(estilos);
		}
	}
})();
