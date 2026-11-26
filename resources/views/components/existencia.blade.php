@props(['existencia', 'unidad' => ''])
@php $n = floor($existencia); @endphp
@if ($n < 1)
    <span class="badge bg-danger-soft text-danger uppercase">Agotado</span>
@elseif ($n <= 5)
    <span class="badge bg-warn-soft text-warn">Quedan <span class="num text-[13px]">{{ cantidad($n) }}</span> {{ $unidad }}</span>
@else
    <span class="badge bg-cyan-soft text-cyan-ink"><span class="num text-[13px]">{{ cantidad($n) }}</span> {{ $unidad }} disp.</span>
@endif
