@php
    $hayFiltros = request('q') || $filtros['desde'] || $filtros['hasta'];
    $chev = '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M8.3 4.3a1 1 0 0 1 1.4 0l7 7a1 1 0 0 1 0 1.4l-7 7a1 1 0 0 1-1.4-1.4l6.29-6.3-6.3-6.3a1 1 0 0 1 0-1.4Z"/></svg>';
    $iconoFecha = '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M4.3 8.3a1 1 0 0 1 1.4 0l6.3 6.29 6.3-6.3a1 1 0 1 1 1.4 1.42l-7 7a1 1 0 0 1-1.4 0l-7-7a1 1 0 0 1 0-1.42Z"/></svg>';
    // Paginador: primera, última y una ventana alrededor de la actual.
    $ultima = $pedidos->lastPage();
    $actual = $pedidos->currentPage();
    $paginas = collect(range(1, $ultima))->filter(fn ($n) => $n === 1 || $n === $ultima || abs($n - $actual) <= 2)->values();
@endphp
<x-layouts.app title="Historial" sub="Pedidos confirmados" section="historial">
    <section class="card ped-block" data-historial>
        <form role="search" class="toolbar" data-filtro-form data-hoy="{{ today()->format('Y-m-d') }}">
            {{-- Rango de fechas de creación: calendario propio (no el input date del navegador). --}}
            <input type="hidden" name="desde" value="{{ $filtros['desde'] }}">
            <input type="hidden" name="hasta" value="{{ $filtros['hasta'] }}">
            {{-- Al filtrar se conserva el orden elegido en los encabezados. --}}
            @if ($orden)
                <input type="hidden" name="o" value="{{ $orden->campo }}">
                <input type="hidden" name="d" value="{{ $orden->dir() }}">
            @endif
            <button type="button" data-filtro-fecha="desde" aria-haspopup="dialog" class="hist-fecha {{ $filtros['desde'] ? '' : 'is-vacio' }}">
                <small>Desde</small><b>{{ $filtros['desde'] ? fecha_corta($filtros['desde']) : 'Cualquiera' }}</b>{!! $iconoFecha !!}
            </button>
            <button type="button" data-filtro-fecha="hasta" aria-haspopup="dialog" class="hist-fecha {{ $filtros['hasta'] ? '' : 'is-vacio' }}">
                <small>Hasta</small><b>{{ $filtros['hasta'] ? fecha_corta($filtros['hasta']) : 'Cualquiera' }}</b>{!! $iconoFecha !!}
            </button>
            <label class="search-box">
                <span class="sr-only">Buscar por número de pedido Softland o cliente</span>
                <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M10.5 3a7.5 7.5 0 0 1 5.96 12.05l4.25 4.24a1 1 0 0 1-1.42 1.42l-4.24-4.25A7.5 7.5 0 1 1 10.5 3Zm0 2a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11Z"/></svg>
                <input type="search" name="q" value="{{ request('q') }}" placeholder="N.º Softland, cliente o código" enterkeyhint="search">
            </label>
            <button class="btn btn-line">Buscar</button>
        </form>

        @if ($filtros['desde'] || $filtros['hasta'])
            <p class="hist-rango">
                <span>{{ $pedidos->total() }} pedido(s) en el rango</span>
                <a href="{{ route('pedidos.index', array_filter(['q' => request('q')])) }}">Quitar fechas</a>
            </p>
        @endif

        @if ($pedidos->isEmpty())
            <div class="empty">
                <strong>{{ $hayFiltros ? 'Sin coincidencias' : 'Aún no hay pedidos' }}</strong>
                Los pedidos confirmados aparecerán aquí.
            </div>
        @else
            <div class="tbl-box">
                <table class="tbl">
                    <thead>
                        <tr>
                            <x-th-orden campo="pedido" :orden="$orden">Pedido</x-th-orden>
                            <x-th-orden campo="cliente" :orden="$orden">Cliente</x-th-orden>
                            <x-th-orden campo="fecha" :orden="$orden">Fecha</x-th-orden>
                            <x-th-orden campo="articulos" :orden="$orden" class="th-num">Artículos</x-th-orden>
                            <x-th-orden campo="softland" :orden="$orden">Softland</x-th-orden>
                            <x-th-orden campo="total" :orden="$orden" class="th-num">Total</x-th-orden>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pedidos as $p)
                            <tr tabindex="0" data-href="{{ route('pedidos.show', $p) }}" style="cursor: pointer">
                                <td style="white-space: nowrap">
                                    <a href="{{ route('pedidos.show', $p) }}" style="font-weight: 650; color: var(--color-royal)">
                                        @if ($p->softland_pedido)<span class="num" style="font-size: 16px">{{ $p->numero() }}</span>@else<span class="tag tag-warn">Sin enviar</span>@endif
                                    </a>
                                </td>
                                <td>
                                    <span style="font-weight: 650">{{ $p->cliente }}</span>
                                    @if ($p->cliente_codigo)<span class="td-sub">{{ $p->cliente_codigo }}</span>@endif
                                </td>
                                <td style="white-space: nowrap">{{ ($p->fecha_pedido ?? $p->created_at)->format('d/m/Y') }}</td>
                                <td class="td-num">{{ $p->lineas }}</td>
                                <td>
                                    @if ($p->enviadoASoftland())
                                        <span class="tag tag-ok">Softland <span class="num" style="margin-left: 4px">{{ $p->softland_pedido }}</span></span>
                                    @elseif ($p->softland_estado === \App\Models\Pedido::SOFTLAND_ERROR)
                                        <span class="tag tag-bad">Error al enviar</span>
                                    @else
                                        <span class="tag tag-neutral">Sin enviar a Softland</span>
                                    @endif
                                </td>
                                <td class="td-num td-total">{{ dinero($p->total, $p->moneda ?? 'L') }}</td>
                                <td class="td-chev" aria-hidden="true">{!! $chev !!}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="tfoot">
                <span>Mostrando {{ number_format($pedidos->firstItem()) }}–{{ number_format($pedidos->lastItem()) }} de {{ number_format($pedidos->total()) }} pedidos</span>
                @if ($ultima > 1)
                    <nav class="pager" aria-label="Paginación">
                        @if ($pedidos->onFirstPage())
                            <span class="pager-btn" style="opacity: .45" aria-disabled="true">‹</span>
                        @else
                            <a class="pager-btn" href="{{ $pedidos->previousPageUrl() }}" rel="prev" aria-label="Anterior">‹</a>
                        @endif
                        @foreach ($paginas as $k => $n)
                            @if ($k > 0 && $n - $paginas[$k - 1] > 1)<span class="pager-gap">…</span>@endif
                            <a class="pager-btn {{ $n === $actual ? 'on' : '' }}" href="{{ $pedidos->url($n) }}" @if ($n === $actual) aria-current="page" @endif>{{ $n }}</a>
                        @endforeach
                        @if ($pedidos->hasMorePages())
                            <a class="pager-btn" href="{{ $pedidos->nextPageUrl() }}" rel="next" aria-label="Siguiente">›</a>
                        @else
                            <span class="pager-btn" style="opacity: .45" aria-disabled="true">›</span>
                        @endif
                    </nav>
                @endif
            </div>
        @endif
    </section>

    <x-calendario marca="data-filtro-sheet" titulo="Creados desde" :quitar="true" />
</x-layouts.app>
