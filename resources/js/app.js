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
initSteppers();
initConfirmForm();
if (window.__aviso) toast(window.__aviso);
