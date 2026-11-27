@php
    use App\Pedidos\Carrito;
    use App\Pedidos\Linea;
    use Illuminate\Support\Carbon;

    /** @var array $i  artículo del carrito */
    $lotesArt ??= [];
    $ajustado ??= false;
    $imagenes ??= [];
    $imagen = $imagenes[$i['codigo']] ?? null;
    $loteSel = collect($lotesArt)->firstWhere('lote', $i['lote'] ?? null) ?? ($lotesArt[0] ?? null);
    $empaque = Linea::tieneEmpaque($i);
    $sinPrecio = Linea::sinPrecio($i);
    $caja = Linea::unidadCaja($i);
    $interna = Linea::unidadInterna($i);
    $vence = fn ($f) => $f ? Carbon::parse($f)->format('d/m/Y') : 's/f';
    // Se puede pedir más de lo disponible (o agotado): se completa con traslados. Solo se avisa.
    $excede = Carrito::excede($i, $loteSel) > 0;
    $disponible = $loteSel ? $loteSel->disponible : (float) $i['existencia'];
    $iniciales = mb_strtoupper(mb_substr(trim($i['descripcion']), 0, 2));

    // Lo que necesita el panel de edición.
    $datos = [
        'codigo' => $i['codigo'],
        'descripcion' => $i['descripcion'],
        'factor' => (float) ($i['factor'] ?? 1),
        'unidad' => $caja,
        'unidad_empaque' => $interna,
        'modo' => $i['modo'] ?? Carrito::CAJA,
        'cajas' => (float) $i['cantidad'],
        'unidades' => (float) ($i['unidades'] ?? $i['cantidad']),
        'precio' => (float) $i['precio'],
        'precio_nivel' => (float) ($i['precio_nivel'] ?? $i['precio']),
        'manual' => ! empty($i['precio_manual']),
        'existencia' => (float) $i['existencia'],
        'lote' => $loteSel?->lote,
        'lotes' => array_map(fn ($l) => ['lote' => $l->lote, 'vence' => $l->vence, 'disponible' => $l->disponible], $lotesArt),
    ];
@endphp
{{-- Toda la fila abre el panel de edición (cantidad, precio y lote); lo atiende app.js por delegación. --}}
<tr data-cart-row="{{ $i['codigo'] }}" data-editar-articulo data-articulo="{{ json_encode($datos) }}"
    tabindex="0" role="button" aria-haspopup="dialog" aria-label="Editar {{ $i['descripcion'] }}"
    class="{{ $ajustado ? 'is-ajustado' : '' }}">
    <td data-v="{{ $i['descripcion'] }}">
        <div class="ped-art">
            <span class="thumb" aria-hidden="true">{{ $iniciales }}@if ($imagen)<img src="{{ $imagen }}" alt="" width="44" height="44" loading="lazy" decoding="async" onerror="this.remove()">@endif</span>
            <span class="min-w-0">
                <span class="ped-art-desc">{{ $i['descripcion'] }}</span>
                <span class="ped-art-sub">
                    <span class="num text-[15px] font-bold">{{ $i['codigo'] }}</span>
                    @if ($loteSel)
                        · Lote <span class="num text-ink text-[15px] font-bold">{{ $loteSel->lote }}</span>
                        · vence <span class="num text-[15px] font-bold">{{ $vence($loteSel->vence) }}</span>
                    @endif
                </span>
                @if ($excede)
                    <span class="ped-warn">
                        {{ $disponible > 0 ? 'Excede lo disponible ('.cantidad(\App\Catalogo\Cantidades::bajar4($disponible)).' '.$caja.')' : 'Sin existencia en bodega '.config('softland.bodega') }} · se pedirá igual
                    </span>
                @endif
                @if ($ajustado)
                    <span class="ped-warn">Cambió la existencia, el lote o el precio.</span>
                @endif
            </span>
        </div>
    </td>
    {{-- Cantidad en cajas; las unidades, de referencia. --}}
    <td class="td-num">
        {{ cantidad($i['cantidad']) }} <span class="text-[15px]">{{ $caja }}</span>
        @if ($empaque)
            <span class="td-sub">/ {{ cantidad($i['unidades'] ?? 0) }} {{ $interna }}</span>
        @endif
    </td>
    {{-- Precio por caja · por unidad --}}
    <td class="td-num">
        @if ($sinPrecio)
            <span class="tag tag-warn">Falta precio</span>
        @else
            {{ dinero($i['precio']) }}
            @if ($empaque)
                <span class="td-sub">{{ dinero(Linea::precioUnidad($i)) }} / {{ $interna }}</span>
            @endif
        @endif
    </td>
    <td class="td-num td-total">{{ dinero(Carrito::subtotal($i)) }}</td>
    <td class="td-chev" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M8.3 4.3a1 1 0 0 1 1.4 0l7 7a1 1 0 0 1 0 1.4l-7 7a1 1 0 0 1-1.4-1.4l6.29-6.3-6.3-6.3a1 1 0 0 1 0-1.4Z"/></svg>
    </td>
</tr>
