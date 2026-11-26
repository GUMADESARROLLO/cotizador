@props(['cliente', 'total' => null])
{{-- Cliente del pedido en curso, con acceso para cambiarlo. --}}
<section class="mb-4 overflow-hidden rounded-[20px] bg-white text-ink shadow-[0_0_0_2px_var(--color-cyan),0_24px_50px_-24px_rgb(9_16_56/0.8)]" aria-label="Cliente del pedido">
    <div class="flex items-start gap-3 px-4 py-3">
        <span class="bg-royal text-cyan mt-0.5 grid size-10 shrink-0 place-items-center rounded-full shadow-[0_0_0_4px_rgb(41_182_232/0.2)]" aria-hidden="true">
            <svg viewBox="0 0 24 24" class="size-5" fill="currentColor"><path d="M12 2a5 5 0 1 1 0 10 5 5 0 0 1 0-10Zm0 12c4.42 0 8 2.24 8 5v1a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-1c0-2.76 3.58-5 8-5Z"/></svg>
        </span>
        <div class="min-w-0 flex-1">
            <p class="text-cyan-ink text-[11px] font-extrabold tracking-[0.14em] uppercase">Cliente del pedido</p>
            <p class="line-clamp-2 leading-snug font-bold">{{ $cliente->nombre }}</p>
            <p class="text-foot text-label-2 mt-1 flex flex-wrap items-center gap-x-2 gap-y-1">
                <span class="num text-[15px] font-bold">{{ $cliente->codigo }}</span>
                @if ($cliente->moroso)
                    <span class="badge bg-danger-soft text-danger uppercase">Moroso</span>
                @endif
                <span>Disponible <span class="num text-[15px] font-bold {{ $cliente->disponible > 0 ? 'text-ink' : 'text-danger' }}">{{ dinero($cliente->disponible) }}</span></span>
            </p>
        </div>
        <a href="{{ route('clientes.index') }}" class="pressable text-royal -mr-1 shrink-0 px-1 py-2 text-[13px] font-extrabold tracking-[0.06em] uppercase">Cambiar</a>
    </div>

    @if ($total !== null)
        {{-- Aviso de crédito: el JS lo muestra/oculta al cambiar el total. No bloquea el pedido. --}}
        <p data-credit-warning data-disponible="{{ $cliente->disponible }}" @if ($total <= $cliente->disponible) hidden @endif
           class="bg-warn-soft text-warn border-line border-t px-4 py-2.5 text-[13px] font-semibold">
            ⚠ El total supera el crédito disponible del cliente.
        </p>
    @endif
</section>
