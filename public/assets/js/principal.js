document.addEventListener('submit', function (event) {
    const formulario = event.target;

    if (!(formulario instanceof HTMLFormElement)) {
        return;
    }

    const esCierreSesion = formulario.querySelector('input[name="csrf_logout"]');
    if (!esCierreSesion) {
        return;
    }

    if (!window.confirm('¿Deseas cerrar sesión?')) {
        event.preventDefault();
    }
});
