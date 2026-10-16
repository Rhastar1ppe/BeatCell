document.addEventListener('DOMContentLoaded', () => {
  const filtro = document.querySelector('[data-filtro-historial]');
  const filas = [...document.querySelectorAll('[data-fila-historial]')];
  if (!filtro || !filas.length) return;
  filtro.addEventListener('input', () => {
    const texto = filtro.value.trim().toLocaleLowerCase();
    filas.forEach((fila) => {
      fila.hidden = !fila.textContent.toLocaleLowerCase().includes(texto);
    });
  });
});
