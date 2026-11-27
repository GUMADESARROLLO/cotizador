<x-layouts.app title="Clientes" :sub="'Cartera de '.config('clientes.vendedor_nombre').' ('.config('clientes.vendedor').') con crédito, saldo y contacto'" section="clientes" :resumen="$resumen">
    @if ($actual)
        <x-slot:actions>
            <a href="{{ route('articulos.index') }}" class="btn btn-solid">Continuar con {{ $actual->nombre }}</a>
        </x-slot:actions>
    @endif

    {{-- Un solo formulario para toda la pantalla: cada fila y cada reciente traen un botón con form="form-elegir-cliente". --}}
    <form id="form-elegir-cliente" method="POST" action="{{ route('clientes.seleccionar') }}" hidden>@csrf</form>

    {{-- Últimos 3 clientes con pedido: se eligen de un toque, sin buscarlos. --}}
    @if (count($recientes))
        <section class="cli-recientes" aria-label="Clientes recientes">
            <span class="cli-recientes-t">Recientes</span>
            @foreach ($recientes as $r)
                <button type="submit" form="form-elegir-cliente" name="codigo" value="{{ $r['codigo'] }}" class="chip cli-reciente"
                        title="Último pedido {{ $r['fecha']->format('d/m/Y H:i') }}">
                    <span class="num">{{ $r['codigo'] }}</span>
                    <span class="cli-reciente-n">{{ $r['nombre'] }}</span>
                    <span class="cli-reciente-f">Último pedido {{ $r['fecha']->format('d/m/Y H:i') }}</span>
                </button>
            @endforeach
        </section>
    @endif

    <div class="card" data-listado data-endpoint="{{ route('clientes.buscar') }}">
        <form class="toolbar" role="search" action="{{ route('clientes.index') }}">
            <div class="seg" role="group" aria-label="Segmento de clientes">
                @foreach (['todos' => 'Todos', 'morosos' => 'Morosos', 'saldo' => 'Con saldo'] as $clave => $texto)
                    <button type="button" class="seg-btn {{ $listado['f'] === $clave ? 'on' : '' }}" data-filtro="{{ $clave }}" aria-pressed="{{ $listado['f'] === $clave ? 'true' : 'false' }}">{{ $texto }} <span class="seg-n" data-seg-n="{{ $clave }}">{{ number_format($listado['contadores'][$clave] ?? 0) }}</span></button>
                @endforeach
            </div>

            <label class="search-box">
                <span class="sr-only">Buscar cliente, código o RUC</span>
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input type="search" name="q" value="{{ $listado['q'] }}" autocomplete="off" spellcheck="false" enterkeyhint="search"
                       @unless ($actual) autofocus @endunless placeholder="Buscar cliente, código o RUC…">
                <button type="button" class="search-clear" data-clear @if ($listado['q'] === '') hidden @endif aria-label="Borrar búsqueda">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </label>

            {{-- La opción "selected" es el valor por defecto de initListado(); el valor de la URL lo aplica el JS. --}}
            <label class="select-sm toolbar-grow">
                <span class="sr-only">Filas por página</span>
                <select name="per">
                    @foreach ([10, 25, 50] as $n)
                        <option value="{{ $n }}" @if ($n === 25) selected @endif>{{ $n }}</option>
                    @endforeach
                </select>
            </label>
        </form>

        <div data-results aria-live="polite" aria-busy="false">
            @include('clientes._resultados', $listado + ['actual' => $actual?->codigo])
        </div>
    </div>
</x-layouts.app>
