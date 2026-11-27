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

// Editor minimalista de observaciones (texto plano: Softland las guarda como texto simple).
function initObservaciones() {
    const editor = document.querySelector('[data-obs-editor]');
    if (!editor) return;
    const area = editor.querySelector('textarea');
    const contador = editor.querySelector('[data-obs-contador]');
    const max = Number(area.maxLength) || 2000;

    const actualizar = () => {
        contador.textContent = `${area.value.length.toLocaleString('en-US')} / ${max.toLocaleString('en-US')}`;
        contador.classList.toggle('is-limite', area.value.length >= max * 0.9);
        // Crece con el texto (sin barra de desplazamiento) hasta un máximo razonable.
        area.style.height = 'auto';
        area.style.height = `${Math.min(area.scrollHeight + 2, 320)}px`;
    };
    // Cambia el texto como si se hubiera escrito (deja deshacer con Ctrl+Z).
    const reemplazar = (inicio, fin, texto, selInicio, selFin) => {
        area.focus();
        area.setSelectionRange(inicio, fin);
        if (!document.execCommand?.('insertText', false, texto)) area.setRangeText(texto, inicio, fin, 'end');
        area.setSelectionRange(selInicio ?? inicio + texto.length, selFin ?? inicio + texto.length);
        actualizar();
    };

    editor.querySelector('.obs-barra').addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-obs]');
        if (!btn) return;
        const { selectionStart: a, selectionEnd: b, value: v } = area;

        if (btn.dataset.obs === 'vineta') {
            // Viñeta al inicio de cada línea seleccionada (o de la línea actual); otra vez la quita.
            const ini = v.lastIndexOf('\n', a - 1) + 1;
            const finLinea = v.indexOf('\n', b);
            const fin = finLinea === -1 ? v.length : finLinea;
            const lineas = v.slice(ini, fin).split('\n');
            const todas = lineas.every((l) => l.startsWith('• '));
            const nuevo = lineas.map((l) => (todas ? l.slice(2) : l.startsWith('• ') ? l : `• ${l}`)).join('\n');
            reemplazar(ini, fin, nuevo, ini + nuevo.length, ini + nuevo.length);
        } else if (btn.dataset.obs === 'mayusculas') {
            const [i, f] = a === b ? [0, v.length] : [a, b];
            reemplazar(i, f, v.slice(i, f).toLocaleUpperCase('es'), i, i + (f - i));
        } else if (btn.dataset.obs === 'limpiar' && v.trim() !== '') {
            const [{ default: Swal }] = await Promise.all([import('sweetalert2'), import('sweetalert2/dist/sweetalert2.min.css')]);
            const r = await Swal.fire({
                icon: 'warning',
                iconColor: '#a34d00',
                title: '¿Borrar las observaciones?',
                showCancelButton: true,
                confirmButtonText: 'Sí, borrar',
                cancelButtonText: 'Cancelar',
                focusCancel: true,
                reverseButtons: true,
                buttonsStyling: false,
                customClass: { popup: 'swal-unimark', confirmButton: 'swal-confirmar swal-peligro', cancelButton: 'swal-cancelar', actions: 'swal-acciones' },
            });
            if (r.isConfirmed) reemplazar(0, v.length, '');
        }
    });

    area.addEventListener('input', actualizar);
    actualizar();
}

initObservaciones();
