(function () {
	'use strict';
	if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }

	document.querySelectorAll('.barra-progreso > span').forEach(function (barra) {
		var ancho = barra.style.width;
		barra.style.width = '0';
		// Dos fotogramas para que el navegador pinte el 0 antes de crecer.
		requestAnimationFrame(function () {
			requestAnimationFrame(function () { barra.style.width = ancho; });
		});
	});
})();
