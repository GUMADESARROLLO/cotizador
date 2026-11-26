@props(['marca' => 'data-fecha-sheet', 'titulo' => 'Fecha', 'quitar' => false])
{{--
    Calendario en panel inferior (bottom sheet). Lo dibuja app.js (crearCalendario).
    $marca: atributo con el que el JS encuentra este panel.
    $quitar: muestra "Quitar fecha" (para filtros opcionales).
--}}
<dialog {{ $marca }} class="lote-sheet" aria-label="{{ $titulo }}">
    <div class="sheet-panel" data-sheet-panel>
        <div class="flex cursor-grab touch-none flex-col items-center pt-2.5 pb-1" data-sheet-grab>
            <span class="bg-silver h-[5px] w-10 rounded-full" aria-hidden="true"></span>
        </div>
        <header class="px-5 pt-2 pb-3" data-sheet-grab>
            <p class="text-cyan-ink text-[11px] font-extrabold tracking-[0.14em] uppercase" data-cal-eyebrow>{{ $titulo }}</p>
            <h2 class="text-ink mt-0.5 text-[17px] leading-snug font-bold first-letter:uppercase" data-cal-elegida></h2>
        </header>
        <div class="flex flex-wrap gap-2 px-5 pb-3" data-cal-rapidos></div>
        <div class="border-line border-t px-3 pt-3">
            <div class="mb-2 flex items-center justify-between px-2">
                <button type="button" data-cal-mes="-1" class="pressable text-royal disabled:text-label-3 grid size-11 place-items-center rounded-full" aria-label="Mes anterior">
                    <svg viewBox="0 0 24 24" class="size-6" fill="currentColor" aria-hidden="true"><path d="M15.7 4.3a1 1 0 0 1 0 1.4L9.41 12l6.3 6.3a1 1 0 0 1-1.42 1.4l-7-7a1 1 0 0 1 0-1.4l7-7a1 1 0 0 1 1.42 0Z"/></svg>
                </button>
                <p class="text-ink text-[17px] font-extrabold first-letter:uppercase" data-cal-titulo aria-live="polite"></p>
                <button type="button" data-cal-mes="1" class="pressable text-royal disabled:text-label-3 grid size-11 place-items-center rounded-full" aria-label="Mes siguiente">
                    <svg viewBox="0 0 24 24" class="size-6" fill="currentColor" aria-hidden="true"><path d="M8.3 4.3a1 1 0 0 1 1.4 0l7 7a1 1 0 0 1 0 1.4l-7 7a1 1 0 0 1-1.4-1.4l6.29-6.3-6.3-6.3a1 1 0 0 1 0-1.4Z"/></svg>
                </button>
            </div>
            <div class="grid grid-cols-7 text-center" data-cal-grid role="grid"></div>
        </div>
        <div class="grid gap-2 px-5 pt-3 {{ $quitar ? 'grid-cols-2' : '' }}" style="padding-bottom: max(16px, env(safe-area-inset-bottom))">
            @if ($quitar)
                <button type="button" data-cal-quitar class="pressable bg-danger-soft text-danger rounded-[14px] py-3.5 text-[15px] font-extrabold tracking-[0.06em] uppercase">Quitar fecha</button>
            @endif
            <button type="button" data-sheet-cerrar class="pressable bg-mist text-royal w-full rounded-[14px] py-3.5 text-[15px] font-extrabold tracking-[0.06em] uppercase">Cancelar</button>
        </div>
    </div>
</dialog>
