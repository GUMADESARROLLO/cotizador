@props(['datos' => [], 'fecha' => null])
{{--
    Categoría Reorder Point (GUMA@NET) y meses de inventario del artículo.
    $datos vacío = el artículo no está en la foto de categorías: se muestra "—".
--}}
@php
    $categoria = $datos['categoria'] ?? null;
    $meses = $datos['meses'] ?? null;
    $estilo = match (substr((string) $categoria, 0, 1)) {
        'A' => 'bg-tint-soft text-royal',
        'B' => 'bg-cyan-soft text-cyan-ink',
        'C' => 'bg-fill text-label-2',
        default => 'bg-fill text-label-3',
    };
    $titulo = 'Categoría Reorder Point'.($fecha ? ' (foto del '.\Illuminate\Support\Carbon::parse($fecha)->format('d/m/Y').')' : '')
        .' · Meses de inventario = disponible 002+005+006 / promedio mensual privado';
@endphp
<span class="inline-flex items-center gap-1.5" title="{{ $titulo }}">
    <span aria-hidden="true">·</span>
    @if ($categoria)
        <span class="badge {{ $estilo }}"><span class="sr-only">Categoría </span>{{ $categoria }}</span>
        @if ($meses !== null)
            <span class="{{ $meses < 1 ? 'text-danger' : '' }}"><span class="num text-[14px] font-bold">{{ number_format($meses, 1) }}</span> {{ $meses == 1.0 ? 'mes' : 'meses' }}</span>
        @else
            <span class="text-label-3">— meses</span>
        @endif
    @else
        <span class="text-label-3">Cat. —</span>
    @endif
</span>
