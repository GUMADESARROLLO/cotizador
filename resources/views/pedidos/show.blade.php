<x-layouts.app :title="'Pedido '.$pedido->folio" :slogan="$pedido->cliente" tab="historial">
    <x-slot:leading>
        <a href="{{ route('pedidos.index') }}" class="pressable text-royal -ml-2 flex h-11 items-center gap-0.5 px-1 text-sm font-extrabold tracking-[0.06em] uppercase">
            <svg viewBox="0 0 24 24" class="size-6" fill="currentColor" aria-hidden="true"><path d="M15.7 4.3a1 1 0 0 1 0 1.4L9.41 12l6.3 6.3a1 1 0 0 1-1.42 1.4l-7-7a1 1 0 0 1 0-1.4l7-7a1 1 0 0 1 1.42 0Z"/></svg>
            Historial
        </a>
    </x-slot:leading>
    <x-slot:trailing>
        <button type="button" onclick="window.print()" class="pressable text-royal h-11 px-1 text-sm font-extrabold tracking-[0.06em] uppercase">Imprimir</button>
    </x-slot:trailing>

    @if (session('creado'))
        <div class="no-print text-ink mb-6 flex items-center gap-3 rounded-[20px] bg-gradient-to-r from-white to-[#e6f6fd] px-4 py-3 font-bold shadow-[0_0_0_3px_var(--color-cyan),0_24px_50px_-18px_rgb(41_182_232/0.85)] animate-card-in">
            <span class="bg-royal text-cyan grid size-11 shrink-0 place-items-center rounded-full shadow-[0_0_0_4px_rgb(41_182_232/0.25)]">
                <svg viewBox="0 0 24 24" class="size-6" fill="currentColor" aria-hidden="true"><path d="M9.55 17.6a1 1 0 0 1-.7-.3l-4.2-4.2a1 1 0 1 1 1.4-1.4l3.5 3.5 8.3-8.3a1 1 0 1 1 1.4 1.4l-9 9a1 1 0 0 1-.7.3Z"/></svg>
            </span>
            Pedido confirmado
        </div>
    @endif
    @if (session('actualizado'))
        <div class="no-print text-ink mb-6 flex items-center gap-3 rounded-[20px] bg-gradient-to-r from-white to-[#e6f6fd] px-4 py-3 font-bold shadow-[0_0_0_3px_var(--color-cyan)] animate-card-in">
            <span class="bg-royal text-cyan grid size-11 shrink-0 place-items-center rounded-full">
                <svg viewBox="0 0 24 24" class="size-6" fill="currentColor" aria-hidden="true"><path d="M9.55 17.6a1 1 0 0 1-.7-.3l-4.2-4.2a1 1 0 1 1 1.4-1.4l3.5 3.5 8.3-8.3a1 1 0 1 1 1.4 1.4l-9 9a1 1 0 0 1-.7.3Z"/></svg>
            </span>
            Cambios guardados
        </div>
    @endif

    {{-- Envío manual a Softland --}}
    <section class="no-print mb-6" aria-label="Softland">
        @if ($pedido->enviadoASoftland())
            <div class="flex items-center gap-3 rounded-[20px] bg-white px-4 py-3 shadow-[0_0_0_2px_var(--color-cyan)]">
                <span class="bg-royal text-cyan grid size-11 shrink-0 place-items-center rounded-full" aria-hidden="true">
                    <svg viewBox="0 0 24 24" class="size-6" fill="currentColor"><path d="M9.55 17.6a1 1 0 0 1-.7-.3l-4.2-4.2a1 1 0 1 1 1.4-1.4l3.5 3.5 8.3-8.3a1 1 0 1 1 1.4 1.4l-9 9a1 1 0 0 1-.7.3Z"/></svg>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-cyan-ink text-[11px] font-extrabold tracking-[0.14em] uppercase">En Softland</p>
                    <p class="text-ink"><span class="num text-[26px] leading-none font-extrabold">N.º {{ $pedido->softland_pedido }}</span></p>
                    <p class="text-label-2 text-[12px]">Enviado el {{ $pedido->softland_enviado_at?->format('d/m/Y H:i') }} · consecutivo {{ config('softland.consecutivo') }}</p>
                </div>
            </div>
        @elseif ($pedido->softland_estado === \App\Models\Pedido::SOFTLAND_ENVIANDO)
            <p class="rounded-[20px] bg-white/10 px-4 py-3 text-white ring-1 ring-white/20">
                Enviando a Softland… recarga la página en unos segundos.
                <span class="text-on-royal-soft block text-[12px]">Si el envío se interrumpió, al cabo de {{ config('softland.envio_trabado_minutos') }} minutos se comprobará en Softland y se mostrará el resultado.</span>
            </p>
        @else
            @if ($pedido->softland_estado === \App\Models\Pedido::SOFTLAND_ERROR)
                <div class="bg-danger-soft text-danger mb-3 rounded-[20px] px-4 py-3 text-sm">
                    <p class="font-extrabold">No se pudo enviar a Softland</p>
                    <p class="mt-0.5 font-semibold">{{ $pedido->softland_error }}</p>
                </div>
            @endif
            <form method="POST" action="{{ route('pedidos.editar', $pedido) }}" class="mb-3">
                @csrf
                <button class="pressable text-royal flex w-full items-center justify-center gap-2 rounded-[14px] bg-white py-3 text-[14px] font-extrabold tracking-[0.06em] uppercase shadow-[0_16px_36px_-20px_rgb(9_16_56/0.7)]">
                    <svg viewBox="0 0 24 24" class="size-5" fill="currentColor" aria-hidden="true"><path d="M16.3 3.3a2.4 2.4 0 0 1 3.4 3.4L8.9 17.5a1 1 0 0 1-.46.26l-4 1a1 1 0 0 1-1.21-1.2l1-4a1 1 0 0 1 .26-.47L16.3 3.3Zm2 1.4a.4.4 0 0 0-.56 0L7.2 15.2l-.38 1.5 1.5-.38L18.3 5.26a.4.4 0 0 0 0-.56Z"/></svg>
                    Modificar pedido
                </button>
            </form>
            <form method="POST" action="{{ route('pedidos.softland', $pedido) }}" data-softland-form
                  data-folio="{{ $pedido->folio }}" data-cliente="{{ $pedido->cliente }}"
                  data-total="{{ dinero($pedido->total, $pedido->moneda ?? 'L') }}" data-lineas="{{ $pedido->lineas }}"
                  data-consecutivo="{{ config('softland.consecutivo') }}" data-usuario="{{ config('softland.usuario') }}">
                @csrf
                <button class="btn-primary">
                    <svg viewBox="0 0 24 24" class="size-5" fill="currentColor" aria-hidden="true"><path d="M3.4 20.4 21.85 12.5a.55.55 0 0 0 0-1L3.4 3.6a.5.5 0 0 0-.7.6L5 11l9 1-9 1-2.3 6.8a.5.5 0 0 0 .7.6Z"/></svg>
                    {{ $pedido->softland_estado === \App\Models\Pedido::SOFTLAND_ERROR ? 'Reintentar envío' : 'Enviar a Softland' }}
                </button>
            </form>
        @endif
    </section>

    @if (session('softland_ok') || session('softland_error'))
        <script>window.__softland = @json(['ok' => session('softland_ok'), 'error' => session('softland_error')]);</script>
    @endif

    <div class="space-y-7">
        <ul class="group-list">
            <li class="flex justify-between gap-4 px-4 py-3"><span class="text-label-2 font-semibold">Cliente</span><span class="text-right font-bold">{{ $pedido->cliente }}</span></li>
            @if ($pedido->cliente_codigo)
                <li class="flex justify-between gap-4 px-4 py-3"><span class="text-label-2 font-semibold">Código</span><span class="num text-lg font-bold">{{ $pedido->cliente_codigo }}</span></li>
            @endif
            @if ($pedido->nivel_precio)
                <li class="flex justify-between gap-4 px-4 py-3"><span class="text-label-2 font-semibold">Nivel de precio</span><span class="text-right font-semibold">{{ $pedido->nivel_precio }}</span></li>
            @endif
            @if ($pedido->condicion_pago)
                <li class="flex justify-between gap-4 px-4 py-3"><span class="text-label-2 font-semibold">Condición de pago</span><span class="num text-lg font-bold">{{ $pedido->condicion_pago }}</span></li>
            @endif
            @if ($pedido->cliente_ruc)
                <li class="flex justify-between gap-4 px-4 py-3"><span class="text-label-2 font-semibold">RUC</span><span class="text-right font-semibold">{{ $pedido->cliente_ruc }}</span></li>
            @endif
            @if ($pedido->contacto)
                <li class="flex justify-between gap-4 px-4 py-3"><span class="text-label-2 font-semibold">Contacto</span><span class="text-right font-semibold">{{ $pedido->contacto }}</span></li>
            @endif
            <li class="flex justify-between gap-4 px-4 py-3"><span class="text-label-2 font-semibold">Fecha</span><span class="num text-lg font-bold">{{ $pedido->created_at->format('d/m/Y H:i') }}</span></li>
        </ul>

        <section>
            <p class="section-label">{{ $pedido->lineas }} artículo(s)</p>
            <ul class="group-list">
                @foreach ($pedido->items as $i)
                    <li class="flex items-start justify-between gap-3 px-4 py-3.5">
                        <div class="min-w-0">
                            <p class="leading-snug font-semibold">{{ $i->descripcion }}</p>
                            <p class="text-foot text-label-2 mt-1"><span class="num text-[15px] font-bold">{{ $i->codigo }} · {{ cantidad($i->cantidad) }} {{ $i->unidad }} × {{ dinero($i->precio, $pedido->moneda ?? 'L') }}</span></p>
                            @if ($i->lote)
                                <p class="text-foot text-label-2">Lote <span class="num text-ink text-[15px] font-bold">{{ $i->lote }}</span>@if ($i->lote_vence) · vence <span class="num text-[15px] font-bold">{{ \Illuminate\Support\Carbon::parse($i->lote_vence)->format('d/m/Y') }}</span>@endif</p>
                            @endif
                        </div>
                        <p class="price shrink-0">{{ dinero($i->subtotal, $pedido->moneda ?? 'L') }}</p>
                    </li>
                @endforeach
                <li class="bg-royal flex items-center justify-between px-4 py-4 text-white">
                    <span class="text-sm font-extrabold tracking-[0.1em] uppercase">Total</span>
                    <span class="num text-[36px] leading-none font-extrabold [text-shadow:0_0_24px_rgb(41_182_232/0.7)]">{{ dinero($pedido->total, $pedido->moneda ?? 'L') }}</span>
                </li>
            </ul>
        </section>

        @if ($pedido->credito_disponible !== null && $pedido->total > $pedido->credito_disponible)
            <p class="bg-warn-soft text-warn rounded-[20px] px-4 py-3 text-sm font-semibold">
                ⚠ Al confirmar, el total superaba el crédito disponible del cliente ({{ dinero($pedido->credito_disponible, $pedido->moneda ?? 'L') }}).
            </p>
        @endif

        @if ($pedido->notas)
            <section>
                <p class="section-label">Observaciones</p>
                <p class="text-ink rounded-[20px] bg-white px-4 py-3 whitespace-pre-line">{{ $pedido->notas }}</p>
            </section>
        @endif
    </div>
</x-layouts.app>
