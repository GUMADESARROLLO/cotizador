@props(['codigo', 'lotes', 'seleccionado', 'unidad' => '', 'descripcion' => ''])
{{--
    Lote de despacho del artículo (bodega del pedido). Viene sugerido el que
    vence primero; con varios lotes, un botón abre el panel para elegir otro.
--}}
@php
    $vence = fn ($l) => $l->vence ? \Illuminate\Support\Carbon::parse($l->vence)->format('d/m/Y') : 's/f';
@endphp
<div class="mt-2">
    @if (count($lotes) > 1)
        <button type="button" data-lote-trigger data-codigo="{{ $codigo }}"
                data-descripcion="{{ $descripcion }}" data-unidad="{{ $unidad }}"
                data-lotes="{{ json_encode(array_map(fn ($l) => $l->toArray(), $lotes)) }}"
                data-seleccionado="{{ $seleccionado->lote }}"
                aria-haspopup="dialog"
                class="pressable bg-mist ring-line active:bg-cyan-soft flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left ring-1">
            <span class="min-w-0 flex-1">
                <span class="text-cyan-ink block text-[11px] font-extrabold tracking-[0.1em] uppercase">Lote · {{ count($lotes) }} disponibles</span>
                <span class="text-ink block text-[14px]" data-lote-texto>
                    <span class="num block text-[17px] leading-tight font-bold">{{ $seleccionado->lote }}</span>
                    <span class="text-label-2 block">vence <span class="num text-ink text-[16px] font-bold">{{ $vence($seleccionado) }}</span> · <span class="num text-ink text-[16px] font-bold">{{ cantidad($seleccionado->maximoPedible()) }}</span> {{ $unidad }} disp.</span>
                </span>
            </span>
            <span class="text-royal shrink-0 text-[12px] font-extrabold tracking-[0.08em] uppercase">Cambiar</span>
            <svg viewBox="0 0 24 24" class="text-royal -ml-2 size-4 shrink-0" fill="currentColor" aria-hidden="true"><path d="M8.3 4.3a1 1 0 0 1 1.4 0l7 7a1 1 0 0 1 0 1.4l-7 7a1 1 0 0 1-1.4-1.4l6.29-6.3-6.3-6.3a1 1 0 0 1 0-1.4Z"/></svg>
        </button>
    @else
        <p class="text-foot text-label-2">
            <span class="text-cyan-ink text-[11px] font-extrabold tracking-[0.1em] uppercase">Lote</span>
            <span class="num text-ink text-[15px] font-bold">{{ $seleccionado->lote }}</span>
            · vence <span class="num text-[15px] font-bold">{{ $vence($seleccionado) }}</span>
            · <span class="num text-[15px] font-bold">{{ cantidad($seleccionado->maximoPedible()) }}</span> {{ $unidad }} disp.
        </p>
    @endif
</div>
