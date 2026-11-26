<li class="flex flex-col gap-2.5 px-4 py-3.5 sm:flex-row sm:items-center sm:gap-4">
    <div class="min-w-0 flex-1">
        <p class="text-body leading-snug font-semibold">{{ $a->descripcion }}</p>
        <p class="text-foot text-label-2 mt-1 flex flex-wrap items-center gap-x-2 gap-y-1">
            <span class="num text-[15px] font-bold">{{ $a->codigo }}</span>
            <span aria-hidden="true">·</span>
            <x-existencia :existencia="$a->existencia" :unidad="$a->unidad" />
        </p>
    </div>
    <div class="flex items-center justify-between gap-3 sm:justify-end">
        <p>
            <span class="price">{{ dinero($a->precio) }}</span>
            @if ($a->unidad)<span class="text-foot text-label-2">/ {{ $a->unidad }}</span>@endif
        </p>
        <x-stepper :codigo="$a->codigo" :cantidad="$enPedido" :max="$a->maximoPedible()" />
    </div>
</li>
