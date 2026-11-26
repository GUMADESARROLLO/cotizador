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
const qty = (n) => Number(n).toLocaleString('en-US', { maximumFractionDigits: 4 });
// Cajas con fracción (hasta 4 decimales, hacia abajo) y unidades enteras.
const bajar4 = (n) => Math.floor(Math.round(n * 1e6) / 100) / 1e4;
const unidadesDe = (cajas, factor) => Math.floor(Math.round(cajas * factor * 1e6) / 1e6);
const numero = (v) => Number(String(v ?? '').replace(/,/g, '').trim());

async function postJson(url, body) {
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': CSRF,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(body),
    });
    return { res, data: await res.json() };
}

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
    st.dataset.qty = value;
    const input = $('[data-qty-input]', st);
    if (input) input.value = value;
    // Sin tope: se pueden pedir agotados o más de lo disponible (traslados); el servidor avisa.
    if ($('[data-step="add"]', st)) st.toggleAttribute('data-empty', value === 0);
}

function setQty(st, wanted) {
    // En unidades (viales…) enteras; en cajas se admite fracción.
    const n = numero(wanted) || 0;
    const value = Math.max(0, st.dataset.modo === 'unidad' ? Math.floor(n) : bajar4(n));
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
                const { res, data } = await postJson('/pedido/items', { codigo, cantidad: value, modo: st.dataset.modo });
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
                if (data.ajustado || data.excede) {
                    haptic();
                    toast(data.message);
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

/*
 * Panel inferior (bottom sheet) reutilizable sobre un <dialog>: abre y cierra
 * por el mismo camino, se arrastra hacia abajo para cerrar (1:1 con el dedo,
 * decide por distancia o velocidad), se cierra con Cancelar, tocando fuera o Escape.
 */
function hoja(sheet) {
    const panel = $('[data-sheet-panel]', sheet);
    let trigger = null;
    let cerrando = false;

    function abrir(desde) {
        trigger = desde;
        cerrando = false;
        panel.style.transform = '';
        sheet.showModal();
        requestAnimationFrame(() => sheet.classList.add('is-open'));
    }

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

    $('[data-sheet-cerrar]', sheet)?.addEventListener('click', cerrar);
    sheet.addEventListener('click', (e) => e.target === sheet && cerrar()); // tocar fuera
    sheet.addEventListener('cancel', (e) => {
        e.preventDefault(); // Escape: cerrar con animación
        cerrar();
    });

    let inicio = null;
    sheet.querySelectorAll('[data-sheet-grab]').forEach((zona) => {
        zona.addEventListener('pointerdown', (e) => {
            inicio = { y: e.clientY, ultY: e.clientY, ultT: e.timeStamp, vel: 0 };
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

    return { abrir, cerrar };
}

/* ---------- Fecha del pedido: calendario en panel inferior ---------- */
const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
const DIAS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
// Fechas locales sin hora (evita desfases de zona horaria con new Date('YYYY-MM-DD')).
const aFecha = (iso) => {
    const [y, m, d] = iso.split('-').map(Number);
    return new Date(y, m - 1, d);
};
const aIso = (f) => `${f.getFullYear()}-${String(f.getMonth() + 1).padStart(2, '0')}-${String(f.getDate()).padStart(2, '0')}`;
const fechaLarga = (f) => `${DIAS[f.getDay()]} ${f.getDate()} de ${MESES[f.getMonth()]} de ${f.getFullYear()}`;
const sumarDias = (f, n) => new Date(f.getFullYear(), f.getMonth(), f.getDate() + n);

/*
 * Calendario reutilizable en panel inferior (componente x-calendario).
 * opciones: min/max (Date o null = sin límite), hoy, rapidos(hoy) → [[texto, Date]],
 * alElegir(iso) → Promise<boolean> (true cierra el panel), alQuitar() opcional.
 */
function crearCalendario(sheet, { min = null, max = null, hoy, rapidos = () => [], alElegir, alQuitar = null }) {
    const h = hoja(sheet);
    const grid = $('[data-cal-grid]', sheet);
    let elegida = null;
    let vista = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
    let lim = { min, max };

    const enRango = (f) => (!lim.min || f >= lim.min) && (!lim.max || f <= lim.max);
    const mes = (f) => new Date(f.getFullYear(), f.getMonth(), 1);

    function dibujar() {
        $('[data-cal-elegida]', sheet).textContent = elegida ? fechaLarga(elegida) : 'Elige un día';
        $('[data-cal-titulo]', sheet).textContent = `${MESES[vista.getMonth()]} ${vista.getFullYear()}`;
        $('[data-cal-mes="-1"]', sheet).disabled = !!lim.min && vista <= mes(lim.min);
        $('[data-cal-mes="1"]', sheet).disabled = !!lim.max && vista >= mes(lim.max);

        // Semana de lunes a domingo.
        const encabezado = ['L', 'M', 'M', 'J', 'V', 'S', 'D']
            .map((d) => `<span class="text-label-2 pb-1 text-[12px] font-bold" aria-hidden="true">${d}</span>`)
            .join('');
        const huecos = (vista.getDay() + 6) % 7;
        const diasMes = new Date(vista.getFullYear(), vista.getMonth() + 1, 0).getDate();
        let celdas = '<span></span>'.repeat(huecos);
        for (let d = 1; d <= diasMes; d++) {
            const f = new Date(vista.getFullYear(), vista.getMonth(), d);
            const iso = aIso(f);
            const sel = elegida && iso === aIso(elegida);
            const esHoy = iso === aIso(hoy);
            celdas += `<button type="button" data-dia="${iso}" ${enRango(f) ? '' : 'disabled'} aria-pressed="${!!sel}"
                aria-label="${fechaLarga(f)}${esHoy ? ', hoy' : ''}"
                class="cal-dia pressable mx-auto grid size-11 place-items-center rounded-full text-[17px] font-semibold
                       ${!enRango(f) ? 'text-label-3 font-normal' : sel ? 'bg-royal text-white' : esHoy ? 'text-royal ring-cyan ring-2' : 'text-ink'}">${d}</button>`;
        }
        grid.innerHTML = encabezado + celdas;

        $('[data-cal-rapidos]', sheet).innerHTML = rapidos(hoy)
            .filter(([, f]) => enRango(f))
            .map(([t, f]) => {
                const sel = elegida && aIso(f) === aIso(elegida);
                return `<button type="button" data-dia="${aIso(f)}" aria-pressed="${!!sel}"
                    class="pressable rounded-full px-4 py-2 text-[14px] font-bold ${sel ? 'bg-royal text-white' : 'bg-mist text-royal'}">${t}</button>`;
            })
            .join('');
    }

    sheet.querySelectorAll('[data-cal-mes]').forEach((b) =>
        b.addEventListener('click', () => {
            vista = new Date(vista.getFullYear(), vista.getMonth() + Number(b.dataset.calMes), 1);
            dibujar();
        }),
    );
    sheet.addEventListener('click', async (e) => {
        const dia = e.target.closest('[data-dia]');
        if (!dia || dia.disabled) return;
        const iso = dia.dataset.dia;
        sheet.querySelectorAll('[data-dia]').forEach((b) => (b.disabled = true));
        const ok = await alElegir(iso);
        if (ok) {
            elegida = aFecha(iso);
            h.cerrar();
        }
        dibujar();
    });
    $('[data-cal-quitar]', sheet)?.addEventListener('click', () => {
        alQuitar?.();
        h.cerrar();
    });

    return {
        /** Abre el panel con la fecha actual (iso o vacío) y, si se indica, otros límites. */
        abrir(trigger, iso, limites = {}) {
            lim = { min, max, ...limites };
            elegida = iso ? aFecha(iso) : null;
            vista = mes(elegida ?? (lim.max && hoy > lim.max ? lim.max : hoy));
            if (limites.titulo) $('[data-cal-eyebrow]', sheet).textContent = limites.titulo;
            dibujar();
            h.abrir(trigger);
            $('[aria-pressed="true"]', grid)?.focus({ preventScroll: true });
        },
    };
}

/* Fecha del pedido (pantalla Pedido): se guarda en el servidor al elegirla. */
function initFecha() {
    const sheet = $('[data-fecha-sheet]');
    const trigger = $('[data-fecha-trigger]');
    if (!sheet || !trigger) return;
    const hoy = aFecha(trigger.dataset.hoy);

    const cal = crearCalendario(sheet, {
        min: aFecha(trigger.dataset.min),
        max: aFecha(trigger.dataset.max),
        hoy,
        // Accesos rápidos: ingresar uno de ayer, o armarlo hoy con fecha del lunes.
        rapidos: (h) => {
            const proxLunes = sumarDias(h, ((8 - h.getDay()) % 7) || 7);
            return [['Ayer', sumarDias(h, -1)], ['Hoy', h], ['Mañana', sumarDias(h, 1)], [`Próximo lunes ${proxLunes.getDate()}`, proxLunes]];
        },
        alElegir: async (iso) => {
            try {
                const { res, data } = await postJson('/pedido/fecha', { fecha: iso });
                if (!res.ok) {
                    haptic();
                    toast(data.errors?.fecha?.[0] ?? data.message ?? 'No se pudo cambiar la fecha.');
                    return false;
                }
                trigger.dataset.fecha = data.fecha;
                $('[data-fecha-texto]', trigger).textContent = data.corta;
                return true;
            } catch {
                toast('Sin conexión. La fecha no se cambió.');
                return false;
            }
        },
    });

    trigger.addEventListener('click', () => cal.abrir(trigger, trigger.dataset.fecha));
}

/* Historial: filtros Desde / Hasta (fecha de creación). Al elegir, se filtra. */
function initFiltroFechas() {
    const sheet = $('[data-filtro-sheet]');
    const form = $('[data-filtro-form]');
    if (!sheet || !form) return;
    const hoy = aFecha(form.dataset.hoy);
    const input = (campo) => $(`input[name="${campo}"]`, form);
    let campo = 'desde';

    const cal = crearCalendario(sheet, {
        max: hoy,
        hoy,
        rapidos: (h) =>
            campo === 'desde'
                ? [['Hoy', h], ['Hace 7 días', sumarDias(h, -7)], ['Inicio de mes', new Date(h.getFullYear(), h.getMonth(), 1)]]
                : [['Hoy', h], ['Ayer', sumarDias(h, -1)]],
        alElegir: async (iso) => {
            input(campo).value = iso;
            form.requestSubmit();
            return true;
        },
        alQuitar: () => {
            input(campo).value = '';
            form.requestSubmit();
        },
    });

    form.addEventListener('click', (e) => {
        const b = e.target.closest('[data-filtro-fecha]');
        if (!b) return;
        campo = b.dataset.filtroFecha;
        // Desde no puede pasar de Hasta, ni Hasta ser antes de Desde.
        const otro = input(campo === 'desde' ? 'hasta' : 'desde').value;
        const limites = campo === 'desde' ? { max: otro ? aFecha(otro) : hoy } : { min: otro ? aFecha(otro) : null };
        cal.abrir(b, input(campo).value, { ...limites, titulo: campo === 'desde' ? 'Creados desde' : 'Creados hasta' });
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

/*
 * Editar un artículo del pedido (panel inferior): cantidad, precio y lote en
 * un solo lugar. Cajas y unidades están enlazadas (se escribe en cualquiera:
 * 2,800 TBL ⇄ 100 CJA), igual el precio (C$32.93 / TBL ⇄ C$922.04 / CJA).
 * Al guardar, el servidor valida todo y devuelve la fila ya dibujada.
 */
const vence = (iso) => (iso ? iso.split('-').reverse().join('/') : 's/f'); // 2027-09-01 → 01/09/2027
const escapar = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const r4 = (n) => Math.round(n * 1e4) / 1e4;
const textoNum = (n) => (Number.isFinite(n) && n > 0 ? String(r4(n)) : '');

function initEditor() {
    const sheet = $('[data-articulo-sheet]');
    if (!sheet) return;
    const h = hoja(sheet);
    const form = $('[data-articulo-form]', sheet);
    const el = (k) => $(`[data-ed-${k}]`, sheet);
    const inCajas = el('cajas');
    const inUnidades = el('unidades');
    const inPCaja = el('precio-caja');
    const inPUnidad = el('precio-unidad');
    const listaLotes = el('lotes');

    let a = null; // datos del artículo
    let fila = null;
    let modo = 'caja'; // en qué campo se escribió la cantidad
    let por = 'caja'; // en qué campo se escribió el precio
    let precioTocado = false;
    let usarNivel = false;
    let lote = null;

    const factor = () => a.factor || 1;
    const empaque = () => factor() > 1;
    const disponible = () => (lote ? a.lotes.find((l) => l.lote === lote)?.disponible ?? 0 : a.existencia);
    const cajas = () => (modo === 'unidad' ? Math.floor(numero(inUnidades.value) || 0) / factor() : numero(inCajas.value) || 0);
    const precioCaja = () => (por === 'unidad' ? (numero(inPUnidad.value) || 0) * factor() : numero(inPCaja.value) || 0);

    function pintar() {
        // Disponible del lote elegido (o de la bodega) y aviso si se pasa.
        const disp = disponible();
        const excede = cajas() > disp + 1e-6;
        // Se permite (se completa con traslados): solo se avisa, sin bloquear.
        const dispTxt = `${qty(bajar4(disp))} ${a.unidad}` + (empaque() ? ` · ${qty(unidadesDe(disp, factor()))} ${a.unidad_empaque}` : '');
        el('disponible').textContent = excede
            ? (disp > 0 ? `Excede lo disponible (${dispTxt}); se pedirá igual.` : `Sin existencia en bodega; se pedirá igual.`)
            : 'Disponible: ' + dispTxt;
        el('disponible').classList.toggle('text-warn', excede);
        el('disponible').classList.toggle('font-semibold', excede);

        const nivel = a.precio_nivel;
        el('nivel-texto').textContent = nivel > 0 ? `Nivel del cliente: ${money(nivel)} / ${a.unidad}` : 'Sin precio en el nivel del cliente.';
        const manual = !usarNivel && (precioTocado ? Math.abs(precioCaja() - nivel) > 0.00005 : a.manual);
        el('usar-nivel').hidden = !(nivel > 0 && manual);

        el('subtotal').textContent = money(cajas() * precioCaja());
    }

    function pintarLotes() {
        el('lotes-grupo').hidden = !a.lotes.length;
        listaLotes.innerHTML = a.lotes
            .map((l, i) => {
                const sel = l.lote === lote;
                const disp = `${qty(bajar4(l.disponible))} ${escapar(a.unidad)}` + (empaque() ? ` · ${qty(unidadesDe(l.disponible, factor()))} ${escapar(a.unidad_empaque)}` : '');
                return `<li class="border-line border-b last:border-b-0">
                    <button type="button" role="radio" aria-checked="${sel}" data-ed-lote="${escapar(l.lote)}"
                            class="lote-opcion pressable flex min-h-14 w-full items-center gap-3 px-4 py-2.5 text-left">
                        <span class="min-w-0 flex-1">
                            <span class="flex flex-wrap items-center gap-2">
                                <span class="num text-ink text-[18px] font-extrabold">${escapar(l.lote)}</span>
                                ${i === 0 ? '<span class="badge bg-cyan text-navy">Sugerido</span>' : ''}
                            </span>
                            <span class="text-label-2 block text-[13px]">Vence <b class="num text-ink text-[15px]">${vence(l.vence)}</b> · <span class="num text-[15px] font-bold">${disp}</span></span>
                        </span>
                        <span class="grid size-6 shrink-0 place-items-center rounded-full ${sel ? 'bg-royal text-white' : 'ring-line ring-2'}" aria-hidden="true">
                            ${sel ? '<svg viewBox="0 0 24 24" class="size-4" fill="currentColor"><path d="M9.55 17.6a1 1 0 0 1-.7-.3l-4.2-4.2a1 1 0 1 1 1.4-1.4l3.5 3.5 8.3-8.3a1 1 0 1 1 1.4 1.4l-9 9a1 1 0 0 1-.7.3Z"/></svg>' : ''}
                        </span>
                    </button>
                </li>`;
            })
            .join('');
    }

    function abrir(btn) {
        fila = btn;
        a = JSON.parse(btn.dataset.articulo);
        modo = empaque() ? a.modo : 'caja';
        por = 'caja';
        precioTocado = false;
        usarNivel = false;
        lote = a.lote;

        el('codigo').textContent = a.codigo;
        el('titulo').textContent = a.descripcion;
        el('u-caja').textContent = a.unidad;
        el('u-interna').textContent = a.unidad_empaque;
        el('p-caja').textContent = `/ ${a.unidad}`;
        el('p-interna').textContent = `/ ${a.unidad_empaque}`;
        $$('[data-ed-solo-empaque]', sheet).forEach((x) => (x.hidden = !empaque()));
        inCajas.value = textoNum(a.cajas);
        inUnidades.value = textoNum(a.unidades);
        inPCaja.value = textoNum(a.precio);
        inPUnidad.value = textoNum(a.precio / factor());
        pintarLotes();
        pintar();
        h.abrir(btn);
    }

    // Cantidad: lo escrito en un campo actualiza el otro.
    inCajas.addEventListener('input', () => {
        modo = 'caja';
        inUnidades.value = textoNum((numero(inCajas.value) || 0) * factor());
        pintar();
    });
    inUnidades.addEventListener('input', () => {
        modo = 'unidad';
        inCajas.value = textoNum(Math.round(((numero(inUnidades.value) || 0) / factor()) * 1e8) / 1e8);
        pintar();
    });
    // Precio: igual, por caja ⇄ por unidad.
    inPCaja.addEventListener('input', () => {
        por = 'caja';
        precioTocado = true;
        usarNivel = false;
        inPUnidad.value = textoNum((numero(inPCaja.value) || 0) / factor());
        pintar();
    });
    inPUnidad.addEventListener('input', () => {
        por = 'unidad';
        precioTocado = true;
        usarNivel = false;
        inPCaja.value = textoNum((numero(inPUnidad.value) || 0) * factor());
        pintar();
    });
    $$('input', form).forEach((i) => i.addEventListener('focus', () => i.select()));

    el('usar-nivel').addEventListener('click', () => {
        usarNivel = true;
        precioTocado = false;
        inPCaja.value = textoNum(a.precio_nivel);
        inPUnidad.value = textoNum(a.precio_nivel / factor());
        pintar();
    });
    listaLotes.addEventListener('click', (e) => {
        const b = e.target.closest('[data-ed-lote]');
        if (!b) return;
        lote = b.dataset.edLote;
        pintarLotes();
        pintar();
    });

    async function guardar(quitar = false) {
        const cantidad = quitar ? 0 : modo === 'unidad' ? Math.floor(numero(inUnidades.value)) : numero(inCajas.value);
        if (!quitar && !(cantidad > 0)) return toast('Escribe la cantidad (o usa Quitar).');
        const body = { codigo: a.codigo, cantidad, modo, lote };
        if (!quitar) {
            if (usarNivel) body.precio_nivel = true;
            else if (precioTocado) {
                const p = por === 'unidad' ? numero(inPUnidad.value) : numero(inPCaja.value);
                if (!(p > 0)) return toast('Escribe un precio mayor que cero.');
                Object.assign(body, { precio: p, por });
            }
        }

        $$('button', form).forEach((b) => (b.disabled = true));
        inflight++;
        try {
            const { res, data } = await postJson('/pedido/items/editar', body);
            if (data.resumen) renderResumen(data.resumen);
            if (res.status === 409) return void (location.href = '/clientes');
            if (!res.ok) {
                haptic();
                toast(data.errors ? Object.values(data.errors)[0][0] : data.message ?? 'No se pudo guardar.');
                if (data.eliminado) {
                    h.cerrar();
                    removeRow(a.codigo);
                }
                return;
            }
            h.cerrar();
            if (data.eliminado) return removeRow(a.codigo);
            // La fila nueva viene dibujada del servidor (mismos textos que al cargar).
            const li = fila.closest('[data-cart-row]');
            li.insertAdjacentHTML('afterend', data.html);
            li.remove();
            if (data.ajustado || data.excede) {
                haptic();
                toast(data.message);
            }
        } catch {
            toast('Sin conexión. No se guardó el cambio.');
        } finally {
            inflight--;
            $$('button', form).forEach((b) => (b.disabled = false));
        }
    }

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        guardar();
    });
    el('quitar').addEventListener('click', () => guardar(true));
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-editar-articulo]');
        if (btn) abrir(btn);
    });
}

/* Consecutivo de Softland del pedido (DESP-A, DESP-B…): se elige en un panel. */
function initConsecutivo() {
    const sheet = $('[data-consecutivo-sheet]');
    const trigger = $('[data-consecutivo-trigger]');
    if (!sheet || !trigger) return;
    const h = hoja(sheet);

    trigger.addEventListener('click', () => h.abrir(trigger));
    sheet.addEventListener('click', async (e) => {
        const b = e.target.closest('[data-consecutivo]');
        if (!b) return;
        if (b.getAttribute('aria-checked') === 'true') return h.cerrar();
        $$('[data-consecutivo]', sheet).forEach((x) => (x.disabled = true));
        try {
            const { res, data } = await postJson('/pedido/consecutivo', { consecutivo: b.dataset.consecutivo });
            if (!res.ok) {
                haptic();
                toast(data.message ?? 'No se pudo cambiar el consecutivo.');
                return;
            }
            $$('[data-consecutivo]', sheet).forEach((x) => {
                const sel = x === b;
                x.setAttribute('aria-checked', String(sel));
                const check = $('[data-check]', x);
                check.className = `grid size-6 shrink-0 place-items-center rounded-full ${sel ? 'bg-royal text-white' : 'ring-line ring-2'}`;
                $('svg', check).classList.toggle('hidden', !sel);
            });
            $('[data-numero-pedido]', trigger).textContent = `N.º ${data.numero}`;
            $('[data-consecutivo-texto]', trigger).textContent = data.consecutivo;
            h.cerrar();
        } catch {
            toast('Sin conexión. El consecutivo no se cambió.');
        } finally {
            $$('[data-consecutivo]', sheet).forEach((x) => (x.disabled = false));
        }
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
                html: `Se creará el pedido en Softland para<br><b>${escapar(d.cliente)}</b><br>
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
initEditor();
initFecha();
initFiltroFechas();
initConsecutivo();
initConfirmForm();
initSoftland();
if (window.__aviso) toast(window.__aviso);
