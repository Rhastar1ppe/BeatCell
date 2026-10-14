(() => {
	'use strict';

	const input = document.querySelector('#buscar-contenido-curso');
	const estado = document.querySelector('#estado-busqueda-curso');
	const mensajeVacio = document.querySelector('.aviso-sin-resultados');
	const modulos = [...document.querySelectorAll('[data-course-module]')];

	if (!(input instanceof HTMLInputElement) || !estado || !mensajeVacio || modulos.length === 0) {
		return;
	}

	const normalizar = (texto) => texto
		.toLocaleLowerCase('es')
		.normalize('NFD')
		.replace(/[\u0300-\u036f]/g, '')
		.trim();

	const coincide = (elementos, consulta) => elementos.some((elemento) =>
		elemento && normalizar(elemento.textContent || '').includes(consulta)
	);

	const limpiarAperturasDeBusqueda = () => {
		document.querySelectorAll('[data-search-opened="true"]').forEach((elemento) => {
			elemento.open = false;
			elemento.removeAttribute('data-search-opened');
		});
	};

	const abrirParaBusqueda = (elemento) => {
		if (!elemento.open) {
			elemento.open = true;
			elemento.dataset.searchOpened = 'true';
		}
	};

	const actualizar = () => {
		limpiarAperturasDeBusqueda();
		const consulta = normalizar(input.value);
		let coincidencias = 0;
		let modulosVisibles = 0;

		modulos.forEach((modulo) => {
			const tituloModulo = modulo.querySelector('.modulo-titulo');
			const descripcionModulo = modulo.querySelector(':scope > .modulo-contenido > .texto');
			const coincideModulo = consulta !== ''
				&& coincide([tituloModulo, descripcionModulo], consulta);
			let moduloTieneCoincidencia = coincideModulo;

			modulo.querySelectorAll('[data-course-topic]').forEach((tema) => {
				const tituloTema = tema.querySelector('.tema-titulo');
				const contenidoTema = tema.querySelector('.tema-contenido');
				const textosTema = contenidoTema
					? [...contenidoTema.children].filter((elemento) => elemento.matches('.texto'))
					: [];
				const coincideTema = consulta !== '' && coincide([tituloTema, ...textosTema], consulta);
				const actividades = [...tema.querySelectorAll('[data-course-activity]')];
				const actividadesCoincidentes = actividades.filter((actividad) =>
					consulta !== '' && normalizar(actividad.textContent || '').includes(consulta)
				);
				const temaVisible = consulta === '' || coincideModulo || coincideTema || actividadesCoincidentes.length > 0;

				tema.hidden = !temaVisible;
				if (consulta !== '' && temaVisible) {
					abrirParaBusqueda(modulo);
					if (coincideTema || actividadesCoincidentes.length > 0) {
						abrirParaBusqueda(tema);
					}
				}

				actividades.forEach((actividad) => {
					const visible = consulta === '' || coincideModulo || actividadesCoincidentes.includes(actividad);
					actividad.hidden = !visible;
				});

				if (coincideTema) {
					coincidencias++;
					moduloTieneCoincidencia = true;
				}
				coincidencias += actividadesCoincidentes.length;
			});

			modulo.hidden = consulta !== '' && !moduloTieneCoincidencia;
			if (coincideModulo) {
				coincidencias++;
				abrirParaBusqueda(modulo);
			}
			if (!modulo.hidden) {
				modulosVisibles++;
			}
		});

		mensajeVacio.hidden = consulta === '' || modulosVisibles > 0;
		if (consulta === '') {
			estado.textContent = 'Escribe para filtrar el contenido de este curso.';
		} else if (coincidencias === 0) {
			estado.textContent = 'No se encontraron coincidencias.';
		} else {
			estado.textContent = `${coincidencias} ${coincidencias === 1 ? 'coincidencia encontrada' : 'coincidencias encontradas'}.`;
		}
	};

	input.addEventListener('input', actualizar);
})();