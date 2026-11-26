<x-layouts.app title="Elegir cliente" :slogan="'Clientes de '.config('clientes.vendedor_nombre').' ('.config('clientes.vendedor').')'" tab="cliente" :resumen="$resumen">
    @if ($actual)
        <div class="mb-4 flex items-center justify-between gap-3 rounded-[20px] bg-white/10 px-4 py-3 text-white ring-1 ring-white/20">
            <p class="min-w-0 text-sub">
                <span class="text-on-royal-soft block text-[11px] font-extrabold tracking-[0.14em] uppercase">Pedido en curso</span>
                <span class="block truncate font-bold">{{ $actual->nombre }}</span>
            </p>
            <a href="{{ route('articulos.index') }}" class="pressable bg-cyan text-navy shrink-0 rounded-xl px-4 py-2.5 text-[13px] font-extrabold tracking-[0.06em] uppercase">Continuar</a>
        </div>
    @endif

    {{-- Últimos 3 clientes con pedido: se eligen de un toque, sin buscarlos. --}}
    @if (count($recientes))
        <section class="mb-4" aria-label="Clientes recientes">
            <p class="section-label">Recientes</p>
            <ul class="group-list">
                @foreach ($recientes as $r)
                    <li>
                        <form method="POST" action="{{ route('clientes.seleccionar') }}">
                            @csrf
                            <input type="hidden" name="codigo" value="{{ $r['codigo'] }}">
                            <button class="pressable active:bg-mist flex min-h-14 w-full items-center gap-3 px-4 py-3 text-left">
                                <span class="num text-royal shrink-0 text-[17px] font-extrabold">{{ $r['codigo'] }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="text-ink block truncate font-semibold">{{ $r['nombre'] }}</span>
                                    <span class="text-label-2 block text-[12px]">Último pedido {{ $r['fecha']->format('d/m/Y H:i') }}</span>
                                </span>
                                <svg viewBox="0 0 24 24" class="text-label-3 size-5 shrink-0" fill="currentColor" aria-hidden="true"><path d="M8.3 4.3a1 1 0 0 1 1.4 0l7 7a1 1 0 0 1 0 1.4l-7 7a1 1 0 0 1-1.4-1.4l6.29-6.3-6.3-6.3a1 1 0 0 1 0-1.4Z"/></svg>
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <form role="search" action="{{ route('clientes.index') }}" data-search
          data-endpoint="{{ route('clientes.buscar') }}"
          class="bg-royal sticky z-20 -mx-4 px-4 pt-1 pb-4" style="top: calc(env(safe-area-inset-top) + 60px)">
        <label class="relative block">
            <span class="sr-only">Buscar cliente por código, nombre o RUC</span>
            <svg viewBox="0 0 24 24" class="text-royal pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2" fill="currentColor" aria-hidden="true"><path d="M10.5 3a7.5 7.5 0 0 1 5.96 12.05l4.25 4.24a1 1 0 0 1-1.42 1.42l-4.24-4.25A7.5 7.5 0 1 1 10.5 3Zm0 2a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11Z"/></svg>
            <input type="search" name="q" value="{{ request('q') }}" autocomplete="off" autocapitalize="characters"
                   enterkeyhint="search" spellcheck="false" @unless ($actual) autofocus @endunless
                   placeholder="Código, nombre o RUC"
                   class="text-ink placeholder:text-ink-soft focus:shadow-[0_0_0_3px_var(--color-cyan)] h-12 w-full rounded-[14px] bg-white pr-11 pl-11 font-medium shadow-[0_16px_36px_-18px_rgb(9_16_56/0.7)] outline-none [&::-webkit-search-cancel-button]:hidden">
            <button type="button" data-clear hidden aria-label="Borrar búsqueda"
                    class="pressable text-label-3 absolute top-1/2 right-1 grid size-10 -translate-y-1/2 place-items-center">
                <svg viewBox="0 0 24 24" class="size-5" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 1 1 0 20 10 10 0 0 1 0-20Zm3.3 5.3L12 10.6 8.7 7.3a1 1 0 0 0-1.4 1.4l3.3 3.3-3.3 3.3a1 1 0 1 0 1.4 1.4l3.3-3.3 3.3 3.3a1 1 0 0 0 1.4-1.4L13.4 12l3.3-3.3a1 1 0 0 0-1.4-1.4Z"/></svg>
            </button>
        </label>
    </form>

    <div data-results aria-live="polite" aria-busy="false">
        @include('clientes._vacio')
    </div>
</x-layouts.app>
