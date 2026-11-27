<x-layouts.app title="Datos para Softland" sub="Encabezado y líneas que llevaría el pedido · solo vista previa" section="pedido" :resumen="$resumen">
    <x-slot:actions>
        <a href="{{ route('carrito.index') }}" class="btn btn-line">Volver al pedido</a>
    </x-slot:actions>

    <p class="ped-aviso ped-block" style="background: var(--color-cyan-soft); color: var(--color-ink)">
        Esto <strong>no se envía a Softland</strong>: muestra cómo quedarían los registros en
        <span class="num">umk.PEDIDO</span> y <span class="num">umk.PEDIDO_LINEA</span> con el carrito actual.
    </p>

    @php
        $mostrar = fn ($v) => $v === null ? 'NULL' : (is_float($v) ? rtrim(rtrim(number_format($v, 4, '.', ','), '0'), '.') : (string) $v);
    @endphp

    @if ($error)
        <p class="ped-aviso is-bad ped-block">No se pudo armar: {{ $error }}</p>
    @else
        @php
            $tabla = function (array $campos) use ($mostrar) {
                return collect($campos)->map(fn ($x, $campo) => compact('campo') + $x + ['texto' => $mostrar($x['valor'])]);
            };
        @endphp

        <section class="card ped-block" aria-label="Encabezado">
            <div class="card-head">
                <h2>umk.PEDIDO · encabezado ({{ count($encabezado) }} columnas)</h2>
            </div>
            <div class="tbl-box">
                <table class="tbl" data-orden-local>
                    <thead>
                        <tr><x-th-orden campo="campo">Campo</x-th-orden><x-th-orden campo="valor">Valor</x-th-orden><x-th-orden campo="origen">Origen</x-th-orden></tr>
                    </thead>
                    <tbody>
                        @foreach ($tabla($encabezado) as $f)
                            <tr>
                                <td class="sl-campo">{{ $f['campo'] }}</td>
                                <td class="sl-valor">{{ $f['texto'] }}</td>
                                <td class="sl-origen">{{ $f['origen'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        @foreach ($lineas as $n => $linea)
            <section class="card ped-block" aria-label="Línea {{ $n + 1 }}">
                <div class="card-head">
                    <h2>umk.PEDIDO_LINEA · línea {{ $n + 1 }} · {{ $linea['ARTICULO']['valor'] }} ({{ count($linea) }} columnas)</h2>
                </div>
                <div class="tbl-box">
                    <table class="tbl" data-orden-local>
                        <thead>
                            <tr><x-th-orden campo="campo">Campo</x-th-orden><x-th-orden campo="valor">Valor</x-th-orden><x-th-orden campo="origen">Origen</x-th-orden></tr>
                        </thead>
                        <tbody>
                            @foreach ($tabla($linea) as $f)
                                <tr>
                                    <td class="sl-campo">{{ $f['campo'] }}</td>
                                    <td class="sl-valor">{{ $f['texto'] }}</td>
                                    <td class="sl-origen">{{ $f['origen'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach

        <p class="page-sub" style="margin-top: 16px">Incluye todas las columnas obligatorias (NOT NULL) de umk.PEDIDO y umk.PEDIDO_LINEA.</p>
    @endif
</x-layouts.app>
