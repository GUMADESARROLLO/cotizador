<x-layouts.app title="Buscar artículos" slogan="Precio INST.PUB. C$ y existencia en línea" tab="articulos" :resumen="$resumen">
    <x-cliente-actual :cliente="$cliente" />
    {{-- Campo de búsqueda; se queda fijo debajo de la barra al desplazar. --}}
    <form role="search" action="{{ route('articulos.index') }}" data-search
          data-endpoint="{{ route('articulos.buscar') }}"
          class="bg-royal sticky z-20 -mx-4 px-4 pt-1 pb-4" style="top: calc(env(safe-area-inset-top) + 60px)">
        <label class="relative block">
            <span class="sr-only">Buscar artículo por código o descripción</span>
            <svg viewBox="0 0 24 24" class="text-royal pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2" fill="currentColor" aria-hidden="true"><path d="M10.5 3a7.5 7.5 0 0 1 5.96 12.05l4.25 4.24a1 1 0 0 1-1.42 1.42l-4.24-4.25A7.5 7.5 0 1 1 10.5 3Zm0 2a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11Z"/></svg>
            <input type="search" name="q" value="{{ request('q') }}" autocomplete="off" autocapitalize="off"
                   enterkeyhint="search" spellcheck="false" autofocus
                   placeholder="Código o descripción"
                   class="text-ink placeholder:text-ink-soft focus:shadow-[0_0_0_3px_var(--color-cyan)] h-12 w-full rounded-[14px] bg-white pr-11 pl-11 font-medium shadow-[0_16px_36px_-18px_rgb(9_16_56/0.7)] outline-none [&::-webkit-search-cancel-button]:hidden">
            <button type="button" data-clear hidden aria-label="Borrar búsqueda"
                    class="pressable text-label-3 absolute top-1/2 right-1 grid size-10 -translate-y-1/2 place-items-center">
                <svg viewBox="0 0 24 24" class="size-5" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 1 1 0 20 10 10 0 0 1 0-20Zm3.3 5.3L12 10.6 8.7 7.3a1 1 0 0 0-1.4 1.4l3.3 3.3-3.3 3.3a1 1 0 1 0 1.4 1.4l3.3-3.3 3.3 3.3a1 1 0 0 0 1.4-1.4L13.4 12l3.3-3.3a1 1 0 0 0-1.4-1.4Z"/></svg>
            </button>
        </label>
    </form>

    <div data-results aria-live="polite" aria-busy="false">
        @include('articulos._vacio')
    </div>

    {{-- Acceso flotante al pedido cuando ya hay artículos. --}}
    <a href="{{ route('carrito.index') }}" data-cart-pill @if (! $resumen['lineas']) hidden @endif
       class="pressable no-print fixed inset-x-4 z-20 mx-auto flex max-w-md items-center justify-between rounded-2xl bg-white px-4 py-3 shadow-[0_0_0_2px_var(--color-cyan),0_24px_50px_-18px_rgb(9_16_56/0.8)]"
       style="bottom: calc(env(safe-area-inset-bottom) + 4.5rem)">
        <span class="text-ink-soft text-sub font-semibold">
            <span class="num text-royal text-xl font-extrabold" data-cart-lineas>{{ $resumen['lineas'] }}</span>
            artículo(s)
        </span>
        <span class="text-royal flex items-center gap-1">
            <span class="num text-2xl font-extrabold" data-cart-total>{{ dinero($resumen['total']) }}</span>
            <svg viewBox="0 0 24 24" class="size-5" fill="currentColor" aria-hidden="true"><path d="M8.3 4.3a1 1 0 0 1 1.4 0l7 7a1 1 0 0 1 0 1.4l-7 7a1 1 0 0 1-1.4-1.4l6.29-6.3-6.3-6.3a1 1 0 0 1 0-1.4Z"/></svg>
        </span>
    </a>
</x-layouts.app>
