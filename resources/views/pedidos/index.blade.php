<x-layouts.app title="Historial" slogan="Pedidos confirmados" tab="historial">
    <form role="search" class="pt-1 pb-4">
        <label class="relative block">
            <span class="sr-only">Buscar por folio o cliente</span>
            <svg viewBox="0 0 24 24" class="text-royal pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2" fill="currentColor" aria-hidden="true"><path d="M10.5 3a7.5 7.5 0 0 1 5.96 12.05l4.25 4.24a1 1 0 0 1-1.42 1.42l-4.24-4.25A7.5 7.5 0 1 1 10.5 3Zm0 2a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11Z"/></svg>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Folio, cliente o código" enterkeyhint="search"
                   class="text-ink placeholder:text-ink-soft focus:shadow-[0_0_0_3px_var(--color-cyan)] h-12 w-full rounded-[14px] bg-white pr-3 pl-11 font-medium outline-none">
        </label>
    </form>

    @if ($pedidos->isEmpty())
        <div class="flex flex-col items-center px-8 pt-10 pb-6 text-center">
            <p class="text-title font-extrabold text-white">{{ request('q') ? 'Sin coincidencias' : 'Aún no hay pedidos' }}</p>
            <p class="text-sub text-on-royal-soft mt-1 max-w-xs">Los pedidos confirmados aparecerán aquí.</p>
        </div>
    @else
        <ul class="group-list animate-card-in">
            @foreach ($pedidos as $p)
                <li>
                    <a href="{{ route('pedidos.show', $p) }}" class="pressable active:bg-mist flex items-center gap-3 px-4 py-3.5">
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold">{{ $p->cliente }}</p>
                            <p class="text-foot text-label-2 mt-0.5"><span class="num text-royal text-[15px] font-extrabold">{{ $p->folio }}</span> · {{ $p->created_at->format('d/m/Y H:i') }} · {{ $p->lineas }} art.</p>
                        </div>
                        <p class="price">{{ dinero($p->total) }}</p>
                        <svg viewBox="0 0 24 24" class="text-label-3 size-4 shrink-0" fill="currentColor" aria-hidden="true"><path d="M8.3 4.3a1 1 0 0 1 1.4 0l7 7a1 1 0 0 1 0 1.4l-7 7a1 1 0 0 1-1.4-1.4l6.29-6.3-6.3-6.3a1 1 0 0 1 0-1.4Z"/></svg>
                    </a>
                </li>
            @endforeach
        </ul>
        <div class="pt-4 [&_a]:bg-white [&_span]:bg-white/60">{{ $pedidos->links('pagination::simple-tailwind') }}</div>
    @endif
</x-layouts.app>
