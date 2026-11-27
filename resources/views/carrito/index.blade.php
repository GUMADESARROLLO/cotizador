<x-layouts.app title="Pedido" sub="Revisa cantidades, lotes y precios antes de confirmar" section="pedido" :resumen="$resumen">
    <x-slot:actions>
        @if (count($items))
            <a href="{{ route('softland.vista-previa') }}" class="btn btn-line">Ver datos para Softland</a>
        @endif
        <a href="{{ route('articulos.index') }}" class="btn btn-line">Agregar artículos</a>
        @if (count($items))
            <form method="POST" action="{{ route('carrito.vaciar') }}"
                  data-swal-confirm="¿Vaciar el pedido?" data-swal-texto="Se quitarán todos los artículos."
                  data-swal-boton="Sí, vaciar" data-swal-peligro>
                @csrf @method('DELETE')
                <button class="btn btn-line" style="color: var(--color-danger)">Vaciar</button>
            </form>
        @endif
    </x-slot:actions>

    @if ($editando)
        <div class="ped-edit">
            <p>
                <span class="eyebrow">Modificando pedido</span>
                <strong>Creado el <span class="num" style="font-size: 18px">{{ $editando->created_at->format('d/m/Y H:i') }}</span></strong>
            </p>
            <form method="POST" action="{{ route('pedidos.cancelar-edicion') }}">
                @csrf
                <button class="btn btn-line" style="color: var(--color-danger)">Cancelar edición</button>
            </form>
        </div>
    @endif

    <div data-cart-empty @if (count($items)) hidden @endif class="card ped-block">
        <div class="empty">
            <strong>Tu pedido está vacío</strong>
            Busca artículos y agrégalos; aquí verás cantidades y total.
            <div style="margin-top: 16px"><a href="{{ route('articulos.index') }}" class="btn btn-solid">Buscar artículos</a></div>
        </div>
    </div>

    @if (count($items))
        {{-- Misma estructura que el detalle de un pedido guardado: resumen arriba
             (cliente · fecha · N.º Softland · total), observaciones + confirmar y la tabla. --}}
        <div data-cart-content class="ped-pagina">
            <div class="ped-cabecera">
                @include('pedidos._cliente', ['cliente' => $cliente, 'total' => $resumen['total'], 'cambiar' => true])

                {{-- Fecha: hoy por defecto; se puede poner otra (p. ej. armarlo hoy con fecha del lunes). --}}
                <button type="button" data-fecha-trigger aria-haspopup="dialog" aria-label="Cambiar fecha del pedido"
                        data-fecha="{{ $fecha->format('Y-m-d') }}" data-hoy="{{ today()->format('Y-m-d') }}" data-min="{{ $fechaMin->format('Y-m-d') }}" data-max="{{ $fechaMax->format('Y-m-d') }}"
                        class="kpi kpi-btn">
                    <span class="kpi-label">Fecha del pedido</span>
                    <span class="kpi-value" style="font-size: 24px" data-fecha-texto>{{ fecha_corta($fecha) }}</span>
                    <span class="kpi-sub kpi-accion">Cambiar fecha
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor" aria-hidden="true"><path d="M4.3 8.3a1 1 0 0 1 1.4 0l6.3 6.29 6.3-6.3a1 1 0 1 1 1.4 1.42l-7 7a1 1 0 0 1-1.4 0l-7-7a1 1 0 0 1 0-1.42Z"/></svg>
                    </span>
                </button>

                {{-- Toca → elegir el consecutivo (DESP-A, DESP-B…); el número cambia según el elegido. --}}
                <button type="button" data-consecutivo-trigger aria-haspopup="dialog" aria-label="Pedido Softland: cambiar consecutivo"
                        class="kpi kpi-btn">
                    <span class="kpi-label">Pedido Softland</span>
                    <span class="kpi-value" style="font-size: 24px" data-numero-pedido>{{ $numeroPedido ? 'N.º '.$numeroPedido : '—' }}</span>
                    <span class="kpi-sub kpi-accion">Consecutivo <b data-consecutivo-texto>{{ $codigoConsecutivo }}</b>
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor" aria-hidden="true"><path d="M4.3 8.3a1 1 0 0 1 1.4 0l6.3 6.29 6.3-6.3a1 1 0 1 1 1.4 1.42l-7 7a1 1 0 0 1-1.4 0l-7-7a1 1 0 0 1 0-1.42Z"/></svg>
                    </span>
                </button>

                <div class="kpi">
                    <p class="kpi-label">Total</p>
                    <p class="kpi-value" style="color: var(--color-royal)" data-cart-total>{{ dinero($resumen['total']) }}</p>
                    <p class="kpi-sub"><span data-cart-lineas>{{ $resumen['lineas'] }}</span> artículo(s) · <span data-cart-piezas>{{ cantidad($resumen['piezas']) }}</span> pieza(s)</p>
                </div>
            </div>

            {{-- Observaciones (van a Softland) y confirmar: el primer <button> del form es el de enviar. --}}
            <form method="POST" action="{{ route('pedidos.store') }}" data-confirm-form class="card ped-notas ped-confirmar">
                @csrf
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                <div class="ped-confirmar-obs">
                    <label for="notas" class="eyebrow">Observaciones</label>
                    <textarea id="notas" name="notas" rows="1" maxlength="2000"
                              placeholder="Ej.: ORDEN DE COMPRA No. 121164">{{ old('notas', $observaciones) }}</textarea>
                    <p class="hint">Va a Softland como observaciones del pedido.</p>
                </div>
                <button class="btn btn-solid ped-confirm">{{ $editando ? 'Guardar cambios' : 'Confirmar pedido' }}</button>
            </form>

            <section class="card" aria-label="Artículos del pedido">
                <div class="card-head">
                    <h2><span data-cart-lineas>{{ $resumen['lineas'] }}</span> artículo(s)</h2>
                    <small>Toca una fila para editar cantidad, precio o lote</small>
                </div>
                <div class="tbl-box">
                    <table class="tbl" data-orden-local>
                        <thead>
                            <tr>
                                <x-th-orden campo="articulo">Artículo</x-th-orden>
                                <x-th-orden campo="cantidad" class="th-num">Cantidad</x-th-orden>
                                <x-th-orden campo="precio" class="th-num">Precio</x-th-orden>
                                <x-th-orden campo="subtotal" class="th-num">Subtotal</x-th-orden>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody data-cart-lista>
                            @foreach ($items as $i)
                                @include('carrito._linea', [
                                    'i' => $i,
                                    'lotesArt' => $lotes[$i['codigo']] ?? [],
                                    'imagenes' => $imagenes ?? [],
                                    'ajustado' => in_array($i['codigo'], session('ajustados', [])),
                                ])
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    @endif

    @if (count($items))
    {{-- Consecutivo de Softland del pedido (bottom sheet): los que empiezan con DESP-. --}}
    <dialog data-consecutivo-sheet class="lote-sheet" aria-labelledby="consecutivo-sheet-titulo">
        <div class="sheet-panel" data-sheet-panel>
            <div class="flex cursor-grab touch-none flex-col items-center pt-2.5 pb-1" data-sheet-grab>
                <span class="bg-silver h-[5px] w-10 rounded-full" aria-hidden="true"></span>
            </div>
            <header class="px-5 pt-2 pb-3" data-sheet-grab>
                <p class="text-cyan-ink text-[11px] font-extrabold tracking-[0.14em] uppercase">Pedido Softland</p>
                <h2 id="consecutivo-sheet-titulo" class="text-ink mt-0.5 text-[17px] leading-snug font-bold">Consecutivo del pedido</h2>
                <p class="text-label-2 mt-1 text-[13px]">El número es tentativo: se asigna al enviar a Softland.</p>
            </header>
            <ul class="border-line border-t" role="radiogroup" aria-label="Consecutivo">
                @foreach ($consecutivos as $c)
                    @php $sel = $c['codigo'] === $codigoConsecutivo; @endphp
                    <li class="border-line border-b last:border-b-0">
                        <button type="button" role="radio" aria-checked="{{ $sel ? 'true' : 'false' }}" data-consecutivo="{{ $c['codigo'] }}"
                                class="lote-opcion pressable flex min-h-16 w-full items-center gap-3 px-5 py-3 text-left">
                            <span class="min-w-0 flex-1">
                                <span class="num text-ink block text-[19px] font-extrabold">{{ $c['codigo'] }}</span>
                                <span class="text-label-2 block text-[14px]">{{ $c['descripcion'] }}</span>
                            </span>
                            <span class="num text-ink shrink-0 text-[18px] font-bold">N.º {{ $c['siguiente'] }}</span>
                            <span class="grid size-6 shrink-0 place-items-center rounded-full {{ $sel ? 'bg-royal text-white' : 'ring-line ring-2' }}" data-check aria-hidden="true">
                                <svg viewBox="0 0 24 24" class="size-4 {{ $sel ? '' : 'hidden' }}" fill="currentColor"><path d="M9.55 17.6a1 1 0 0 1-.7-.3l-4.2-4.2a1 1 0 1 1 1.4-1.4l3.5 3.5 8.3-8.3a1 1 0 1 1 1.4 1.4l-9 9a1 1 0 0 1-.7.3Z"/></svg>
                            </span>
                        </button>
                    </li>
                @endforeach
            </ul>
            <div class="space-y-2 px-5 pt-3" style="padding-bottom: max(16px, env(safe-area-inset-bottom))">
                <a href="{{ route('softland.vista-previa') }}" class="pressable text-royal bg-cyan-soft block w-full rounded-[14px] py-3.5 text-center text-[15px] font-extrabold tracking-[0.06em] uppercase">Ver datos para Softland</a>
                <button type="button" data-sheet-cerrar class="pressable bg-mist text-royal w-full rounded-[14px] py-3.5 text-[15px] font-extrabold tracking-[0.06em] uppercase">Cancelar</button>
            </div>
        </div>
    </dialog>
    @endif

    {{-- Calendario de la fecha del pedido (bottom sheet). --}}
    <x-calendario marca="data-fecha-sheet" titulo="Fecha del pedido" />

    {{-- Editar artículo (bottom sheet): cantidad, precio y lote en un solo lugar. Lo llena app.js. --}}
    <dialog data-articulo-sheet class="lote-sheet" aria-labelledby="articulo-sheet-titulo">
        <form class="sheet-panel flex max-h-[92dvh] flex-col" data-sheet-panel data-articulo-form novalidate>
            <div class="flex cursor-grab touch-none flex-col items-center pt-2.5 pb-1" data-sheet-grab>
                <span class="bg-silver h-[5px] w-10 rounded-full" aria-hidden="true"></span>
            </div>
            <header class="border-line border-b px-5 pt-1 pb-3" data-sheet-grab>
                <p class="text-cyan-ink num text-[13px] font-extrabold tracking-[0.1em]" data-ed-codigo></p>
                <h2 id="articulo-sheet-titulo" class="text-ink line-clamp-2 text-[17px] leading-snug font-bold" data-ed-titulo></h2>
            </header>

            <div class="min-h-0 flex-1 space-y-5 overflow-y-auto overscroll-contain px-5 py-4">
                {{-- Cantidad: cajas y unidades enlazadas; se escribe en cualquiera. --}}
                <fieldset>
                    <legend class="text-label-2 mb-2 text-[12px] font-extrabold tracking-[0.12em] uppercase">Cantidad</legend>
                    <div class="grid grid-cols-2 gap-2" data-ed-cantidades>
                        <label class="ed-campo">
                            <input type="text" inputmode="decimal" autocomplete="off" data-ed-cajas aria-label="Cantidad en cajas">
                            <span data-ed-u-caja></span>
                        </label>
                        <label class="ed-campo" data-ed-solo-empaque>
                            <input type="text" inputmode="numeric" autocomplete="off" data-ed-unidades aria-label="Cantidad en unidades">
                            <span data-ed-u-interna></span>
                        </label>
                    </div>
                    <p class="text-label-2 mt-1.5 text-[13px]" data-ed-disponible aria-live="polite"></p>
                </fieldset>

                {{-- Precio: por caja (lo que va a Softland) o por unidad; enlazados. --}}
                <fieldset>
                    <legend class="text-label-2 mb-2 text-[12px] font-extrabold tracking-[0.12em] uppercase">Precio</legend>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="ed-campo">
                            <span class="ed-moneda">{{ config('articulos.currency') }}</span>
                            <input type="text" inputmode="decimal" autocomplete="off" data-ed-precio-caja aria-label="Precio por caja" placeholder="0.00">
                            <span data-ed-p-caja></span>
                        </label>
                        <label class="ed-campo" data-ed-solo-empaque>
                            <span class="ed-moneda">{{ config('articulos.currency') }}</span>
                            <input type="text" inputmode="decimal" autocomplete="off" data-ed-precio-unidad aria-label="Precio por unidad" placeholder="0.00">
                            <span data-ed-p-interna></span>
                        </label>
                    </div>
                    <p class="text-label-2 mt-1.5 flex flex-wrap items-center justify-between gap-2 text-[13px]">
                        <span data-ed-nivel-texto></span>
                        <button type="button" data-ed-usar-nivel class="pressable text-royal text-[12px] font-extrabold tracking-[0.06em] uppercase">Usar precio del nivel</button>
                    </p>
                </fieldset>

                {{-- Lote de despacho (bodega del pedido). Sugerido: el que vence primero. --}}
                <fieldset data-ed-lotes-grupo>
                    <legend class="text-label-2 mb-2 text-[12px] font-extrabold tracking-[0.12em] uppercase">Lote · bodega {{ config('softland.bodega') }}</legend>
                    <ul class="ring-line overflow-hidden rounded-[14px] ring-1" data-ed-lotes role="radiogroup" aria-label="Lote"></ul>
                </fieldset>

                <p class="bg-mist text-ink rounded-[14px] px-4 py-3 text-[15px]" aria-live="polite">
                    Subtotal <span class="num float-right text-[19px] font-extrabold" data-ed-subtotal></span>
                </p>
            </div>

            <div class="border-line space-y-2 border-t px-5 pt-3" style="padding-bottom: max(16px, env(safe-area-inset-bottom))">
                <button type="submit" class="btn-primary" data-ed-guardar>Guardar</button>
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" data-ed-quitar class="pressable bg-danger-soft text-danger rounded-[14px] py-3.5 text-[14px] font-extrabold tracking-[0.06em] uppercase">Quitar</button>
                    <button type="button" data-sheet-cerrar class="pressable bg-mist text-royal rounded-[14px] py-3.5 text-[14px] font-extrabold tracking-[0.06em] uppercase">Cancelar</button>
                </div>
            </div>
        </form>
    </dialog>
</x-layouts.app>
