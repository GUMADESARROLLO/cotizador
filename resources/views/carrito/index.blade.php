<x-layouts.app title="Pedido" slogan="Revisa cantidades y confirma" tab="pedido" :resumen="$resumen">
    @if (count($items))
        <x-slot:trailing>
            <form method="POST" action="{{ route('carrito.vaciar') }}"
                  onsubmit="return confirm('¿Vaciar el pedido? Se quitarán todos los artículos.')">
                @csrf @method('DELETE')
                <button class="pressable text-danger h-11 px-1 text-sm font-extrabold tracking-[0.06em] uppercase">Vaciar</button>
            </form>
        </x-slot:trailing>
    @endif

    @if ($editando)
        <div class="mb-4 flex items-center justify-between gap-3 rounded-[20px] bg-white px-4 py-3 shadow-[0_0_0_2px_var(--color-cyan)]">
            <p class="min-w-0">
                <span class="text-cyan-ink block text-[11px] font-extrabold tracking-[0.14em] uppercase">Modificando pedido</span>
                <span class="text-ink block leading-tight font-bold">Creado el <span class="num text-[18px] font-extrabold">{{ $editando->created_at->format('d/m/Y H:i') }}</span></span>
            </p>
            <form method="POST" action="{{ route('pedidos.cancelar-edicion') }}">
                @csrf
                <button class="pressable text-danger shrink-0 px-1 py-2 text-[12px] font-extrabold tracking-[0.06em] uppercase">Cancelar edición</button>
            </form>
        </div>
    @endif

    <x-cliente-actual :cliente="$cliente" :total="$resumen['total']" />

    <div data-cart-empty @if (count($items)) hidden @endif class="flex flex-col items-center px-8 pt-10 pb-6 text-center">
        <div class="empty-icon">
            <svg viewBox="0 0 24 24" class="size-8" fill="currentColor" aria-hidden="true"><path d="M7 7V6a5 5 0 0 1 10 0v1h2.1a1 1 0 0 1 1 .92l1 12A2 2 0 0 1 19.1 22H4.9a2 2 0 0 1-2-2.08l1-12A1 1 0 0 1 4.9 7H7Zm2 0h6V6a3 3 0 0 0-6 0v1Z"/></svg>
        </div>
        <p class="text-title font-extrabold text-white">Tu pedido está vacío</p>
        <p class="text-sub text-on-royal-soft mt-1 max-w-xs">Busca artículos y agrégalos; aquí verás cantidades y total.</p>
        <a href="{{ route('articulos.index') }}" class="btn-primary mt-6 w-auto">Buscar artículos</a>
    </div>

    @if (count($items))
        <div data-cart-content class="space-y-6">
            {{-- Datos del pedido en una fila: fecha (toca → calendario) y número tentativo en Softland. --}}
            <section>
                <p class="section-label">Datos del pedido</p>
                <div class="group-list flex items-stretch">
                    {{-- Fecha: hoy por defecto; se puede poner otra (p. ej. armarlo hoy con fecha del lunes). --}}
                    <button type="button" data-fecha-trigger aria-haspopup="dialog" aria-label="Cambiar fecha del pedido"
                            data-fecha="{{ $fecha->format('Y-m-d') }}" data-hoy="{{ today()->format('Y-m-d') }}" data-min="{{ $fechaMin->format('Y-m-d') }}" data-max="{{ $fechaMax->format('Y-m-d') }}"
                            class="pressable active:bg-mist flex min-w-0 flex-1 items-center gap-3 py-3 pr-2 pl-4 text-left">
                        <span class="bg-royal text-cyan grid size-9 shrink-0 place-items-center rounded-full" aria-hidden="true">
                            <svg viewBox="0 0 24 24" class="size-[18px]" fill="currentColor"><path d="M8 2a1 1 0 0 1 1 1v1h6V3a1 1 0 1 1 2 0v1h1a3 3 0 0 1 3 3v11a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3h1V3a1 1 0 0 1 1-1Zm11 8H5v8a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-8ZM6 6a1 1 0 0 0-1 1v1h14V7a1 1 0 0 0-1-1H6Z"/></svg>
                        </span>
                        <span class="num text-ink min-w-0 text-[22px] leading-tight font-extrabold whitespace-nowrap" data-fecha-texto>{{ fecha_corta($fecha) }}</span>
                        <svg viewBox="0 0 24 24" class="text-royal size-4 shrink-0" fill="currentColor" aria-hidden="true"><path d="M4.3 8.3a1 1 0 0 1 1.4 0l6.3 6.29 6.3-6.3a1 1 0 1 1 1.4 1.42l-7 7a1 1 0 0 1-1.4 0l-7-7a1 1 0 0 1 0-1.42Z"/></svg>
                    </button>
                    {{-- Número tentativo (se asigna al enviar) y de qué consecutivo de Softland sale. --}}
                    {{-- Toca → elegir el consecutivo (DESP-A, DESP-B…); el número cambia según el elegido. --}}
                    <button type="button" data-consecutivo-trigger aria-haspopup="dialog" aria-label="Pedido Softland: cambiar consecutivo"
                            class="pressable active:bg-mist border-line flex shrink-0 flex-col items-end justify-center border-l py-3 pr-4 pl-3 text-right">
                        <span class="num text-ink text-[22px] leading-tight font-extrabold whitespace-nowrap" data-numero-pedido>{{ $numeroPedido ? 'N.º '.$numeroPedido : '—' }}</span>
                        <span class="text-label-2 flex items-center gap-1 text-[12px] whitespace-nowrap">Consecutivo <b class="text-ink" data-consecutivo-texto>{{ $codigoConsecutivo }}</b>
                            <svg viewBox="0 0 24 24" class="text-royal size-3.5" fill="currentColor" aria-hidden="true"><path d="M4.3 8.3a1 1 0 0 1 1.4 0l6.3 6.29 6.3-6.3a1 1 0 1 1 1.4 1.42l-7 7a1 1 0 0 1-1.4 0l-7-7a1 1 0 0 1 0-1.42Z"/></svg>
                        </span>
                    </button>
                </div>
            </section>

            <section>
                <p class="section-label flex justify-between">
                    <span>Artículos · <span data-cart-lineas>{{ $resumen['lineas'] }}</span></span>
                    <span class="normal-case tracking-normal font-semibold">Toca uno para editarlo</span>
                </p>
                <ul class="group-list" data-cart-lista>
                    @foreach ($items as $i)
                        @include('carrito._linea', [
                            'i' => $i,
                            'lotesArt' => $lotes[$i['codigo']] ?? [],
                            'ajustado' => in_array($i['codigo'], session('ajustados', [])),
                        ])
                    @endforeach
                </ul>
            </section>

            <form method="POST" action="{{ route('pedidos.store') }}" class="space-y-6" data-confirm-form>
                @csrf
                <section>
                    <label for="notas" class="section-label block">Observaciones</label>
                    <textarea id="notas" name="notas" rows="2" maxlength="2000"
                              placeholder="Ej.: ORDEN DE COMPRA No. 121164"
                              class="text-ink placeholder:text-label-3 block w-full resize-none rounded-[20px] bg-white px-4 py-3 font-medium shadow-[0_30px_60px_-30px_rgb(9_16_56/0.6)] outline-none focus:shadow-[0_0_0_3px_var(--color-cyan)]">{{ old('notas', $observaciones) }}</textarea>
                    <p class="text-on-royal-soft px-1 pt-1.5 text-[12px]">Va a Softland como observaciones del pedido.</p>
                </section>

                <button class="btn-primary">
                    {{ $editando ? 'Guardar cambios' : 'Confirmar pedido' }} · <span class="num text-xl" data-cart-total>{{ dinero($resumen['total']) }}</span>
                </button>
            </form>
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
