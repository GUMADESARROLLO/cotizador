@php
    /** @var \App\Clientes\Cliente $cliente */
    $total ??= null;
    $cambiar ??= true;
    $palabras = preg_split('/\s+/', trim($cliente->nombre), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (($palabras[0] ?? '') !== '' && str_starts_with(mb_strtoupper($palabras[0]), 'FARM') && count($palabras) > 1) {
        array_shift($palabras);
    }
    $iniciales = mb_strtoupper(mb_substr($palabras[0] ?? '', 0, 1).mb_substr($palabras[1] ?? '', 0, 1));
@endphp
{{-- Cliente del pedido (en curso o guardado). El aviso de crédito lo muestra u oculta el JS al cambiar el total. --}}
<section class="card" aria-label="Cliente del pedido">
    <div class="ped-cliente">
        <span class="avatar" aria-hidden="true">{{ $iniciales }}<span class="avatar-dot {{ $cliente->moroso ? 'is-bad' : 'is-ok' }}"></span></span>
        <div class="ped-cliente-body">
            <p class="eyebrow">Cliente del pedido</p>
            <p class="ped-cliente-nombre">{{ $cliente->nombre }}</p>
            <div class="ped-meta">
                <span class="num">{{ $cliente->codigo }}</span>
                @if ($cliente->ruc)
                    <span>RUC <span class="num">{{ $cliente->ruc }}</span></span>
                @endif
                @if ($cliente->moroso)
                    <span class="tag tag-bad">Moroso</span>
                @endif
            </div>
            <div class="ped-meta">
                <span>Disponible <span class="num" @if ($cliente->disponible <= 0) style="color: var(--color-danger)" @endif>{{ dinero($cliente->disponible, $cliente->moneda) }}</span></span>
                @if ($cliente->condicionPago)
                    <span>Condición <span class="num">{{ $cliente->condicionPago }}</span></span>
                @endif
            </div>
            @if ($cliente->nivelPrecio)
                <div class="ped-meta"><span class="tag tag-info">Precios {{ $cliente->nivelPrecio }}</span></div>
            @endif
        </div>
        @if ($cambiar)
            <a href="{{ route('clientes.index') }}" class="btn btn-line" style="min-height: 32px; padding: 0 10px; font-size: 13px">Cambiar</a>
        @endif
    </div>
    @if ($total !== null && $cliente->creditoComparable())
        <p data-credit-warning data-disponible="{{ $cliente->disponible }}" @if ($total <= $cliente->disponible) hidden @endif
           class="ped-cliente-aviso">
            ⚠ El total supera el crédito disponible del cliente.
        </p>
    @endif
</section>
