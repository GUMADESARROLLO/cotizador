@props(['datos' => [], 'fecha' => null, 'modo' => 'completo', 'cargado' => true])
{{--
    Categoría Reorder Point (GUMA@NET) y meses de inventario del artículo.
    $modo: "categoria" (solo el badge), "meses" (solo el número) o "completo" (ambos).
    $cargado false = no hay mapa cargado: no se muestra nada. $datos [] = el artículo no está en la foto: "—".
--}}
@php
    $categoria = $datos['categoria'] ?? null;
    $meses = $datos['meses'] ?? null;
    $estilo = match (substr((string) $categoria, 0, 1)) {
        'A' => 'tag-info',
        'B' => 'tag-ok',
        default => 'tag-neutral',
    };
    $titulo = 'Categoría Reorder Point'.($fecha ? ' (foto del '.\Illuminate\Support\Carbon::parse($fecha)->format('d/m/Y').')' : '')
        .' · Meses de inventario = disponible 002+005+006 / promedio mensual privado';
@endphp
@if ($cargado)
    @if ($modo !== 'meses')
        <span title="{{ $titulo }}">
            @if ($categoria)
                <span class="tag {{ $estilo }}"><span class="sr-only">Categoría </span>{{ $categoria }}</span>
            @else
                <span class="text-ink-soft">Cat. —</span>
            @endif
        </span>
    @endif
    @if ($modo !== 'categoria')
        @if ($categoria && $meses !== null)
            <span class="{{ $meses < 1 ? 'text-danger' : '' }}" title="{{ $titulo }}">{{ number_format($meses, 1) }}<span class="td-sub">{{ $meses == 1.0 ? 'mes' : 'meses' }}</span></span>
        @else
            <span class="text-ink-soft" title="{{ $titulo }}">— <span class="sr-only">meses</span></span>
        @endif
    @endif
@endif
