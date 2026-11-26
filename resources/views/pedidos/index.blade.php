<x-layouts.app title="Historial" slogan="Pedidos confirmados" tab="historial">
    <form role="search" class="space-y-2 pt-1 pb-4" data-filtro-form data-hoy="{{ today()->format('Y-m-d') }}">
        <label class="relative block">
            <span class="sr-only">Buscar por número de pedido Softland o cliente</span>
            <svg viewBox="0 0 24 24" class="text-royal pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2" fill="currentColor" aria-hidden="true"><path d="M10.5 3a7.5 7.5 0 0 1 5.96 12.05l4.25 4.24a1 1 0 0 1-1.42 1.42l-4.24-4.25A7.5 7.5 0 1 1 10.5 3Zm0 2a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11Z"/></svg>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="N.º Softland, cliente o código" enterkeyhint="search"
                   class="text-ink placeholder:text-ink-soft focus:shadow-[0_0_0_3px_var(--color-cyan)] h-12 w-full rounded-[14px] bg-white pr-3 pl-11 font-medium outline-none">
        </label>

        {{-- Rango de fechas de creación: calendario propio (no el input date del navegador). --}}
        <input type="hidden" name="desde" value="{{ $filtros['desde'] }}">
        <input type="hidden" name="hasta" value="{{ $filtros['hasta'] }}">
        <div class="flex items-stretch overflow-hidden rounded-[14px] bg-white">
            <button type="button" data-filtro-fecha="desde" aria-haspopup="dialog"
                    class="pressable flex min-h-14 min-w-0 flex-1 items-center gap-2 px-4 py-2 text-left">
                <span class="min-w-0 flex-1">
                    <span class="text-label-2 block text-[11px] font-extrabold tracking-[0.1em] uppercase">Desde</span>
                    <span class="num block truncate text-[19px] leading-tight font-extrabold {{ $filtros['desde'] ? 'text-ink' : 'text-label-3' }}">{{ $filtros['desde'] ? fecha_corta($filtros['desde']) : 'Cualquiera' }}</span>
                </span>
                <svg viewBox="0 0 24 24" class="text-royal size-4 shrink-0" fill="currentColor" aria-hidden="true"><path d="M4.3 8.3a1 1 0 0 1 1.4 0l6.3 6.29 6.3-6.3a1 1 0 1 1 1.4 1.42l-7 7a1 1 0 0 1-1.4 0l-7-7a1 1 0 0 1 0-1.42Z"/></svg>
            </button>
            <span class="bg-line my-2 w-px" aria-hidden="true"></span>
            <button type="button" data-filtro-fecha="hasta" aria-haspopup="dialog"
                    class="pressable flex min-h-14 min-w-0 flex-1 items-center gap-2 px-4 py-2 text-left">
                <span class="min-w-0 flex-1">
                    <span class="text-label-2 block text-[11px] font-extrabold tracking-[0.1em] uppercase">Hasta</span>
                    <span class="num block truncate text-[19px] leading-tight font-extrabold {{ $filtros['hasta'] ? 'text-ink' : 'text-label-3' }}">{{ $filtros['hasta'] ? fecha_corta($filtros['hasta']) : 'Cualquiera' }}</span>
                </span>
                <svg viewBox="0 0 24 24" class="text-royal size-4 shrink-0" fill="currentColor" aria-hidden="true"><path d="M4.3 8.3a1 1 0 0 1 1.4 0l6.3 6.29 6.3-6.3a1 1 0 1 1 1.4 1.42l-7 7a1 1 0 0 1-1.4 0l-7-7a1 1 0 0 1 0-1.42Z"/></svg>
            </button>
        </div>
        @if ($filtros['desde'] || $filtros['hasta'])
            <p class="flex items-center justify-between px-1 text-[13px] text-white">
                <span>{{ $pedidos->total() }} pedido(s) en el rango</span>
                <a href="{{ route('pedidos.index', array_filter(['q' => request('q')])) }}" class="pressable text-cyan font-extrabold tracking-[0.06em] uppercase">Quitar fechas</a>
            </p>
        @endif
    </form>

    @if ($pedidos->isEmpty())
        <div class="flex flex-col items-center px-8 pt-10 pb-6 text-center">
            <p class="text-title font-extrabold text-white">{{ request('q') || $filtros['desde'] || $filtros['hasta'] ? 'Sin coincidencias' : 'Aún no hay pedidos' }}</p>
            <p class="text-sub text-on-royal-soft mt-1 max-w-xs">Los pedidos confirmados aparecerán aquí.</p>
        </div>
    @else
        <ul class="group-list animate-card-in">
            @foreach ($pedidos as $p)
                <li>
                    <a href="{{ route('pedidos.show', $p) }}" class="pressable active:bg-mist flex items-center gap-3 px-4 py-3.5">
                        <div class="min-w-0 flex-1">
                            <p class="flex min-w-0 items-baseline gap-2">
                                @if ($p->cliente_codigo)
                                    <span class="num text-royal shrink-0 text-[16px] font-extrabold">{{ $p->cliente_codigo }}</span>
                                @endif
                                <span class="truncate font-semibold">{{ $p->cliente }}</span>
                            </p>
                            <p class="text-foot text-label-2 mt-0.5">@if ($p->softland_pedido)<span class="num text-royal text-[15px] font-extrabold">{{ $p->numero() }}</span>@else<span class="badge bg-warn-soft text-warn">Sin enviar</span>@endif · {{ ($p->fecha_pedido ?? $p->created_at)->format('d/m/Y') }} · {{ $p->lineas }} art.</p>
                            <p class="mt-1">
                                @if ($p->enviadoASoftland())
                                    <span class="badge bg-cyan-soft text-cyan-ink">Softland <span class="num text-[13px]">{{ $p->softland_pedido }}</span></span>
                                @elseif ($p->softland_estado === \App\Models\Pedido::SOFTLAND_ERROR)
                                    <span class="badge bg-danger-soft text-danger">Error al enviar</span>
                                @else
                                    <span class="badge bg-mist text-ink-soft">Sin enviar a Softland</span>
                                @endif
                            </p>
                        </div>
                        <p class="price">{{ dinero($p->total, $p->moneda ?? 'L') }}</p>
                        <svg viewBox="0 0 24 24" class="text-label-3 size-4 shrink-0" fill="currentColor" aria-hidden="true"><path d="M8.3 4.3a1 1 0 0 1 1.4 0l7 7a1 1 0 0 1 0 1.4l-7 7a1 1 0 0 1-1.4-1.4l6.29-6.3-6.3-6.3a1 1 0 0 1 0-1.4Z"/></svg>
                    </a>
                </li>
            @endforeach
        </ul>
        <div class="pt-4 [&_a]:bg-white [&_span]:bg-white/60">{{ $pedidos->links('pagination::simple-tailwind') }}</div>
    @endif

    <x-calendario marca="data-filtro-sheet" titulo="Creados desde" :quitar="true" />
</x-layouts.app>
