<?php

namespace App\Http\Controllers;

use App\Catalogo\Catalogo;
use App\Catalogo\Lotes;
use App\Clientes\Cliente;
use App\Clientes\Clientes;
use App\Models\Pedido;
use App\Pedidos\Carrito;
use App\Pedidos\Linea;
use App\Softland\ConsecutivoPedido;
use App\Softland\EnviarPedidoSoftland;
use App\Support\Orden;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PedidoController extends Controller
{
    /** Columnas del historial que se ordenan al tocar el encabezado => expresión SQL (lista cerrada). */
    private const ORDENES_HISTORIAL = [
        'pedido' => 'softland_pedido',
        'cliente' => 'cliente',
        'fecha' => 'COALESCE(fecha_pedido, created_at)',
        'articulos' => 'lineas',
        'softland' => 'softland_estado',
        'total' => 'total',
    ];

    public function index(Request $request)
    {
        $filtros = $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
        ]) + ['desde' => null, 'hasta' => null];
        // Si vienen al revés, se ordenan.
        if ($filtros['desde'] && $filtros['hasta'] && $filtros['desde'] > $filtros['hasta']) {
            [$filtros['desde'], $filtros['hasta']] = [$filtros['hasta'], $filtros['desde']];
        }

        $pedidos = Pedido::query()
            // Pedidos creados en el rango (ambos días incluidos).
            ->when($filtros['desde'], fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filtros['hasta'], fn ($q, $h) => $q->whereDate('created_at', '<=', $h))
            ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($w) => $w
                ->where('softland_pedido', 'like', "%{$t}%")
                ->orWhere('cliente', 'like', "%{$t}%")
                ->orWhere('cliente_codigo', $t)))
            ->when(
                $orden = Orden::desde($request, array_keys(self::ORDENES_HISTORIAL)),
                // Columna elegida al tocar el encabezado; vacíos al final; empate: más reciente.
                fn ($q) => $q->orderByRaw(self::ORDENES_HISTORIAL[$orden->campo].' IS NULL')
                    ->orderByRaw(self::ORDENES_HISTORIAL[$orden->campo].' '.$orden->dir())
                    ->latest('id'),
                fn ($q) => $q->latest(),
            )
            ->paginate(20)
            ->withQueryString();

        return view('pedidos.index', compact('pedidos', 'filtros', 'orden'));
    }

    public function show(Pedido $pedido, EnviarPedidoSoftland $envio, Clientes $clientes, ConsecutivoPedido $consecutivo)
    {
        // Si un envío quedó a medias, se resuelve consultando Softland (sin duplicar).
        try {
            $envio->revisarEnvioTrabado($pedido);
        } catch (Throwable $e) {
            Log::warning("Revisión de envío trabado {$pedido->folio}: ".$e->getMessage());
        }

        // Cliente como al crear el pedido: datos al día de Softland (crédito disponible,
        // moroso…); si no se puede leer, los guardados con el pedido.
        try {
            $cliente = $pedido->cliente_codigo ? $clientes->encontrar($pedido->cliente_codigo) : null;
        } catch (Throwable $e) {
            Log::warning("Cliente del pedido {$pedido->id}: ".$e->getMessage());
            $cliente = null;
        }
        $cliente ??= Cliente::fromArray([
            'codigo' => (string) $pedido->cliente_codigo,
            'nombre' => $pedido->cliente,
            'direccion' => '',
            'ruc' => (string) $pedido->cliente_ruc,
            'vendedor' => '',
            'moroso' => false,
            'credito' => 0.0,
            'saldo' => 0.0,
            'disponible' => (float) $pedido->credito_disponible,
            'condicionPago' => (string) $pedido->condicion_pago,
            'nivelPrecio' => (string) $pedido->nivel_precio,
            'monedaNivel' => $pedido->moneda ?? 'L',
            'moneda' => $pedido->moneda ?? 'L',
        ]);

        // Número de Softland: el asignado; si aún no se envía, el tentativo (solo lectura).
        $numeroTentativo = null;
        if (! $pedido->softland_pedido) {
            try {
                $numeroTentativo = $consecutivo->siguiente($pedido->consecutivo());
            } catch (Throwable $e) {
                Log::warning('Consecutivo de pedido Softland: '.$e->getMessage());
            }
        }

        return view('pedidos.show', [
            'pedido' => $pedido->load('items'),
            'cliente' => $cliente,
            'numeroTentativo' => $numeroTentativo,
        ]);
    }

    public function store(Request $request, Catalogo $catalogo, Lotes $lotes, Clientes $clientes, Carrito $carrito)
    {
        $data = $request->validate([
            'contacto' => ['nullable', 'string', 'max:150'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ]);

        // Datos actuales del cliente (crédito al día; puede haberse desactivado).
        $cliente = $clientes->encontrar($carrito->cliente()->codigo);
        if (! $cliente) {
            return redirect()->route('clientes.index')
                ->with('aviso', 'El cliente ya no está activo. Elige otro para continuar el pedido.');
        }
        $carrito->fijarCliente($cliente);

        $data += [
            'cliente_codigo' => $cliente->codigo,
            'cliente' => $cliente->nombre,
            'cliente_ruc' => $cliente->ruc ?: null,
            'nivel_precio' => $cliente->nivelPrecio ?: null,
            'moneda' => $cliente->monedaNivel,
            'condicion_pago' => $cliente->condicionPago ?: null,
            // Solo comparable si el crédito está en la misma moneda que los precios.
            'credito_disponible' => $cliente->creditoComparable() ? $cliente->disponible : null,
        ];

        if ($carrito->vacio()) {
            return redirect()->route('carrito.index')
                ->with('aviso', 'Agrega al menos un artículo.');
        }

        // Fecha del pedido (calendario): puede ser anterior a hoy, dentro del rango permitido.
        if ($carrito->fecha()->lt(today()->subDays(config('softland.fecha_min_dias')))) {
            return redirect()->route('carrito.index')->withInput()
                ->with('aviso', 'La fecha del pedido ('.fecha_larga($carrito->fecha()).') es demasiado antigua. Elige otra.');
        }
        $data['fecha_pedido'] = $carrito->fecha()->format('Y-m-d');
        // Consecutivo de Softland elegido (null = el de la configuración, DESP-A).
        $data['softland_consecutivo'] = $carrito->consecutivo();

        // Revalidar contra Softland: precio, existencia y el lote elegido.
        $items = $carrito->items();
        $actuales = $catalogo->encontrarVarios(array_keys($items));
        $lotesActuales = $lotes->porArticulos(array_keys($items));
        $ajustes = [];

        foreach ($items as $codigo => $item) {
            $articulo = $actuales[$codigo] ?? null;
            $lote = $carrito->loteVigente((string) $codigo, $lotesActuales[$codigo] ?? []);
            $pedida = Carrito::capturada($item); // en su modo: unidades o cajas
            $nueva = $articulo ? $carrito->fijar($articulo, $pedida, $lote) : 0;
            if (! $articulo) {
                $carrito->quitar((string) $codigo);
            }
            $cambioLote = ($item['lote'] ?? null) !== $lote?->lote;
            // Un precio puesto a mano no se pisa con el del nivel.
            $cambioPrecio = $articulo && empty($item['precio_manual']) && $articulo->precio != $item['precio'];
            if (abs($nueva - $pedida) > 0.00001 || $cambioLote || $cambioPrecio) {
                $ajustes[] = $codigo;
            }
        }

        if ($ajustes) {
            return redirect()->route('carrito.index')
                ->withInput()
                ->with('aviso', 'La existencia, el lote o el precio cambió en '.count($ajustes).' artículo(s). Revisa el pedido antes de confirmar.')
                ->with('ajustados', $ajustes);
        }

        // Todo artículo debe llevar precio (los que no tienen en el nivel se ponen a mano).
        $sinPrecio = array_keys(array_filter($carrito->items(), Linea::sinPrecio(...)));
        if ($sinPrecio) {
            return redirect()->route('carrito.index')->withInput()
                ->with('aviso', 'Pon el precio de '.count($sinPrecio).' artículo(s) antes de confirmar.')
                ->with('ajustados', $sinPrecio);
        }

        $editandoId = $carrito->editando();

        $pedido = DB::transaction(function () use ($data, $carrito, $editandoId) {
            if ($editandoId) {
                // Modificación de un pedido guardado: solo si aún no está en Softland.
                $pedido = Pedido::query()->lockForUpdate()->find($editandoId);
                if (! $pedido || ! $pedido->puedeEnviarseASoftland()) {
                    return null;
                }
                $pedido->update($data + $this->totales($carrito) + ['softland_estado' => null, 'softland_error' => null, 'softland_pedido' => null]);
                $pedido->items()->delete();
            } else {
                $pedido = Pedido::create($data + $this->totales($carrito));
            }

            foreach ($carrito->items() as $item) {
                $pedido->items()->create($item + ['subtotal' => Carrito::subtotal($item)]);
            }

            if (! $editandoId) {
                $pedido->update(['folio' => 'P-'.str_pad((string) $pedido->id, 6, '0', STR_PAD_LEFT)]);
            }

            return $pedido;
        });

        if (! $pedido) {
            $carrito->terminar();

            return redirect()->route('pedidos.show', $editandoId)
                ->with('aviso', 'El pedido ya fue enviado a Softland y no se puede modificar.');
        }

        $carrito->terminar(); // el siguiente pedido empieza eligiendo cliente

        return redirect()->route('pedidos.show', $pedido)->with($editandoId ? 'actualizado' : 'creado', true);
    }

    /**
     * Carga un pedido guardado en el carrito para modificarlo (solo si no se
     * envió a Softland). Se revalidan precio, existencia y lote al cargarlo.
     */
    public function editar(Pedido $pedido, Clientes $clientes, Carrito $carrito)
    {
        if (! $pedido->puedeEnviarseASoftland()) {
            return redirect()->route('pedidos.show', $pedido)
                ->with('aviso', 'Este pedido ya fue enviado a Softland: no se puede modificar.');
        }

        $cliente = $pedido->cliente_codigo ? $clientes->encontrar($pedido->cliente_codigo) : null;
        if (! $cliente) {
            return redirect()->route('pedidos.show', $pedido)
                ->with('aviso', 'El cliente del pedido ya no está activo: no se puede modificar.');
        }

        $carrito->terminar();
        $carrito->fijarCliente($cliente);

        // Catálogo y lotes con el nivel de precio de este cliente.
        if ($cliente->nivelPrecio !== '') {
            config(['articulos.precio.nivel' => $cliente->nivelPrecio]);
        }
        $catalogo = app(Catalogo::class);
        $codigos = $pedido->items->pluck('codigo')->map(fn ($c) => (string) $c)->all();
        $articulos = $catalogo->encontrarVarios($codigos);
        $lotes = app(Lotes::class)->porArticulos($codigos);

        $ajustados = [];
        foreach ($pedido->items as $item) {
            $articulo = $articulos[(string) $item->codigo] ?? null;
            if (! $articulo) {
                $ajustados[] = (string) $item->codigo; // ya no está disponible: no se carga

                continue;
            }
            $disponibles = $lotes[(string) $item->codigo] ?? [];
            // Respeta el lote que tenía el pedido si sigue disponible; si no, el sugerido.
            $lote = collect($disponibles)->firstWhere('lote', $item->lote) ?? ($disponibles[0] ?? null);
            // Mismo modo de captura (unidades o cajas) y, si lo tenía, su precio manual.
            $linea = $item->comoLinea();
            $pedida = Carrito::capturada($linea);
            $aplicada = $carrito->fijar($articulo, $pedida, $lote, $linea['modo']);
            if ($linea['precio_manual'] && $aplicada > 0) {
                $carrito->fijarPrecio((string) $item->codigo, $linea['precio'], Carrito::CAJA);
            }
            $cambioPrecio = ! $linea['precio_manual'] && $articulo->precio != $item->precio;

            if (abs($aplicada - $pedida) > 0.00001 || $lote?->lote !== $item->lote || $cambioPrecio) {
                $ajustados[] = (string) $item->codigo;
            }
        }

        // Conserva su fecha (puede ser anterior a hoy).
        $carrito->editar($pedido->id, $pedido->notas, $pedido->fecha_pedido ?? today(), $pedido->softland_consecutivo);

        return redirect()->route('carrito.index')
            ->with('aviso', $ajustados
                ? 'Modificando el pedido: '.count($ajustados).' artículo(s) cambiaron de existencia, lote o precio. Revísalos.'
                : 'Modificando el pedido.')
            ->with('ajustados', $ajustados);
    }

    /** Sale de la edición sin guardar: el pedido queda como estaba. */
    public function cancelarEdicion(Carrito $carrito)
    {
        $id = $carrito->editando();
        $carrito->terminar();

        return $id
            ? redirect()->route('pedidos.show', $id)->with('aviso', 'Edición cancelada. El pedido no cambió.')
            : redirect()->route('clientes.index');
    }

    private function totales(Carrito $carrito): array
    {
        $r = $carrito->resumen();

        return ['lineas' => $r['lineas'], 'piezas' => $r['piezas'], 'total' => $r['total']];
    }
}
