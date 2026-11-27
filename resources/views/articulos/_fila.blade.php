@php
    // En cajas por defecto (con fracción); si ya está en el pedido, en el modo en que se capturó.
    $modo = $a->tieneEmpaque() ? ($item['modo'] ?? 'caja') : 'caja';
    $enUnidades = $modo === 'unidad';
    $iniciales = mb_strtoupper(collect(preg_split('/\s+/', trim($a->descripcion), -1, PREG_SPLIT_NO_EMPTY))->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode(''));
@endphp
<tr @class(['row-off' => ! $a->disponible()])>
    <td>
        <div class="art">
            {{-- Miniatura: caja fija (sin saltos de diseño); las iniciales quedan debajo si no hay foto o mientras carga. --}}
            <div class="thumb">
                <span aria-hidden="true">{{ $iniciales }}</span>
                @if ($imagen)
                    <img src="{{ $imagen }}" alt="Foto de {{ $a->descripcion }}" width="80" height="80"
                         loading="{{ $prioridad ? 'eager' : 'lazy' }}" decoding="async"
                         onerror="this.remove()">
                @endif
            </div>
            <div class="art-txt">
                <span class="art-nombre">{{ mb_strtoupper($a->descripcion) }}</span>
                <span class="td-sub"><span class="num">{{ $a->codigo }}</span>@if ($a->unidad) · {{ $a->unidad }}@endif</span>
            </div>
        </div>
    </td>
    <td>
        <x-reorden modo="categoria" :cargado="$reorden !== null" :datos="$reorden ?? []" :fecha="$reordenFecha ?? null" />
    </td>
    <td class="td-num">
        <x-reorden modo="meses" :cargado="$reorden !== null" :datos="$reorden ?? []" :fecha="$reordenFecha ?? null" />
    </td>
    <td class="td-num">
        <x-existencia :existencia="$a->existencia" :unidad="$a->unidad" :factor="$a->factor" :unidad-empaque="$a->unidadEmpaque" />
    </td>
    <td class="td-num">
        @if ($a->precio > 0)
            {{ dinero($a->precio) }}
            @if ($a->tieneEmpaque())
                <span class="td-sub">/ {{ $a->unidad }} · {{ dinero($a->precio / $a->factor) }} / {{ $a->unidadEmpaque ?: 'UND' }}</span>
                <span class="td-sub">{{ cantidad($a->factor) }} por {{ $a->unidad }}</span>
            @elseif ($a->unidad)
                <span class="td-sub">/ {{ $a->unidad }}</span>
            @endif
        @else
            <span class="tag tag-warn">Sin precio</span>
            <span class="td-sub">Se pone en el pedido</span>
        @endif
    </td>
    <td class="td-pedido">
        <x-stepper :codigo="$a->codigo" :modo="$modo"
                   :cantidad="$item ? \App\Pedidos\Carrito::capturada($item) : 0"
                   :max="$enUnidades ? $a->maximoUnidades() : $a->maximoPedible()"
                   :etiqueta="$a->tieneEmpaque() ? ($enUnidades ? ($a->unidadEmpaque ?: 'UND') : $a->unidad) : null" />
    </td>
</tr>
