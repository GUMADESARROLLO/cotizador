@props(['existencia', 'unidad' => '', 'factor' => 1, 'unidadEmpaque' => ''])
{{--
    Existencia en cajas (con fracción: 0.26 CJA) y, si hay empaque, su
    equivalente en unidades internas (0.26 CJA = 13 VIA).
--}}
@php
    $cajas = \App\Catalogo\Cantidades::bajar4((float) $existencia);
    $unidades = $factor > 1 ? \App\Catalogo\Cantidades::unidades((float) $existencia, (float) $factor) : null;
    $agotado = $unidades !== null ? $unidades < 1 : $cajas <= 0;
    $equiv = $unidades !== null ? ' · '.cantidad($unidades).' '.($unidadEmpaque ?: 'UND') : '';
@endphp
@if ($agotado)
    <span class="badge bg-danger-soft text-danger uppercase">Agotado</span>
@elseif ($cajas <= 5)
    <span class="badge bg-warn-soft text-warn">Quedan <span class="num text-[13px]">{{ cantidad($cajas) }}</span> {{ $unidad }}{{ $equiv }}</span>
@else
    <span class="badge bg-cyan-soft text-cyan-ink"><span class="num text-[13px]">{{ cantidad($cajas) }}</span> {{ $unidad }}{{ $equiv }} disp.</span>
@endif
