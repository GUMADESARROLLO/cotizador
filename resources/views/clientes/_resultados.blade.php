@php
    $desde = $total ? ($pagina - 1) * $per + 1 : 0;
    $hasta = $desde ? $desde + count($clientes) - 1 : 0;
    $ini = max(1, min($pagina - 2, $paginas - 4));
    $ventana = range($ini, min($paginas, $ini + 4));
@endphp
@if ($contadores)
    <span data-contadores data-todos="{{ $contadores['todos'] }}" data-morosos="{{ $contadores['morosos'] }}" data-saldo="{{ $contadores['saldo'] }}" hidden></span>
@endif

@if ($error)
    <div class="empty">
        <strong>Sin conexión a clientes</strong>
        No se pudo consultar SQL Server. Intenta de nuevo en un momento.
    </div>
@elseif (! count($clientes))
    <div class="empty">
        <strong>Sin resultados</strong>
        @if ($q !== '')
            No hay clientes que coincidan con “{{ $q }}”@if ($f !== 'todos') en este segmento @endif.
        @else
            No hay clientes en este segmento.
        @endif
    </div>
@else
    <div class="tbl-box">
        <table class="tbl tbl-clientes">
            <thead>
                <tr>
                    <x-th-orden campo="cliente" :orden="$orden ?? null">Cliente</x-th-orden>
                    <x-th-orden campo="nivel" :orden="$orden ?? null">Nivel de precio</x-th-orden>
                    <x-th-orden campo="limite" :orden="$orden ?? null" class="th-num">Límite</x-th-orden>
                    <x-th-orden campo="saldo" :orden="$orden ?? null" class="th-num">Saldo</x-th-orden>
                    <x-th-orden campo="disponible" :orden="$orden ?? null" class="th-num">Disponible</x-th-orden>
                    <x-th-orden campo="telefonos" :orden="$orden ?? null">Teléfonos</x-th-orden>
                    <th scope="col"><span class="sr-only">Elegir</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($clientes as $c)
                    <tr @class(['row-bad' => $c->moroso, 'is-actual' => $actual === $c->codigo])>
                        <td class="cli-cell">
                            {{-- Único control de la fila: un botón real (teclado nativo); el click en cualquier parte de la fila lo activa (clientes.js). --}}
                            <button type="submit" form="form-elegir-cliente" name="codigo" value="{{ $c->codigo }}" class="cli-pick">
                                <span class="cli-name"><span class="num cli-code">{{ $c->codigo }}</span> · {{ $c->nombre }}</span>
                                @if ($c->moroso)<span class="tag tag-bad">Moroso</span>@endif
                                @if ($actual === $c->codigo)<span class="tag tag-info">Actual</span>@endif
                            </button>
                            @if ($c->ruc)<span class="td-sub">RUC {{ $c->ruc }}</span>@endif
                            @if ($c->direccion)<span class="td-sub cli-dir" title="{{ $c->direccion }}">{{ $c->direccion }}</span>@endif
                        </td>
                        <td>@if ($c->nivelPrecio)<span class="tag tag-neutral">{{ $c->nivelPrecio }}</span>@else<span class="td-sub">—</span>@endif</td>
                        <td class="td-num">{{ dinero($c->credito, $c->moneda) }}</td>
                        <td class="td-num {{ $c->saldo > 0 ? 'cli-bad' : '' }}">{{ dinero($c->saldo, $c->moneda) }}</td>
                        <td class="td-num {{ $c->disponible > 0 ? 'cli-ok' : 'cli-sin' }}">
                            {{ dinero($c->disponible, $c->moneda) }}
                            @unless ($c->disponible > 0)<span class="td-sub">Sin crédito disponible</span>@endunless
                        </td>
                        <td>
                            @if ($c->telefono1 || $c->telefono2)
                                <span class="cli-tel">{{ $c->telefono1 ?: '—' }}</span>
                                <span class="td-sub">{{ $c->telefono2 ?: '—' }}</span>
                            @else
                                <span class="td-sub">—</span>
                            @endif
                        </td>
                        <td class="cli-go" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="tfoot">
        <span>Mostrando {{ number_format($desde) }}–{{ number_format($hasta) }} de {{ number_format($total) }} clientes · Elige un cliente para empezar su pedido</span>
        @if ($paginas > 1)
            <nav class="pager" aria-label="Paginación">
                <button type="button" class="pager-btn" data-page="{{ $pagina - 1 }}" @disabled($pagina <= 1) aria-label="Página anterior">‹</button>
                @if ($ventana[0] > 1)
                    <button type="button" class="pager-btn" data-page="1">1</button>
                    @if ($ventana[0] > 2)<span class="pager-btn" aria-hidden="true">…</span>@endif
                @endif
                @foreach ($ventana as $n)
                    <button type="button" @class(['pager-btn', 'on' => $n === $pagina]) data-page="{{ $n }}" @if ($n === $pagina) aria-current="page" @endif>{{ $n }}</button>
                @endforeach
                @if (end($ventana) < $paginas)
                    @if (end($ventana) < $paginas - 1)<span class="pager-btn" aria-hidden="true">…</span>@endif
                    <button type="button" class="pager-btn" data-page="{{ $paginas }}">{{ $paginas }}</button>
                @endif
                <button type="button" class="pager-btn" data-page="{{ $pagina + 1 }}" @disabled($pagina >= $paginas) aria-label="Página siguiente">›</button>
            </nav>
        @endif
    </div>
@endif
