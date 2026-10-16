document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('form[data-confirmar]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      const mensaje = form.dataset.confirmar || '¿Deseas continuar?';
      if (!window.confirm(mensaje)) event.preventDefault();
    });
  });
});
