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

// Adjuntos de un pedido ingresado en Softland: modal con zona para soltar archivos y tabla.
function initAdjuntos() {
    const dlg = document.querySelector('[data-adjuntos-dlg]');
    const abrir = document.querySelector('[data-adjuntos-abrir]');
    if (!dlg || !abrir) return;

    const $ = (sel) => dlg.querySelector(sel);
    const drop = $('[data-adjuntos-drop]');
    const input = $('[data-adjuntos-input]');
    const filas = $('[data-adjuntos-filas]');
    const buscar = $('[data-adjuntos-buscar]');
    const progreso = $('[data-adjuntos-progreso]');
    const barra = $('[data-adjuntos-barra]');
    const estado = $('[data-adjuntos-estado]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const maxBytes = Number(dlg.dataset.maxKb) * 1024;
    const extensiones = dlg.dataset.extensiones.split(',');
    let datos = [];
    let subiendo = false;

    const esc = (t) => String(t ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const peso = (b) => (b >= 1048576 ? `${(b / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(b / 1024))} KB`);
    const swal = async () => {
        const [{ default: Swal }] = await Promise.all([import('sweetalert2'), import('sweetalert2/dist/sweetalert2.min.css')]);
        return Swal.mixin({
            buttonsStyling: false,
            reverseButtons: true,
            target: dlg, // dentro del <dialog> (si no, queda detrás del modal)
            customClass: { popup: 'swal-unimark', confirmButton: 'swal-confirmar', cancelButton: 'swal-cancelar', actions: 'swal-acciones' },
        });
    };
    const aviso = async (titulo, icono = 'error') => {
        const Swal = await swal();
        Swal.fire({ toast: true, position: 'top-end', icon: icono, title: titulo, showConfirmButton: false, timer: icono === 'error' ? 4500 : 2500, timerProgressBar: true, customClass: { popup: 'swal-toast' } });
    };

    const pintar = () => {
        const q = buscar.value.trim().toLowerCase();
        const lista = datos.filter((a) => !q || a.nombre.toLowerCase().includes(q));
        filas.innerHTML = lista
            .map(
                (a) => `<tr>
                    <td data-v="${esc(a.nombre)}"><a class="adj-nombre" href="${esc(a.descargar)}" title="Descargar">${esc(a.nombre)}</a></td>
                    <td><span class="tag tag-neutral">${esc(a.extension || '—')}</span></td>
                    <td class="td-num" data-v="${a.tamano}">${peso(a.tamano)}</td>
                    <td data-v="${esc(a.fecha_orden)}">${esc(a.fecha)}</td>
                    <td class="adj-acciones">
                        <a class="icon-btn" href="${esc(a.descargar)}" aria-label="Descargar ${esc(a.nombre)}" title="Descargar">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v12M7 11l5 5 5-5M4 20h16"/></svg>
                        </a>
                        <button type="button" class="icon-btn adj-borrar" data-eliminar="${esc(a.eliminar)}" data-nombre="${esc(a.nombre)}" aria-label="Eliminar ${esc(a.nombre)}" title="Eliminar">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg>
                        </button>
                    </td>
                </tr>`,
            )
            .join('');
        $('[data-adjuntos-total]').textContent = datos.length;
        $('[data-adjuntos-vacio]').hidden = datos.length > 0;
        $('[data-adjuntos-vacio]').textContent = datos.length ? '' : 'Aún no hay documentos adjuntos.';
        if (datos.length && !lista.length) filas.innerHTML = `<tr><td colspan="5" class="adj-sin">Ningún archivo coincide con “${esc(buscar.value)}”.</td></tr>`;
        const n = document.querySelector('[data-adjuntos-n]');
        if (n) {
            n.textContent = datos.length;
            n.hidden = datos.length === 0;
        }
    };

    const cargar = async () => {
        filas.innerHTML = '<tr><td colspan="5" class="adj-sin">Cargando…</td></tr>';
        try {
            const r = await fetch(dlg.dataset.listar, { headers: { Accept: 'application/json' } });
            datos = (await r.json()).adjuntos ?? [];
        } catch {
            aviso('No se pudieron cargar los adjuntos.');
        }
        pintar();
    };

    // Sube con XMLHttpRequest para mostrar el avance.
    const subir = (archivos) => {
        if (subiendo || !archivos.length) return;
        const malos = archivos.filter((f) => !extensiones.includes(f.name.split('.').pop().toLowerCase()) || f.size > maxBytes);
        if (malos.length) {
            aviso(`${malos.length === 1 ? `"${malos[0].name}"` : `${malos.length} archivos`}: tipo no permitido o pasa de ${Math.round(maxBytes / 1048576)} MB.`, 'warning');
            archivos = archivos.filter((f) => !malos.includes(f));
            if (!archivos.length) return;
        }
        const fd = new FormData();
        archivos.forEach((f) => fd.append('archivos[]', f));
        const xhr = new XMLHttpRequest();
        xhr.open('POST', dlg.dataset.subir);
        xhr.setRequestHeader('X-CSRF-TOKEN', csrf);
        xhr.setRequestHeader('Accept', 'application/json');
        subiendo = true;
        drop.classList.add('is-ocupado');
        progreso.hidden = false;
        barra.style.width = '0%';
        estado.textContent = `Subiendo ${archivos.length} archivo(s)…`;
        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) barra.style.width = `${Math.round((e.loaded / e.total) * 100)}%`;
        };
        xhr.onload = () => {
            let r = {};
            try {
                r = JSON.parse(xhr.responseText);
            } catch {}
            if (r.adjuntos) datos = r.adjuntos;
            pintar();
            if (xhr.status >= 200 && xhr.status < 300) aviso(r.message ?? 'Listo.', 'success');
            else aviso(r.errors ? Object.values(r.errors)[0][0] : (r.message ?? 'No se pudo subir.'));
        };
        xhr.onerror = () => aviso('Sin conexión. No se subieron los archivos.');
        xhr.onloadend = () => {
            subiendo = false;
            drop.classList.remove('is-ocupado');
            progreso.hidden = true;
            input.value = '';
        };
        xhr.send(fd);
    };

    abrir.addEventListener('click', () => {
        dlg.showModal();
        cargar();
    });
    $('[data-adjuntos-cerrar]').addEventListener('click', () => dlg.close());
    dlg.addEventListener('click', (e) => e.target === dlg && !subiendo && dlg.close()); // clic fuera
    input.addEventListener('change', () => subir([...input.files]));
    buscar.addEventListener('input', pintar);

    ['dragenter', 'dragover'].forEach((t) =>
        drop.addEventListener(t, (e) => {
            e.preventDefault();
            drop.classList.add('is-encima');
        }),
    );
    ['dragleave', 'drop'].forEach((t) =>
        drop.addEventListener(t, (e) => {
            e.preventDefault();
            drop.classList.remove('is-encima');
        }),
    );
    drop.addEventListener('drop', (e) => subir([...(e.dataTransfer?.files ?? [])]));
    // Soltar fuera de la zona no debe abrir el archivo en el navegador.
    dlg.addEventListener('dragover', (e) => e.preventDefault());
    dlg.addEventListener('drop', (e) => e.preventDefault());

    filas.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-eliminar]');
        if (!btn) return;
        const Swal = await swal();
        const r = await Swal.fire({
            icon: 'warning',
            iconColor: '#a34d00',
            title: '¿Eliminar el adjunto?',
            text: btn.dataset.nombre,
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            focusCancel: true,
            customClass: { popup: 'swal-unimark', confirmButton: 'swal-confirmar swal-peligro', cancelButton: 'swal-cancelar', actions: 'swal-acciones' },
        });
        if (!r.isConfirmed) return;
        try {
            const res = await fetch(btn.dataset.eliminar, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
            const d = await res.json();
            if (d.adjuntos) datos = d.adjuntos;
            pintar();
            aviso(d.message ?? 'Eliminado.', res.ok ? 'success' : 'error');
        } catch {
            aviso('Sin conexión. No se eliminó.');
        }
    });
}

initAdjuntos();
