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

    <div class="space-y-7">
        <ul class="group-list">
            <li class="flex justify-between gap-4 px-4 py-3"><span class="text-label-2 font-semibold">Cliente</span><span class="text-right font-bold">{{ $pedido->cliente }}</span></li>
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
                            <p class="text-foot text-label-2 mt-1"><span class="num text-[15px] font-bold">{{ $i->codigo }} · {{ cantidad($i->cantidad) }} {{ $i->unidad }} × {{ dinero($i->precio) }}</span></p>
                        </div>
                        <p class="price shrink-0">{{ dinero($i->subtotal) }}</p>
                    </li>
                @endforeach
                <li class="bg-royal flex items-center justify-between px-4 py-4 text-white">
                    <span class="text-sm font-extrabold tracking-[0.1em] uppercase">Total</span>
                    <span class="num text-[36px] leading-none font-extrabold [text-shadow:0_0_24px_rgb(41_182_232/0.7)]">{{ dinero($pedido->total) }}</span>
                </li>
            </ul>
        </section>

        @if ($pedido->notas)
            <section>
                <p class="section-label">Notas</p>
                <p class="text-ink rounded-[20px] bg-white px-4 py-3 whitespace-pre-line">{{ $pedido->notas }}</p>
            </section>
        @endif
    </div>
</x-layouts.app>
