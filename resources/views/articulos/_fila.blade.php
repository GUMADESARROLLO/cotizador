<li class="flex flex-col gap-3 px-4 py-3.5 sm:flex-row sm:items-center sm:gap-4">
    <div class="flex min-w-0 flex-1 gap-3">
        {{-- Miniatura: caja fija (sin saltos de diseño); el ícono queda debajo mientras carga o si no hay foto. --}}
        <div class="bg-mist text-label-3 ring-line relative grid size-16 shrink-0 place-items-center overflow-hidden rounded-[14px] ring-1 sm:size-20">
            <svg viewBox="0 0 24 24" class="size-7" fill="currentColor" aria-hidden="true"><path d="M16.24 3.56a5.5 5.5 0 0 1 7.78 7.78l-.06.06-8.6 8.6a5.5 5.5 0 0 1-7.84-7.72l.06-.06 8.6-8.6.06-.06Zm-7.25 8.66-.96.96a3.5 3.5 0 0 0 4.86 5.03l.09-.08.96-.96-4.95-4.95Zm1.41-1.41 4.95 4.95 6.24-6.24a3.5 3.5 0 0 0-4.86-5.03l-.09.08-6.24 6.24Z"/></svg>
            @if ($imagen)
                <img src="{{ $imagen }}" alt="" width="80" height="80"
                     loading="{{ $prioridad ? 'eager' : 'lazy' }}" decoding="async" @if ($prioridad) fetchpriority="high" @endif
                     onload="this.classList.add('is-loaded')" onerror="this.remove()"
                     class="product-thumb absolute inset-0 size-full bg-white object-contain p-1">
            @endif
        </div>
        <div class="min-w-0 flex-1">
            <p class="text-body leading-snug font-semibold">{{ $a->descripcion }}</p>
            <p class="text-foot text-label-2 mt-1 flex flex-wrap items-center gap-x-2 gap-y-1">
                <span class="num text-[15px] font-bold">{{ $a->codigo }}</span>
                <span aria-hidden="true">·</span>
                <x-existencia :existencia="$a->existencia" :unidad="$a->unidad" :factor="$a->factor" :unidad-empaque="$a->unidadEmpaque" />
            </p>
        </div>
    </div>

    @php
        // En cajas por defecto (con fracción); si ya está en el pedido, en el modo en que se capturó.
        $modo = $a->tieneEmpaque() ? ($item['modo'] ?? 'caja') : 'caja';
        $enUnidades = $modo === 'unidad';
    @endphp
    <div class="flex items-center justify-between gap-3 sm:justify-end">
        <p class="leading-tight">
            @if ($a->precio > 0)
                @if ($a->tieneEmpaque())
                    <span class="price">{{ dinero($a->precio) }}</span><span class="text-foot text-label-2"> / {{ $a->unidad }}</span>
                    <span class="text-foot text-label-2 block">{{ dinero($a->precio / $a->factor) }} / {{ $a->unidadEmpaque ?: 'UND' }} · {{ cantidad($a->factor) }} por {{ $a->unidad }}</span>
                @else
                    <span class="price">{{ dinero($a->precio) }}</span>
                    @if ($a->unidad)<span class="text-foot text-label-2">/ {{ $a->unidad }}</span>@endif
                @endif
            @else
                <span class="badge bg-warn-soft text-warn">Sin precio</span>
                <span class="text-foot text-label-2 block pt-1">Se pone en el pedido</span>
            @endif
        </p>
        <x-stepper :codigo="$a->codigo" :modo="$modo"
                   :cantidad="$item ? \App\Pedidos\Carrito::capturada($item) : 0"
                   :max="$enUnidades ? $a->maximoUnidades() : $a->maximoPedible()"
                   :etiqueta="$a->tieneEmpaque() ? ($enUnidades ? ($a->unidadEmpaque ?: 'UND') : $a->unidad) : null" />
    </div>
</li>
