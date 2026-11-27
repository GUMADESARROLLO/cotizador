// Comportamiento de Pedido, Historial y detalle. El resto (steppers, hojas, calendario,
// consecutivo, envío a Softland) vive en app.js y se engancha por data-* / delegación.

// Filas clicables: Enter/Espacio abre lo mismo que el clic (editar línea del pedido).
document.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter' && e.key !== ' ') return;
    const fila = e.target.closest?.('tr[data-editar-articulo]');
    if (fila && e.target === fila) {
        e.preventDefault();
        fila.click();
    }
});

// Historial: clic o Enter en la fila abre el pedido (el enlace del número sigue funcionando sin JS).
function initHistorial() {
    const tabla = document.querySelector('[data-historial]');
    if (!tabla) return;
    const ir = (fila) => (location.href = fila.dataset.href);
    tabla.addEventListener('click', (e) => {
        const fila = e.target.closest('tr[data-href]');
        if (!fila || e.target.closest('a, button')) return;
        ir(fila);
    });
    tabla.addEventListener('keydown', (e) => {
        const fila = e.target.closest('tr[data-href]');
        if (fila && e.target === fila && e.key === 'Enter') ir(fila);
    });
}

initHistorial();
