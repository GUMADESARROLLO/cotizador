@php
    use App\Pedidos\Carrito;
    use App\Pedidos\Linea;
    use Illuminate\Support\Carbon;

    /** @var array $i  artículo del carrito */
    $lotesArt ??= [];
    $ajustado ??= false;
    $loteSel = collect($lotesArt)->firstWhere('lote', $i['lote'] ?? null) ?? ($lotesArt[0] ?? null);
    $empaque = Linea::tieneEmpaque($i);
    $sinPrecio = Linea::sinPrecio($i);
    $caja = Linea::unidadCaja($i);
    $interna = Linea::unidadInterna($i);
    $vence = fn ($f) => $f ? Carbon::parse($f)->format('d/m/Y') : 's/f';
    // Se puede pedir más de lo disponible (o agotado): se completa con traslados. Solo se avisa.
    $excede = Carrito::excede($i, $loteSel) > 0;
    $disponible = $loteSel ? $loteSel->disponible : (float) $i['existencia'];

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
<li data-cart-row="{{ $i['codigo'] }}" class="{{ $ajustado ? 'bg-warn-soft' : '' }}">
    <button type="button" data-editar-articulo data-articulo="{{ json_encode($datos) }}" aria-haspopup="dialog"
            class="pressable active:bg-mist flex w-full items-start gap-3 px-4 py-3.5 text-left">
        <span class="min-w-0 flex-1">
            <span class="text-body line-clamp-2 block leading-snug font-semibold">{{ $i['descripcion'] }}</span>
            <span class="text-foot text-label-2 mt-1 block">
                <span class="num text-[15px] font-bold">{{ $i['codigo'] }}</span>
                @if ($loteSel)
                    · Lote <span class="num text-ink text-[15px] font-bold">{{ $loteSel->lote }}</span>
                    · vence <span class="num text-[15px] font-bold">{{ $vence($loteSel->vence) }}</span>
                @endif
            </span>

            {{-- Cantidad y precio a la izquierda; subtotal abajo a la derecha. --}}
            <span class="mt-2.5 flex items-end justify-between gap-3">
                <span class="min-w-0">
                    {{-- Cantidad en cajas; las unidades, de referencia. --}}
                    <span class="num text-ink block text-[24px] leading-none font-extrabold">
                        {{ cantidad($i['cantidad']) }} <span class="text-[15px]">{{ $caja }}</span>
                        @if ($empaque)
                            <span class="text-label-2 text-[15px] font-bold">/ {{ cantidad($i['unidades'] ?? 0) }} {{ $interna }}</span>
                        @endif
                    </span>
                    <span class="text-foot text-label-2 mt-1.5 block">
                        @if ($sinPrecio)
                            <span class="badge bg-warn-soft text-warn ring-warn/30 ring-1">Falta precio</span>
                        @else
                            {{-- Precio por caja · por unidad --}}
                            <span class="num text-ink text-[15px] font-bold">{{ dinero($i['precio']) }}</span>
                            @if ($empaque)
                                · <span class="num text-[15px] font-bold">{{ dinero(Linea::precioUnidad($i)) }}</span>
                            @endif
                        @endif
                    </span>
                </span>
                <span class="price shrink-0 leading-none">{{ dinero(Carrito::subtotal($i)) }}</span>
            </span>

            @if ($excede)
                <span class="text-foot text-warn mt-1.5 block font-semibold">
                    {{ $disponible > 0 ? 'Excede lo disponible ('.cantidad(\App\Catalogo\Cantidades::bajar4($disponible)).' '.$caja.')' : 'Sin existencia en bodega '.config('softland.bodega') }} · se pedirá igual
                </span>
            @endif
            @if ($ajustado)
                <span class="text-foot text-warn mt-1 block font-semibold">Cambió la existencia, el lote o el precio.</span>
            @endif
        </span>
        <svg viewBox="0 0 24 24" class="text-label-3 mt-1 size-5 shrink-0" fill="currentColor" aria-hidden="true"><path d="M8.3 4.3a1 1 0 0 1 1.4 0l7 7a1 1 0 0 1 0 1.4l-7 7a1 1 0 0 1-1.4-1.4l6.29-6.3-6.3-6.3a1 1 0 0 1 0-1.4Z"/></svg>
    </button>
</li>
