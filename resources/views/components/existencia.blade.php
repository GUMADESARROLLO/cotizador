@props(['existencia', 'unidad' => '', 'factor' => 1, 'unidadEmpaque' => ''])
{{--
    Disponible en cajas (con fracción: 0.26 CJA) y, si hay empaque, su
    equivalente en unidades internas como línea secundaria (0.26 CJA = 13 VIA).
--}}
@php
    $cajas = \App\Catalogo\Cantidades::bajar4((float) $existencia);
    $unidades = $factor > 1 ? \App\Catalogo\Cantidades::unidades((float) $existencia, (float) $factor) : null;
    $agotado = $unidades !== null ? $unidades < 1 : $cajas <= 0;
@endphp
@if ($agotado)
    <span class="tag tag-bad">Agotado</span>
@else
    <span class="{{ $cajas <= 5 ? 'text-warn' : '' }}">{{ cantidad($cajas) }} {{ $unidad }}</span>
    @if ($unidades !== null)
        <span class="td-sub">{{ cantidad($unidades) }} {{ $unidadEmpaque ?: 'UND' }}</span>
    @endif
@endif
