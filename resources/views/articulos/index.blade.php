@php
    $nivel = config('articulos.precio.nivel');
@endphp
<x-layouts.app title="Artículos" :sub="'Precio '.$nivel.' y existencia en línea'" section="articulos" :resumen="$resumen">
    <x-slot:actions>
        <a href="{{ route('carrito.index') }}" class="btn btn-solid">
            Ver pedido
            <span class="num"><span data-cart-lineas>{{ $resumen['lineas'] }}</span> art. · <span data-cart-total>{{ dinero($resumen['total']) }}</span></span>
        </a>
    </x-slot:actions>

    <section class="card" data-listado data-endpoint="{{ route('articulos.buscar') }}" aria-label="Catálogo de artículos">
        <div class="toolbar">
            <div class="chips" role="group" aria-label="Filtrar artículos">
                <button type="button" class="chip @if ($lista['filtro'] === 'existencia') on @endif" data-filtro="existencia" aria-pressed="{{ $lista['filtro'] === 'existencia' ? 'true' : 'false' }}">Con existencia</button>
                <button type="button" class="chip @if ($lista['filtro'] === 'todos') on @endif" data-filtro="todos" aria-pressed="{{ $lista['filtro'] === 'todos' ? 'true' : 'false' }}">Todos</button>
                @if ($prioridadDisponible)
                    <button type="button" class="chip @if ($lista['filtro'] === 'prioridad') on @endif" data-filtro="prioridad" aria-pressed="{{ $lista['filtro'] === 'prioridad' ? 'true' : 'false' }}">Prioridad A</button>
                @endif
            </div>
            <form role="search" action="{{ route('articulos.index') }}" class="search-box">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
                <label class="sr-only" for="buscar-articulo">Buscar artículo por código o descripción</label>
                <input type="search" id="buscar-articulo" name="q" value="{{ $lista['q'] }}" autocomplete="off" autocapitalize="off"
                       enterkeyhint="search" spellcheck="false" placeholder="Buscar artículo o código…">
                <button type="button" class="search-clear" data-clear aria-label="Borrar búsqueda" @if ($lista['q'] === '') hidden @endif>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"></path></svg>
                </button>
            </form>
            <label class="select-sm toolbar-grow">
                <span class="sr-only">Filas por página</span>
                <select name="per">
                    @foreach ([10, 20, 50] as $n)
                        <option value="{{ $n }}" @if ($n === 20) selected @endif>{{ $n }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <div data-results aria-live="polite" aria-busy="false">
            @include('articulos._resultados', $lista)
        </div>
    </section>
</x-layouts.app>
