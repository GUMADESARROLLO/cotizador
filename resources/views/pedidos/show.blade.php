@php
    $moneda = $pedido->moneda ?? 'L';
    $reintento = $pedido->softland_estado === \App\Models\Pedido::SOFTLAND_ERROR;
    $pendiente = ! $pedido->enviadoASoftland() && $pedido->softland_estado !== \App\Models\Pedido::SOFTLAND_ENVIANDO;
    $check = '<svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M9.55 17.6a1 1 0 0 1-.7-.3l-4.2-4.2a1 1 0 1 1 1.4-1.4l3.5 3.5 8.3-8.3a1 1 0 1 1 1.4 1.4l-9 9a1 1 0 0 1-.7.3Z"/></svg>';
@endphp
<x-layouts.app :title="'Pedido '.($pedido->softland_pedido ? $pedido->numero() : 'sin enviar')" :sub="$pedido->cliente" section="historial">
    <x-slot:actions>
        {{-- Cotización en Excel (plantilla oficial): pide el N.º de licitación antes de descargar. --}}
        <button type="button" class="btn btn-line" onclick="document.getElementById('dlg-cotizacion').showModal()">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="m8 13 4 5M12 13l-4 5"/></svg>
            Cotización Excel
        </button>
        {{-- Adjuntar documentos: solo cuando el pedido ya está ingresado en Softland. --}}
        @if ($pedido->enviadoASoftland())
            <button type="button" class="btn btn-line" data-adjuntos-abrir>
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                Adjuntar
                @if ($pedido->adjuntos_count)<span class="btn-n" data-adjuntos-n>{{ $pedido->adjuntos_count }}</span>@else<span class="btn-n" data-adjuntos-n hidden>0</span>@endif
            </button>
        @endif
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

    @if ($pedido->enviadoASoftland())
        @php $cfgAdj = config('cotizacion.adjuntos'); @endphp
        {{-- Documentos del pedido: se suben al bucket (carpeta Cotizaciones/<N.º de pedido>). --}}
        <dialog class="dlg dlg-adjuntos" data-adjuntos-dlg aria-labelledby="dlg-adjuntos-t"
                data-listar="{{ route('pedidos.adjuntos', $pedido) }}" data-subir="{{ route('pedidos.adjuntos.subir', $pedido) }}"
                data-max-kb="{{ $cfgAdj['max_kb'] }}" data-extensiones="{{ implode(',', $cfgAdj['extensiones']) }}">
            <div class="dlg-form">
                <div class="adj-head">
                    <div>
                        <h2 id="dlg-adjuntos-t" class="dlg-title">Adjuntos del pedido {{ $pedido->numero() }}</h2>
                        <p class="dlg-sub">{{ $pedido->cliente }}</p>
                    </div>
                    <button type="button" class="icon-btn adj-cerrar" data-adjuntos-cerrar aria-label="Cerrar">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Zona para soltar archivos (o hacer clic para elegirlos). --}}
                <label class="adj-drop" data-adjuntos-drop>
                    <input type="file" multiple data-adjuntos-input accept="{{ collect($cfgAdj['extensiones'])->map(fn ($e) => '.'.$e)->implode(',') }}" class="sr-only">
                    <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V4M7 9l5-5 5 5"/><path d="M20 16v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3"/></svg>
                    <strong>Arrastra los archivos aquí</strong>
                    <span>o <u>haz clic para elegirlos</u> · PDF, imágenes, Word, Excel, ZIP… hasta {{ round($cfgAdj['max_kb'] / 1024) }} MB c/u</span>
                </label>
                <div class="adj-progreso" data-adjuntos-progreso hidden>
                    <div class="adj-barra"><span data-adjuntos-barra></span></div>
                    <small data-adjuntos-estado>Subiendo…</small>
                </div>

                {{-- Archivos adjuntados: buscar, ordenar por columna y descargar / eliminar. --}}
                <div class="adj-lista">
                    <div class="toolbar adj-toolbar">
                        <span class="adj-total"><b data-adjuntos-total>0</b> archivo(s)</span>
                        <label class="search-box">
                            <span class="sr-only">Buscar adjunto</span>
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                            <input type="search" placeholder="Buscar archivo…" data-adjuntos-buscar autocomplete="off">
                        </label>
                    </div>
                    <div class="tbl-box">
                        <table class="tbl adj-tbl" data-orden-local>
                            <thead>
                                <tr>
                                    <x-th-orden campo="nombre">Archivo</x-th-orden>
                                    <x-th-orden campo="tipo">Tipo</x-th-orden>
                                    <x-th-orden campo="tamano" class="th-num">Tamaño</x-th-orden>
                                    <x-th-orden campo="fecha">Fecha</x-th-orden>
                                    <th><span class="sr-only">Acciones</span></th>
                                </tr>
                            </thead>
                            <tbody data-adjuntos-filas></tbody>
                        </table>
                    </div>
                    <p class="empty adj-vacio" data-adjuntos-vacio>Aún no hay documentos adjuntos.</p>
                </div>
            </div>
        </dialog>
    @endif

    <dialog id="dlg-cotizacion" class="dlg" aria-labelledby="dlg-cotizacion-t">
        <form method="POST" action="{{ route('pedidos.cotizacion', $pedido) }}" class="dlg-form"
              onsubmit="setTimeout(() => this.closest('dialog').close(), 50)">
            @csrf
            <h2 id="dlg-cotizacion-t" class="dlg-title">Cotización en Excel</h2>
            <p class="dlg-sub">Se genera sobre la plantilla oficial con los datos de este pedido.</p>
            <label class="dlg-campo">
                <span>N.º de licitación</span>
                <input type="text" name="licitacion" value="{{ $pedido->licitacion }}" maxlength="60" autocomplete="off"
                       placeholder="Ej. CA-158-10-2026" autofocus>
                <small>El que comparte el cliente. Puede quedar vacío.</small>
            </label>

            {{-- Datos del cliente: se recuerdan para sus próximas cotizaciones (tabla cliente_fichas). --}}
            <fieldset class="dlg-grupo">
                <legend>Datos del cliente <small>· se recuerdan para la próxima vez</small></legend>
                <label class="dlg-campo">
                    <span>Cliente</span>
                    <input type="text" name="nombre" value="{{ $ficha['nombre'] }}" maxlength="150" placeholder="Ej. Distribuidora Cruz Azul">
                </label>
                <div class="dlg-fila">
                    <label class="dlg-campo">
                        <span>Atención</span>
                        <input type="text" name="atencion" value="{{ $ficha['atencion'] }}" maxlength="150" placeholder="Ej. Lic. Oscar Estrada">
                    </label>
                    <label class="dlg-campo">
                        <span>Cargo</span>
                        <input type="text" name="cargo" value="{{ $ficha['cargo'] }}" maxlength="100" placeholder="Ej. Gerente General">
                    </label>
                </div>
                <div class="dlg-fila">
                    <label class="dlg-campo">
                        <span>Teléfono</span>
                        <input type="text" name="telefono" value="{{ $ficha['telefono'] }}" maxlength="60" inputmode="tel">
                    </label>
                    <label class="dlg-campo">
                        <span>Fax</span>
                        <input type="text" name="fax" value="{{ $ficha['fax'] }}" maxlength="60" inputmode="tel">
                    </label>
                </div>
            </fieldset>

            {{-- Cambia en cada cotización: se guarda con el pedido; sugiere los ya usados con este cliente. --}}
            <label class="dlg-campo">
                <span>Responsable <small>(línea verde bajo "Ref:", de esta cotización)</small></span>
                <input type="text" name="responsable" value="{{ $pedido->responsable }}" maxlength="150" list="dlg-responsables"
                       autocomplete="off" placeholder="Ej. Lic. Fabiola Guerreri Molina">
                <datalist id="dlg-responsables">
                    @foreach ($responsables as $r)
                        <option value="{{ $r }}"></option>
                    @endforeach
                </datalist>
                @if (count($responsables))
                    <small>Escribe o elige uno de los usados antes con este cliente.</small>
                @endif
            </label>
            <div class="dlg-acciones">
                <button type="button" class="btn btn-line" onclick="this.closest('dialog').close()">Cancelar</button>
                <button class="btn btn-solid">Descargar Excel</button>
            </div>
        </form>
    </dialog>

    @if (session('creado'))
        <div class="ped-flash no-print">{!! $check !!} Pedido confirmado</div>
    @endif
    @if (session('actualizado'))
        <div class="ped-flash no-print">{!! $check !!} Cambios guardados</div>
    @endif

    @if (session('softland_ok') || session('softland_error'))
        <script>window.__softland = @json(['ok' => session('softland_ok'), 'error' => session('softland_error')]);</script>
    @endif

    {{-- Resumen: quién (cliente) · cuándo (fecha) · qué número (Softland: asignado o tentativo) · cuánto (total). --}}
    <div class="ped-cabecera">
        @include('pedidos._cliente', ['cliente' => $cliente, 'total' => null, 'cambiar' => false])
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
            <p class="kpi-label">Total</p>
            <p class="kpi-value" style="color: var(--color-royal)">{{ dinero($pedido->total, $moneda) }}</p>
            {{-- $moneda es el código de Softland (L = córdobas, D = dólares): se muestra el nombre. --}}
            <p class="kpi-sub">{{ $moneda === 'D' ? 'Dólares (US$)' : 'Córdobas (C$)' }}</p>
        </div>
    </div>

    {{-- Observaciones del pedido (van a Softland): a la vista, antes de los artículos. --}}
    @if ($pedido->notas)
        <section class="card ped-notas" aria-label="Observaciones">
            <div>
                <p class="eyebrow">Observaciones</p>
                <p class="ped-notas-texto">{{ $pedido->notas }}</p>
            </div>
        </section>
    @endif

    {{-- Artículos con el formato de la cotización (mismos datos que el Excel). --}}
    @php
        $sim = ($cotizacion['moneda'] ?? 'L') === 'D' ? 'US$' : 'C$';
        $totalCot = collect($cotizacion['lineas'])->sum(fn ($l) => round($l['cantidad'] * $l['precio'], 2));
        $items = $pedido->items->values();
    @endphp
    <section class="card" aria-label="Artículos">
        <div class="card-head">
            <h2>{{ $pedido->lineas }} artículo(s)</h2>
        </div>
        <div class="tbl-box">
            <table class="tbl tbl-cot" data-orden-local>
                <thead>
                    <tr>
                        <x-th-orden campo="item">Item</x-th-orden>
                        <x-th-orden campo="cantidad" class="th-num">Cant.</x-th-orden>
                        <x-th-orden campo="descripcion">Descripción</x-th-orden>
                        <x-th-orden campo="concentracion">Concentración</x-th-orden>
                        <x-th-orden campo="precio" class="th-num">Precio Unitario {{ $sim }}</x-th-orden>
                        <x-th-orden campo="total" class="th-num">Total {{ $sim }}</x-th-orden>
                        <x-th-orden campo="entrega">Tiempo de Entrega</x-th-orden>
                        <x-th-orden campo="lote">Lote</x-th-orden>
                        <x-th-orden campo="vence">Fecha Venc.</x-th-orden>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cotizacion['lineas'] as $n => $l)
                        @php $item = $items[$n] ?? null; @endphp
                        <tr>
                            <td class="cot-c">{{ $n + 1 }}</td>
                            <td class="td-num">{{ cantidad($l['cantidad']) }}</td>
                            <td class="cot-desc" data-v="{{ $l['descripcion'] }}">
                                {{ $l['descripcion'] }}
                                @if ($item?->codigo)<span class="td-sub cot-codigo">{{ $item->codigo }}</span>@endif
                            </td>
                            <td class="cot-c">{{ $l['concentracion'] }}</td>
                            {{-- Precio con todos sus decimales si los tiene (1.4108 por cápsula), como el Excel. --}}
                            <td class="td-num">{{ number_format($l['precio'], round($l['precio'], 2) == $l['precio'] ? 2 : 4) }}</td>
                            <td class="td-num td-total">{{ number_format(round($l['cantidad'] * $l['precio'], 2), 2) }}</td>
                            <td class="cot-c">{{ $tiempoEntrega }}</td>
                            <td class="cot-c">{{ $l['lote'] ?: '—' }}</td>
                            <td class="cot-c" data-v="{{ $l['vence']?->format('Y-m-d') }}">{{ \App\Cotizaciones\CotizacionExcel::vence($l['vence']) ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5">Total {{ $sim }}</td>
                        <td class="td-num">{{ $sim }} {{ number_format($totalCot, 2) }}</td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>

    {{-- Contacto, aviso de crédito y estado del envío (solo si aplican). --}}
    <div class="ped-info">
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
    </div>
</x-layouts.app>
