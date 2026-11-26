<x-layouts.app title="Datos para Softland" slogan="Encabezado y líneas que llevaría el pedido · solo vista previa" tab="pedido" :resumen="$resumen">
    <x-slot:leading>
        <a href="{{ route('carrito.index') }}" class="pressable text-royal -ml-2 flex h-11 items-center gap-0.5 px-1 text-sm font-extrabold tracking-[0.06em] uppercase">
            <svg viewBox="0 0 24 24" class="size-6" fill="currentColor" aria-hidden="true"><path d="M15.7 4.3a1 1 0 0 1 0 1.4L9.41 12l6.3 6.3a1 1 0 0 1-1.42 1.4l-7-7a1 1 0 0 1 0-1.4l7-7a1 1 0 0 1 1.42 0Z"/></svg>
            Pedido
        </a>
    </x-slot:leading>

    <p class="text-on-royal-soft mb-4 rounded-[20px] bg-white/10 px-4 py-3 text-sm ring-1 ring-white/20">
        Esto <strong class="text-white">no se envía a Softland</strong>: muestra cómo quedarían los registros en
        <span class="num text-white">umk.PEDIDO</span> y <span class="num text-white">umk.PEDIDO_LINEA</span> con el carrito actual.
    </p>

    @php
        $mostrar = fn ($v) => $v === null ? 'NULL' : (is_float($v) ? rtrim(rtrim(number_format($v, 4, '.', ','), '0'), '.') : (string) $v);
    @endphp

    @if ($error)
        <p class="bg-danger-soft text-danger rounded-[20px] px-4 py-3 font-semibold">No se pudo armar: {{ $error }}</p>
    @else
        @php
            $tabla = function (array $campos) use ($mostrar) {
                return collect($campos)->map(fn ($x, $campo) => compact('campo') + $x + ['texto' => $mostrar($x['valor'])]);
            };
        @endphp

        <section class="space-y-2">
            <p class="section-label">umk.PEDIDO · encabezado ({{ count($encabezado) }} columnas)</p>
            <ul class="group-list">
                @foreach ($tabla($encabezado) as $f)
                    <li class="px-4 py-2.5">
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="num text-[15px] font-bold">{{ $f['campo'] }}</span>
                            <span class="min-w-0 truncate text-right text-sm font-semibold">{{ $f['texto'] }}</span>
                        </div>
                        <p class="text-label-2 text-[12px]">{{ $f['origen'] }}</p>
                    </li>
                @endforeach
            </ul>
        </section>

        @foreach ($lineas as $n => $linea)
            <section class="mt-7 space-y-2">
                <p class="section-label">umk.PEDIDO_LINEA · línea {{ $n + 1 }} · {{ $linea['ARTICULO']['valor'] }} ({{ count($linea) }} columnas)</p>
                <ul class="group-list">
                    @foreach ($tabla($linea) as $f)
                        <li class="px-4 py-2.5">
                            <div class="flex items-baseline justify-between gap-3">
                                <span class="num text-[15px] font-bold">{{ $f['campo'] }}</span>
                                <span class="min-w-0 truncate text-right text-sm font-semibold">{{ $f['texto'] }}</span>
                            </div>
                            <p class="text-label-2 text-[12px]">{{ $f['origen'] }}</p>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach

        <p class="text-on-royal-soft px-1 pt-3 text-[12px]">Incluye todas las columnas obligatorias (NOT NULL) de umk.PEDIDO y umk.PEDIDO_LINEA.</p>
    @endif
</x-layouts.app>
