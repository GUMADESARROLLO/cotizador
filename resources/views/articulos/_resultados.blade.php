@if ($error)
    <div class="empty">
        <strong>Sin conexión al catálogo</strong>
        No se pudo consultar SQL Server. Intenta de nuevo en un momento.
    </div>
@elseif (! count($articulos))
    <div class="empty">
        <strong>Sin resultados</strong>
        @if ($q !== '')
            No hay artículos que coincidan con “{{ $q }}”{{ $filtro !== 'todos' ? ' con este filtro' : '' }}.
        @else
            No hay artículos con este filtro.
        @endif
    </div>
@else
    @php
        $paginas = max(1, (int) ceil($total / $porPagina));
        $desde = ($pagina - 1) * $porPagina + 1;
        $hasta = $desde + count($articulos) - 1;
        // Ventana de páginas: primera, última y ±2 alrededor de la actual.
        $visibles = collect([1, $paginas, ...range(max(1, $pagina - 2), min($paginas, $pagina + 2))])->unique()->sort()->values();
    @endphp
    <div class="tbl-box">
        <table class="tbl tbl-articulos">
            <caption class="sr-only">Artículos con categoría, meses de inventario, disponible, precio y cantidad a pedir</caption>
            <thead>
                <tr>
                    <x-th-orden campo="articulo" :orden="$orden">Artículo</x-th-orden>
                    <x-th-orden campo="categoria" :orden="$orden" title="Categoría Reorder Point">Categoría</x-th-orden>
                    <x-th-orden campo="meses" :orden="$orden" class="th-num" title="Meses de inventario = disponible 002+005+006 / promedio mensual privado">Meses inv.</x-th-orden>
                    <x-th-orden campo="existencia" :orden="$orden" class="th-num">Disponible</x-th-orden>
                    <x-th-orden campo="precio" :orden="$orden" class="th-num">Precio</x-th-orden>
                    <th scope="col" class="th-pedido">Pedido</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($articulos as $a)
                    @include('articulos._fila', ['a' => $a, 'item' => $enCarrito[$a->codigo] ?? null, 'imagen' => $imagenes[$a->codigo] ?? null, 'prioridad' => $loop->index < 4, 'reorden' => $reordenCargado ? ($reorden[$a->codigo] ?? []) : null, 'reordenFecha' => $reordenFecha])
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="tfoot">
        <span>Mostrando <strong>{{ number_format($desde) }}–{{ number_format($hasta) }}</strong> de <strong>{{ number_format($total) }}</strong> {{ $total === 1 ? 'artículo' : 'artículos' }}</span>
        @if ($paginas > 1)
            <nav class="pager" aria-label="Paginación">
                <button type="button" class="pager-btn" data-page="{{ $pagina - 1 }}" @disabled($pagina <= 1) aria-label="Página anterior">‹</button>
                @foreach ($visibles as $p)
                    @if (! $loop->first && $p - $visibles[$loop->index - 1] > 1)
                        <span class="pager-gap" aria-hidden="true">…</span>
                    @endif
                    <button type="button" class="pager-btn @if ($p === $pagina) on @endif" data-page="{{ $p }}"
                            @if ($p === $pagina) aria-current="page" @endif aria-label="Página {{ $p }}">{{ $p }}</button>
                @endforeach
                <button type="button" class="pager-btn" data-page="{{ $pagina + 1 }}" @disabled($pagina >= $paginas) aria-label="Página siguiente">›</button>
            </nav>
        @endif
    </div>
@endif
