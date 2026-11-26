<?php

namespace App\Http\Controllers;

use App\Catalogo\Catalogo;
use App\Catalogo\Lotes;
use App\Clientes\Clientes;
use App\Models\Pedido;
use App\Pedidos\Carrito;
use App\Softland\EnviarPedidoSoftland;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PedidoController extends Controller
{
    public function index(Request $request)
    {
        $pedidos = Pedido::query()
            ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($w) => $w
                ->where('folio', 'like', "%{$t}%")
                ->orWhere('cliente', 'like', "%{$t}%")
                ->orWhere('cliente_codigo', $t)))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('pedidos.index', compact('pedidos'));
    }

    public function show(Pedido $pedido, EnviarPedidoSoftland $envio)
    {
        // Si un envío quedó a medias, se resuelve consultando Softland (sin duplicar).
        try {
            $envio->revisarEnvioTrabado($pedido);
        } catch (Throwable $e) {
            Log::warning("Revisión de envío trabado {$pedido->folio}: ".$e->getMessage());
        }

        return view('pedidos.show', ['pedido' => $pedido->load('items')]);
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

        // Revalidar contra Softland: precio, existencia y el lote elegido.
        $items = $carrito->items();
        $actuales = $catalogo->encontrarVarios(array_keys($items));
        $lotesActuales = $lotes->porArticulos(array_keys($items));
        $ajustes = [];

        foreach ($items as $codigo => $item) {
            $articulo = $actuales[$codigo] ?? null;
            $lote = $carrito->loteVigente((string) $codigo, $lotesActuales[$codigo] ?? []);
            $nueva = $articulo ? $carrito->fijar($articulo, $item['cantidad'], $lote) : 0;
            if (! $articulo) {
                $carrito->quitar((string) $codigo);
            }
            $cambioLote = ($item['lote'] ?? null) !== $lote?->lote;
            if ($nueva != $item['cantidad'] || $cambioLote || ($articulo && $articulo->precio != $item['precio'])) {
                $ajustes[] = $codigo;
            }
        }

        if ($ajustes) {
            return redirect()->route('carrito.index')
                ->withInput()
                ->with('aviso', 'La existencia, el lote o el precio cambió en '.count($ajustes).' artículo(s). Revisa el pedido antes de confirmar.')
                ->with('ajustados', $ajustes);
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
                $pedido->items()->create($item + [
                    'subtotal' => round($item['precio'] * $item['cantidad'], 2),
                ]);
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
            $aplicada = $carrito->fijar($articulo, (float) $item->cantidad, $lote);

            if ($aplicada != $item->cantidad || $lote?->lote !== $item->lote || $articulo->precio != $item->precio) {
                $ajustados[] = (string) $item->codigo;
            }
        }

        $carrito->editar($pedido->id, $pedido->notas);

        return redirect()->route('carrito.index')
            ->with('aviso', $ajustados
                ? "Modificando {$pedido->folio}: ".count($ajustados).' artículo(s) cambiaron de existencia, lote o precio. Revísalos.'
                : "Modificando {$pedido->folio}.")
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
