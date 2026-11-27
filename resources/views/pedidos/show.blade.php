@php
    $moneda = $pedido->moneda ?? 'L';
    $reintento = $pedido->softland_estado === \App\Models\Pedido::SOFTLAND_ERROR;
    $pendiente = ! $pedido->enviadoASoftland() && $pedido->softland_estado !== \App\Models\Pedido::SOFTLAND_ENVIANDO;
    $check = '<svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M9.55 17.6a1 1 0 0 1-.7-.3l-4.2-4.2a1 1 0 1 1 1.4-1.4l3.5 3.5 8.3-8.3a1 1 0 1 1 1.4 1.4l-9 9a1 1 0 0 1-.7.3Z"/></svg>';
@endphp
<x-layouts.app :title="'Pedido '.($pedido->softland_pedido ? $pedido->numero() : 'sin enviar')" :sub="$pedido->cliente" section="historial">
    <x-slot:actions>
        <a href="{{ route('pedidos.index') }}" class="btn btn-line">Historial</a>
        <button type="button" onclick="window.print()" class="btn btn-line">Imprimir</button>
        {{-- Envío manual a Softland: Modificar y Enviar (solo si aún no se envió ni se está enviando). --}}
        @if ($pendiente)
            <form method="POST" action="{{ route('pedidos.editar', $pedido) }}">
                @csrf
                <button aria-label="Modificar pedido" class="btn btn-line">Modificar</button>
            </form>
            <form method="POST" action="{{ route('pedidos.softland', $pedido) }}" data-softland-form
                  data-cliente="{{ $pedido->cliente }}"
                  data-total="{{ dinero($pedido->total, $moneda) }}" data-lineas="{{ $pedido->lineas }}"
                  data-consecutivo="{{ $pedido->consecutivo() }}" data-usuario="{{ config('softland.usuario') }}">
                @csrf
                <button class="btn btn-solid" aria-label="{{ $reintento ? 'Reintentar envío a Softland' : 'Enviar a Softland' }}">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M3.4 20.4 21.85 12.5a.55.55 0 0 0 0-1L3.4 3.6a.5.5 0 0 0-.7.6L5 11l9 1-9 1-2.3 6.8a.5.5 0 0 0 .7.6Z"/></svg>
                    {{ $reintento ? 'Reintentar' : 'Enviar' }}
                </button>
            </form>
        @endif
    </x-slot:actions>

    @if (session('creado'))
        <div class="ped-flash no-print">{!! $check !!} Pedido confirmado</div>
    @endif
    @if (session('actualizado'))
        <div class="ped-flash no-print">{!! $check !!} Cambios guardados</div>
    @endif

    @if (session('softland_ok') || session('softland_error'))
        <script>window.__softland = @json(['ok' => session('softland_ok'), 'error' => session('softland_error')]);</script>
    @endif

    {{-- Fecha del pedido y número en Softland (el asignado o, si no se ha enviado, el tentativo). --}}
    <div class="kpis ped-block">
        <div class="kpi">
            <p class="kpi-label">Fecha del pedido</p>
            <p class="kpi-value" style="font-size: 24px">{{ fecha_corta($pedido->fecha_pedido ?? $pedido->created_at) }}</p>
            <p class="kpi-sub">Creado {{ $pedido->created_at->format('d/m/Y H:i') }}</p>
        </div>
        <div class="kpi">
            <p class="kpi-label">Pedido Softland</p>
            @if ($pedido->softland_pedido)
                <p class="kpi-value" style="font-size: 24px">N.º {{ $pedido->softland_pedido }}</p>
                <p class="kpi-sub">
                    Consecutivo <b>{{ $pedido->consecutivo() }}</b>
                    @if ($pedido->softland_enviado_at) · enviado {{ $pedido->softland_enviado_at->format('d/m H:i') }} @endif
                </p>
            @else
                <p class="kpi-value" style="font-size: 24px; color: var(--color-ink-soft)">{{ $numeroTentativo ? 'N.º '.$numeroTentativo : 'Sin enviar' }}</p>
                <p class="kpi-sub">Consecutivo <b>{{ $pedido->consecutivo() }}</b> · se asigna al enviar</p>
            @endif
        </div>
        <div class="kpi">
            <p class="kpi-label">Artículos</p>
            <p class="kpi-value">{{ $pedido->lineas }}</p>
            <p class="kpi-sub">líneas del pedido</p>
        </div>
        <div class="kpi">
            <p class="kpi-label">Total</p>
            <p class="kpi-value" style="color: var(--color-royal)">{{ dinero($pedido->total, $moneda) }}</p>
            <p class="kpi-sub">{{ $moneda }}</p>
        </div>
    </div>

    <div class="ped-grid">
        <div class="ped-main">
            <section class="card" aria-label="Artículos">
                <div class="card-head">
                    <h2>{{ $pedido->lineas }} artículo(s)</h2>
                </div>
                <div class="tbl-box">
                    <table class="tbl" data-orden-local>
                        <thead>
                            <tr>
                                <x-th-orden campo="articulo">Artículo</x-th-orden>
                                <x-th-orden campo="cantidad" class="th-num">Cantidad</x-th-orden>
                                <x-th-orden campo="precio" class="th-num">Precio</x-th-orden>
                                <x-th-orden campo="subtotal" class="th-num">Subtotal</x-th-orden>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pedido->items as $i)
                                @php $l = $i->comoLinea(); @endphp
                                <tr>
                                    <td>
                                        <span class="ped-art-desc">{{ $i->descripcion }}</span>
                                        <span class="ped-art-sub">
                                            <span class="num text-[15px] font-bold">{{ $i->codigo }}</span>
                                            @if ($i->lote)
                                                · Lote <span class="num text-ink text-[15px] font-bold">{{ $i->lote }}</span>@if ($i->lote_vence) · vence <span class="num text-[15px] font-bold">{{ \Illuminate\Support\Carbon::parse($i->lote_vence)->format('d/m/Y') }}</span>@endif
                                            @endif
                                        </span>
                                    </td>
                                    <td class="td-num">
                                        {{ cantidad($i->cantidad) }} {{ $i->unidad }}
                                        @if (\App\Pedidos\Linea::tieneEmpaque($l))
                                            {{-- Lo que pidió el cliente, en unidades: 2,800 VIA = 56 CJA --}}
                                            <span class="td-sub">{{ cantidad($l['unidades']) }} {{ \App\Pedidos\Linea::unidadInterna($l) }}</span>
                                        @endif
                                    </td>
                                    <td class="td-num">
                                        {{ dinero($i->precio, $moneda) }}
                                        @if (\App\Pedidos\Linea::tieneEmpaque($l))
                                            <span class="td-sub">{{ dinero(\App\Pedidos\Linea::precioUnidad($l), $moneda) }} / {{ \App\Pedidos\Linea::unidadInterna($l) }}</span>
                                        @endif
                                    </td>
                                    <td class="td-num td-total">{{ dinero($i->subtotal, $moneda) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3">Total</td>
                                <td class="td-num">{{ dinero($pedido->total, $moneda) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>
        </div>

        <aside class="ped-side">
            {{-- Cliente: igual que al crear el pedido (datos al día de Softland). --}}
            @include('pedidos._cliente', ['cliente' => $cliente, 'total' => null, 'cambiar' => false])

            @if ($pedido->contacto)
                <section class="card ped-cliente" aria-label="Contacto">
                    <div class="ped-cliente-body">
                        <p class="eyebrow">Contacto</p>
                        <p class="ped-cliente-nombre">{{ $pedido->contacto }}</p>
                    </div>
                </section>
            @endif

            @if ($pedido->credito_disponible !== null && $pedido->total > $pedido->credito_disponible)
                <p class="ped-aviso">
                    ⚠ Al confirmar, el total superaba el crédito disponible del cliente ({{ dinero($pedido->credito_disponible, $moneda) }}).
                </p>
            @endif

            @if ($pedido->notas)
                <section class="card ped-cliente" aria-label="Observaciones">
                    <div class="ped-cliente-body">
                        <p class="eyebrow">Observaciones</p>
                        <p class="ped-cliente-nombre" style="font-weight: 500; white-space: pre-line">{{ $pedido->notas }}</p>
                    </div>
                </section>
            @endif

            {{-- Estado del envío a Softland (el número, ya enviado, se ve arriba). --}}
            <section class="no-print" aria-label="Softland">
                @if ($pedido->softland_estado === \App\Models\Pedido::SOFTLAND_ENVIANDO)
                    <p class="ped-aviso">
                        Enviando a Softland… recarga la página en unos segundos.
                        <span style="display: block; font-size: 12px; font-weight: 500">Si el envío se interrumpió, al cabo de {{ config('softland.envio_trabado_minutos') }} minutos se comprobará en Softland y se mostrará el resultado.</span>
                    </p>
                @elseif ($reintento && ! $pedido->enviadoASoftland())
                    <div class="ped-aviso is-bad">
                        <p style="font-weight: 700">No se pudo enviar a Softland</p>
                        <p style="margin-top: 2px">{{ $pedido->softland_error }}</p>
                    </div>
                @endif
            </section>
        </aside>
    </div>
</x-layouts.app>
