@props(['codigo', 'cantidad' => 0, 'max' => 0, 'agregar' => true, 'modo' => 'caja', 'etiqueta' => null])
{{--
    Control de cantidad. Si $agregar y la cantidad es 0, muestra un botón "Agregar".
    El máximo siempre es la existencia disponible (validada también en servidor).
    $modo: "unidad" = unidades enteras (viales…); "caja" = cajas, admite fracción.
    $etiqueta: unidad que se muestra junto al número (VIA, CJA…).
--}}
@php
    $valor = rtrim(rtrim(number_format((float) $cantidad, 4, '.', ''), '0'), '.');
    $decimal = $modo !== 'unidad';
@endphp
<div data-stepper data-codigo="{{ $codigo }}" data-max="{{ $max }}" data-qty="{{ $valor }}" data-modo="{{ $modo }}"
     @if ($agregar && ! (float) $cantidad) data-empty @endif
     class="group/st flex items-center justify-end">
    @if ($agregar)
        <button type="button" data-step="add" @disabled($max <= 0)
                class="pressable bg-cyan text-navy disabled:bg-fill disabled:text-label-3 h-11 min-w-[112px] rounded-xl px-4 text-[14px] font-extrabold tracking-[0.06em] uppercase shadow-[inset_0_-3px_0_rgb(10_118_168/0.35)] disabled:shadow-none group-not-data-empty/st:hidden">
            {{ $max <= 0 ? 'Agotado' : 'Agregar' }}
        </button>
    @endif
    <div class="bg-fill flex items-center rounded-full {{ $agregar ? 'group-data-empty/st:hidden' : '' }}">
        <button type="button" data-step="-1" class="stepper-btn" aria-label="Quitar uno">
            <svg viewBox="0 0 24 24" class="size-5" fill="currentColor" aria-hidden="true"><path d="M5 11h14a1 1 0 1 1 0 2H5a1 1 0 1 1 0-2Z"/></svg>
        </button>
        <label class="flex flex-col items-center leading-none">
            <input type="text" inputmode="{{ $decimal ? 'decimal' : 'numeric' }}" value="{{ $valor }}"
                   data-qty-input aria-label="Cantidad{{ $etiqueta ? ' en '.$etiqueta : '' }}"
                   class="num text-ink w-16 bg-transparent text-center text-xl font-extrabold outline-none">
            @if ($etiqueta)
                <span class="text-label-2 -mt-0.5 text-[10px] font-extrabold tracking-[0.1em] uppercase" data-qty-unidad>{{ $etiqueta }}</span>
            @endif
        </label>
        <button type="button" data-step="1" class="stepper-btn" aria-label="Agregar uno" @disabled((float) $cantidad + 1 > $max)>
            <svg viewBox="0 0 24 24" class="size-5" fill="currentColor" aria-hidden="true"><path d="M12 4a1 1 0 0 1 1 1v6h6a1 1 0 1 1 0 2h-6v6a1 1 0 1 1-2 0v-6H5a1 1 0 1 1 0-2h6V5a1 1 0 0 1 1-1Z"/></svg>
        </button>
    </div>
</div>
