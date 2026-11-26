/*
 * Cotizador — interacción del lado del cliente (sin dependencias).
 *
 * Principios: la interfaz responde al instante (optimista) y la red
 * se sincroniza en segundo plano; el servidor siempre tiene la última
 * palabra sobre la existencia disponible.
 */

const $ = (sel, root = document) => root.querySelector(sel);
const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];

const CSRF = $('meta[name="csrf-token"]')?.content;
const CURRENCY = $('meta[name="currency"]')?.content ?? '$';
const money = (n) =>
    CURRENCY + Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const qty = (n) => Number(n).toLocaleString('en-US', { maximumFractionDigits: 2 });

/* ---------- Aviso (HUD) ---------- */
let toastTimer;
function toast(message) {
    const el = $('[data-toast]');
    if (!el || !message) return;
    el.textContent = message;
    el.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => (el.hidden = true), 2600);
}

/* Háptica breve solo en momentos con significado (límite alcanzado, error). */
const haptic = () => navigator.vibrate?.(12);

/* ---------- Borde de desplazamiento de la barra superior ---------- */
function initScrollEdge() {
    const bar = $('[data-scroll-edge]');
    if (!bar) return;
    let ticking = false;
    const update = () => {
        bar.classList.toggle('is-scrolled', window.scrollY > 72); // cuando el título grande ya no se ve
        ticking = false;
    };
    addEventListener(
        'scroll',
        () => {
            if (!ticking) requestAnimationFrame(update), (ticking = true);
        },
        { passive: true },
    );
    update();
}

/* ---------- Búsqueda en vivo ---------- */
function initSearch() {
    const form = $('[data-search]');
    if (!form) return;
    const input = $('input[name="q"]', form);
    const clear = $('[data-clear]', form);
    const results = $('[data-results]');
    let controller;
    let timer;

    async function run() {
        const q = input.value.trim();
        controller?.abort();
        controller = new AbortController();
        const url = new URL(form.dataset.endpoint, location.origin);
        url.searchParams.set('q', q);

        const page = new URL(location.href);
        q ? page.searchParams.set('q', q) : page.searchParams.delete('q');
        history.replaceState(null, '', page);

        results.setAttribute('aria-busy', 'true');
        try {
            const res = await fetch(url, {
                signal: controller.signal,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            // Sin cliente elegido (p. ej. sesión vencida): volver al paso 1.
            if (res.status === 409 && res.headers.get('X-Redirect')) {
                location.href = res.headers.get('X-Redirect');
                return;
            }
            results.innerHTML = await res.text();
        } catch (e) {
            if (e.name !== 'AbortError') toast('No se pudo buscar. Revisa tu conexión.');
        } finally {
            results.setAttribute('aria-busy', 'false');
        }
    }

    input.addEventListener('input', () => {
        clear.hidden = input.value === '';
        clearTimeout(timer);
        // Espera corta: evita una consulta a SQL Server por cada tecla.
        timer = setTimeout(run, 180);
    });
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        clearTimeout(timer);
        run();
        input.blur(); // oculta el teclado en móvil
    });
    clear.addEventListener('pointerdown', (e) => {
        e.preventDefault(); // conserva el foco en el campo
        input.value = '';
        clear.hidden = true;
        run();
    });

    if (input.value.trim()) {
        clear.hidden = false;
        run();
    }
}

/* ---------- Resumen del pedido (badge, píldora, totales) ---------- */
function renderResumen(r) {
    $$('[data-cart-badge]').forEach((b) => {
        b.textContent = r.lineas;
        b.hidden = !r.lineas;
    });
    $$('[data-cart-lineas]').forEach((el) => (el.textContent = r.lineas));
    $$('[data-cart-piezas]').forEach((el) => (el.textContent = qty(r.piezas)));
    $$('[data-cart-total]').forEach((el) => (el.textContent = money(r.total)));
    const pill = $('[data-cart-pill]');
    if (pill) pill.hidden = !r.lineas;
    // Aviso (no bloqueante) si el total supera el crédito disponible del cliente.
    $$('[data-credit-warning]').forEach((el) => (el.hidden = r.total <= Number(el.dataset.disponible)));
}

/* ---------- Control de cantidad ---------- */
const pending = new Map(); // codigo -> timeout programado
let inflight = 0; // peticiones enviadas sin respuesta
const isSyncing = () => pending.size > 0 || inflight > 0;

function paintStepper(st, value) {
    const max = Number(st.dataset.max);
    st.dataset.qty = value;
    const input = $('[data-qty-input]', st);
    if (input) input.value = value;
    const plus = $('[data-step="1"]', st);
    if (plus) plus.disabled = value >= max;
    const add = $('[data-step="add"]', st);
    if (add) {
        st.toggleAttribute('data-empty', value === 0);
        add.disabled = max < 1;
        add.textContent = max < 1 ? 'Agotado' : 'Agregar';
    }
}

function setQty(st, wanted) {
    const max = Number(st.dataset.max);
    let value = Math.max(0, Math.floor(Number(wanted) || 0));
    if (value > max) {
        value = max;
        haptic();
        toast(`Máximo disponible: ${qty(max)}`);
    }
    paintStepper(st, value);
    sync(st, value);
}

// Agrupa toques rápidos: la UI ya cambió; la red recibe solo el valor final.
function sync(st, value) {
    const codigo = st.dataset.codigo;
    clearTimeout(pending.get(codigo));
    pending.set(
        codigo,
        setTimeout(async () => {
            pending.delete(codigo);
            inflight++;
            try {
                const res = await fetch('/pedido/items', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': CSRF,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ codigo, cantidad: value }),
                });
                const data = await res.json();
                if (data.resumen) renderResumen(data.resumen);
                if (res.status === 409) {
                    location.href = '/clientes';
                    return;
                }
                if (!res.ok) {
                    toast(data.message ?? 'No se pudo actualizar el pedido.');
                    if (res.status === 404) removeRow(codigo);
                    return;
                }
                // El servidor manda: existencia actual y cantidad aplicada.
                $$(`[data-stepper][data-codigo="${CSS.escape(codigo)}"]`).forEach((s) => {
                    s.dataset.max = data.maximo;
                    paintStepper(s, data.cantidad);
                });
                if (data.ajustado) {
                    haptic();
                    toast(data.message);
                }
                const row = $(`[data-cart-row="${CSS.escape(codigo)}"]`);
                if (row) {
                    const sub = $('[data-subtotal]', row);
                    if (sub) sub.textContent = money(data.subtotal);
                    if (data.cantidad === 0) removeRow(codigo);
                }
            } catch {
                haptic();
                toast('Sin conexión. El cambio no se guardó.');
            } finally {
                inflight--;
            }
        }, 250),
    );
}

function removeRow(codigo) {
    const row = $(`[data-cart-row="${CSS.escape(codigo)}"]`);
    if (!row) return;
    row.classList.add('row-leaving');
    setTimeout(() => {
        const list = row.parentElement;
        row.remove();
        if (list && !list.children.length) {
            $('[data-cart-content]')?.remove();
            const empty = $('[data-cart-empty]');
            if (empty) empty.hidden = false;
        }
    }, 200);
}

/* ---------- Lote de despacho: panel inferior (bottom sheet) ---------- */
const fecha = (iso) => (iso ? iso.split('-').reverse().join('/') : 's/f'); // 2027-09-01 → 01/09/2027
const escapar = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

function initLotes() {
    const sheet = $('[data-lote-sheet]');
    if (!sheet) return;
    const panel = $('[data-sheet-panel]', sheet);
    const lista = $('[data-sheet-lista]', sheet);
    let trigger = null;
    let cerrando = false;

    function abrir(btn) {
        trigger = btn;
        const lotes = JSON.parse(btn.dataset.lotes);
        const elegido = btn.dataset.seleccionado;
        const unidad = btn.dataset.unidad || '';
        $('[data-sheet-titulo]', sheet).textContent = btn.dataset.descripcion;

        lista.innerHTML = lotes
            .map(
                (l, i) => `
            <li class="border-line border-b last:border-b-0">
                <button type="button" role="option" aria-selected="${l.lote === elegido}" data-lote="${escapar(l.lote)}"
                        class="lote-opcion pressable flex min-h-16 w-full items-center gap-3 px-5 py-3 text-left">
                    <span class="min-w-0 flex-1">
                        <span class="flex flex-wrap items-center gap-2">
                            <span class="num text-ink text-[19px] font-extrabold">${escapar(l.lote)}</span>
                            ${i === 0 ? '<span class="badge bg-cyan text-navy">Sugerido</span>' : ''}
                        </span>
                        <span class="text-label-2 mt-0.5 block text-[14px]">
                            Vence <span class="num text-ink text-[16px] font-bold">${fecha(l.vence)}</span>
                            · <span class="num text-ink text-[16px] font-bold">${qty(l.maximo)}</span> ${escapar(unidad)} disponibles
                        </span>
                    </span>
                    <span class="grid size-7 shrink-0 place-items-center rounded-full ${l.lote === elegido ? 'bg-royal text-white' : 'ring-line ring-2'}" aria-hidden="true">
                        ${l.lote === elegido ? '<svg viewBox="0 0 24 24" class="size-4" fill="currentColor"><path d="M9.55 17.6a1 1 0 0 1-.7-.3l-4.2-4.2a1 1 0 1 1 1.4-1.4l3.5 3.5 8.3-8.3a1 1 0 1 1 1.4 1.4l-9 9a1 1 0 0 1-.7.3Z"/></svg>' : ''}
                    </span>
                </button>
            </li>`,
            )
            .join('');

        cerrando = false;
        panel.style.transform = '';
        sheet.showModal();
        requestAnimationFrame(() => sheet.classList.add('is-open'));
        $('[aria-selected="true"]', lista)?.focus({ preventScroll: true });
    }

    // Sale por el mismo camino por el que entró (hacia abajo), desde donde esté.
    function cerrar() {
        if (!sheet.open || cerrando) return;
        cerrando = true;
        sheet.classList.remove('is-open', 'is-dragging');
        panel.style.transform = '';
        const fin = () => {
            if (cerrando) sheet.close();
            cerrando = false;
            trigger?.focus({ preventScroll: true });
        };
        panel.addEventListener('transitionend', fin, { once: true });
        setTimeout(fin, 450); // por si no hay transición (movimiento reducido)
    }

    async function elegir(lote) {
        const codigo = trigger.dataset.codigo;
        if (lote === trigger.dataset.seleccionado) return cerrar();
        lista.querySelectorAll('button').forEach((b) => (b.disabled = true));
        inflight++;
        try {
            const res = await fetch('/pedido/items/lote', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ codigo, lote }),
            });
            const data = await res.json();
            if (data.resumen) renderResumen(data.resumen);
            if (!res.ok) {
                haptic();
                toast(data.message ?? 'No se pudo cambiar el lote.');
                return;
            }
            // Botón del artículo con el lote nuevo.
            trigger.dataset.seleccionado = data.lote.lote;
            $('[data-lote-texto]', trigger).innerHTML =
                `<span class="num block text-[17px] leading-tight font-bold">${escapar(data.lote.lote)}</span>` +
                `<span class="text-label-2 block">vence <span class="num text-ink text-[16px] font-bold">${fecha(data.lote.vence)}</span>` +
                ` · <span class="num text-ink text-[16px] font-bold">${qty(data.lote.maximo)}</span> ${escapar(trigger.dataset.unidad || '')} disp.</span>`;
            // El tope de la cantidad ahora es la existencia del lote elegido.
            $$(`[data-stepper][data-codigo="${CSS.escape(codigo)}"]`).forEach((s) => {
                s.dataset.max = data.maximo;
                paintStepper(s, data.cantidad);
            });
            const sub = $('[data-subtotal]', $(`[data-cart-row="${CSS.escape(codigo)}"]`));
            if (sub) sub.textContent = money(data.subtotal);
            cerrar();
            if (data.ajustado) {
                haptic();
                toast(data.message);
            }
        } catch {
            toast('Sin conexión. El lote no se cambió.');
        } finally {
            inflight--;
            lista.querySelectorAll('button').forEach((b) => (b.disabled = false));
        }
    }

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-lote-trigger]');
        if (btn) abrir(btn);
    });
    lista.addEventListener('click', (e) => {
        const op = e.target.closest('[data-lote]');
        if (op && !op.disabled) elegir(op.dataset.lote);
    });
    $('[data-sheet-cerrar]', sheet).addEventListener('click', cerrar);
    sheet.addEventListener('click', (e) => e.target === sheet && cerrar()); // tocar fuera
    sheet.addEventListener('cancel', (e) => {
        e.preventDefault(); // Escape: cerrar con animación
        cerrar();
    });

    // Arrastrar hacia abajo para cerrar: sigue al dedo 1:1 y decide por distancia o velocidad.
    let inicio = null;
    sheet.querySelectorAll('[data-sheet-grab]').forEach((zona) => {
        zona.addEventListener('pointerdown', (e) => {
            inicio = { y: e.clientY, t: e.timeStamp, ultY: e.clientY, ultT: e.timeStamp, vel: 0 };
            zona.setPointerCapture(e.pointerId);
            sheet.classList.add('is-dragging');
        });
        zona.addEventListener('pointermove', (e) => {
            if (!inicio) return;
            const dy = e.clientY - inicio.y;
            const dt = e.timeStamp - inicio.ultT || 1;
            inicio.vel = (e.clientY - inicio.ultY) / dt; // px/ms
            inicio.ultY = e.clientY;
            inicio.ultT = e.timeStamp;
            // Hacia arriba resiste (borde suave); hacia abajo sigue al dedo.
            panel.style.transform = `translateY(${dy > 0 ? dy : dy / 6}px)`;
        });
        const soltar = (e) => {
            if (!inicio) return;
            const dy = e.clientY - inicio.y;
            const vel = inicio.vel;
            inicio = null;
            sheet.classList.remove('is-dragging');
            if (dy > 120 || vel > 0.5) cerrar();
            else panel.style.transform = ''; // vuelve a su lugar desde donde quedó
        };
        zona.addEventListener('pointerup', soltar);
        zona.addEventListener('pointercancel', soltar);
    });
}

function initSteppers() {
    // La respuesta visual ocurre al tocar (:active en CSS); el cambio se confirma al soltar.
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-step]');
        if (!btn || btn.disabled) return;
        const st = btn.closest('[data-stepper]');
        const current = Number(st.dataset.qty);
        const step = btn.dataset.step;
        setQty(st, step === 'add' ? 1 : current + Number(step));
    });

    const commitInput = (input) => {
        const st = input.closest('[data-stepper]');
        if (Number(input.value) !== Number(st.dataset.qty)) setQty(st, input.value);
    };
    document.addEventListener('change', (e) => {
        if (e.target.matches('[data-qty-input]')) commitInput(e.target);
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target.matches('[data-qty-input]')) {
            e.preventDefault();
            commitInput(e.target);
            e.target.blur();
        }
    });
    document.addEventListener('focusin', (e) => {
        if (e.target.matches('[data-qty-input]')) e.target.select();
    });
}

/* Evita doble envío al confirmar y espera a que se guarden los cambios pendientes. */
function initConfirmForm() {
    const form = $('[data-confirm-form]');
    if (!form) return;
    form.addEventListener('submit', (e) => {
        const btn = $('button', form);
        if (isSyncing()) {
            e.preventDefault();
            btn.disabled = true;
            const wait = setInterval(() => {
                if (!isSyncing()) clearInterval(wait), form.submit();
            }, 100);
            return;
        }
        btn.disabled = true;
    });
}

initScrollEdge();
initSearch();
/* ---------- Envío a Softland con confirmación (SweetAlert2) ---------- */
// Se carga bajo demanda: solo en la pantalla del pedido, no en cada página.
async function swal() {
    const [{ default: Swal }] = await Promise.all([import('sweetalert2'), import('sweetalert2/dist/sweetalert2.min.css')]);
    return Swal.mixin({
        buttonsStyling: false,
        reverseButtons: true,
        iconColor: '#29b6e8', // cian de la marca
        customClass: {
            popup: 'swal-unimark',
            confirmButton: 'swal-confirmar',
            cancelButton: 'swal-cancelar',
            actions: 'swal-acciones',
        },
    });
}

function initSoftland() {
    const form = $('[data-softland-form]');
    if (form) {
        form.addEventListener('submit', async (e) => {
            if (form.dataset.confirmado) return; // ya confirmado: enviar
            e.preventDefault();
            const d = form.dataset;
            const Swal = await swal();
            const r = await Swal.fire({
                icon: 'question',
                title: '¿Enviar a Softland?',
                html: `Se creará el pedido <b>${escapar(d.folio)}</b> en Softland para<br><b>${escapar(d.cliente)}</b><br>
                       <span class="swal-detalle">${escapar(d.lineas)} artículo(s) · ${escapar(d.total)}<br>
                       Consecutivo ${escapar(d.consecutivo)} · usuario ${escapar(d.usuario)}</span>`,
                showCancelButton: true,
                confirmButtonText: 'Sí, enviar',
                cancelButtonText: 'Cancelar',
                focusCancel: true,
            });
            if (!r.isConfirmed) return;
            form.dataset.confirmado = '1';
            Swal.fire({
                title: 'Enviando a Softland…',
                text: 'No cierres esta página.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => Swal.showLoading(),
            });
            form.submit();
        });
    }

    // Resultado del envío (viene de la redirección).
    const res = window.__softland;
    if (res) {
        swal().then((Swal) =>
            res.ok
                ? Swal.fire({ icon: 'success', title: 'Pedido creado en Softland', html: `Número <b class="swal-numero">${escapar(res.ok)}</b>` })
                : Swal.fire({ icon: 'error', title: 'No se pudo enviar', text: res.error }),
        );
    }
}

initSteppers();
initLotes();
initConfirmForm();
initSoftland();
if (window.__aviso) toast(window.__aviso);
