// Comportamiento de la pantalla articulos.
// El buscador, los filtros y la paginación los maneja initListado() (app.js) y los steppers
// funcionan por delegación en document, así que las filas cargadas por fetch no requieren
// re-inicialización. Aquí solo se atiende el foco: al volver a renderizar la tabla tras
// paginar, el foco no debe perderse en un botón que ya no existe.
document.addEventListener('listado:render', ({ detail }) => {
    const root = detail?.root;
    if (!root || !root.matches('[aria-label="Catálogo de artículos"]')) return;
    const activo = document.activeElement;
    if (activo === document.body || !document.contains(activo)) {
        root.querySelector('.pager-btn.on')?.focus({ preventScroll: true });
    }
});
