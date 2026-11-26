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
                <span class="num text-ink block text-[22px] leading-tight font-extrabold">{{ $editando->folio }}</span>
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
        <div data-cart-content class="space-y-7">
            {{-- Número tentativo en Softland: se asigna definitivamente al confirmar. --}}
            <section class="flex items-center justify-between gap-3 rounded-[20px] bg-white/10 px-4 py-3 text-white ring-1 ring-white/20" aria-label="Número de pedido en Softland">
                <div class="min-w-0">
                    <p class="text-on-royal-soft text-[11px] font-extrabold tracking-[0.14em] uppercase">Pedido Softland</p>
                    <p class="text-on-royal-soft mt-0.5 text-[12px]">Consecutivo {{ $codigoConsecutivo }} · se asigna al enviar a Softland</p>
                    <a href="{{ route('softland.vista-previa') }}" class="pressable text-cyan mt-1 inline-block text-[12px] font-extrabold tracking-[0.08em] uppercase underline-offset-2 hover:underline">Ver datos para Softland →</a>
                </div>
                <p class="num shrink-0 text-[30px] leading-none font-extrabold [text-shadow:0_0_24px_rgb(41_182_232/0.7)]" data-numero-pedido>
                    {{ $numeroPedido ? 'N.º '.$numeroPedido : '—' }}
                </p>
            </section>

            <section>
                <p class="section-label">Artículos</p>
                <ul class="group-list">
                    @foreach ($items as $i)
                        @php
                            $ajustado = in_array($i['codigo'], session('ajustados', []));
                            $lotesArt = $lotes[$i['codigo']] ?? [];
                            $loteSel = collect($lotesArt)->firstWhere('lote', $i['lote'] ?? null) ?? ($lotesArt[0] ?? null);
                            $maximo = $loteSel ? $loteSel->maximoPedible() : floor($i['existencia']);
                        @endphp
                        <li data-cart-row="{{ $i['codigo'] }}" class="flex flex-col gap-2.5 px-4 py-3.5 {{ $ajustado ? 'bg-warn-soft' : '' }}">
                            <div class="min-w-0">
                                <p class="text-body leading-snug font-semibold">{{ $i['descripcion'] }}</p>
                                <p class="text-foot text-label-2 mt-1 flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <span class="num text-[15px] font-bold">{{ $i['codigo'] }}</span>
                                    <span aria-hidden="true">·</span>
                                    <span class="num text-[15px] font-bold">{{ dinero($i['precio']) }}</span> / {{ $i['unidad'] ?: 'u' }}
                                    @unless ($loteSel)
                                        <span aria-hidden="true">·</span>
                                        <x-existencia :existencia="$i['existencia']" :unidad="$i['unidad']" />
                                    @endunless
                                </p>
                                @if ($loteSel)
                                    <x-lote-selector :codigo="$i['codigo']" :lotes="$lotesArt" :seleccionado="$loteSel" :unidad="$i['unidad']" :descripcion="$i['descripcion']" />
                                @endif
                                @if ($ajustado)
                                    <p class="text-foot text-warn mt-1 font-semibold">Ajustado a la existencia, lote o precio actual.</p>
                                @endif
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <x-stepper :codigo="$i['codigo']" :cantidad="$i['cantidad']" :max="$maximo" :agregar="false" />
                                <p class="price shrink-0" data-subtotal>{{ dinero($i['precio'] * $i['cantidad']) }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>

            <form method="POST" action="{{ route('pedidos.store') }}" class="space-y-6" data-confirm-form>
                @csrf
                <section>
                    <label for="notas" class="section-label block">Observaciones</label>
                    <textarea id="notas" name="notas" rows="3" maxlength="2000"
                              placeholder="Ej.: ORDEN DE COMPRA No. 121164"
                              class="text-ink placeholder:text-label-3 focus:shadow-[0_0_0_3px_var(--color-cyan)] block w-full resize-none rounded-[20px] bg-white px-4 py-3 font-medium shadow-[0_30px_60px_-30px_rgb(9_16_56/0.6)] outline-none">{{ old('notas', $observaciones) }}</textarea>
                    <p class="text-on-royal-soft px-1 pt-1.5 text-[12px]">Se envía a Softland como observaciones del pedido (p. ej. la orden de compra).</p>
                </section>

                <button class="btn-primary">
                    {{ $editando ? 'Guardar cambios' : 'Confirmar pedido' }} · <span class="num text-xl" data-cart-total>{{ dinero($resumen['total']) }}</span>
                </button>
            </form>
        </div>
    @endif

    {{-- Panel para elegir el lote (bottom sheet). Lo llena app.js con los lotes del artículo. --}}
    <dialog data-lote-sheet class="lote-sheet" aria-labelledby="lote-sheet-titulo">
        <div class="sheet-panel" data-sheet-panel>
            <div class="flex cursor-grab touch-none flex-col items-center pt-2.5 pb-1" data-sheet-grab>
                <span class="bg-silver h-[5px] w-10 rounded-full" aria-hidden="true"></span>
            </div>
            <header class="px-5 pt-2 pb-3" data-sheet-grab>
                <p class="text-cyan-ink text-[11px] font-extrabold tracking-[0.14em] uppercase">Elegir lote</p>
                <h2 id="lote-sheet-titulo" class="text-ink mt-0.5 line-clamp-2 text-[17px] leading-snug font-bold" data-sheet-titulo></h2>
                <p class="text-label-2 mt-1 text-[13px]">Sugerido: el que vence primero. Bodega {{ config('softland.bodega') }}.</p>
            </header>
            <ul class="border-line max-h-[55dvh] overflow-y-auto overscroll-contain border-t" data-sheet-lista role="listbox"></ul>
            <div class="px-5 pt-3" style="padding-bottom: max(16px, env(safe-area-inset-bottom))">
                <button type="button" data-sheet-cerrar class="pressable bg-mist text-royal w-full rounded-[14px] py-3.5 text-[15px] font-extrabold tracking-[0.06em] uppercase">Cancelar</button>
            </div>
        </div>
    </dialog>
</x-layouts.app>
