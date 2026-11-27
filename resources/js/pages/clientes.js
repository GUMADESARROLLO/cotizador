// Pantalla clientes: click en cualquier parte de la fila = elegir ese cliente.
// El control real de cada fila es un <button form="form-elegir-cliente"> (teclado nativo: Tab, Enter, Espacio);
// aquí solo se delega el click del ratón sobre el resto de la fila. Funciona con filas cargadas por fetch.
document.addEventListener('click', (e) => {
    const fila = e.target.closest?.('.tbl-clientes tbody tr');
    if (!fila || e.target.closest('button, a, input, select, textarea')) return;
    if (getSelection()?.toString()) return; // no interrumpir si está copiando texto
    fila.querySelector('.cli-pick')?.click();
});
