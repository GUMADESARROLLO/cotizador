<?php

namespace App\Http\Controllers;

use App\Catalogo\Catalogo;
use App\Clientes\Clientes;
use App\Models\Pedido;
use App\Pedidos\Carrito;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function show(Pedido $pedido)
    {
        return view('pedidos.show', ['pedido' => $pedido->load('items')]);
    }

    public function store(Request $request, Catalogo $catalogo, Clientes $clientes, Carrito $carrito)
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
            'credito_disponible' => $cliente->disponible,
        ];

        if ($carrito->vacio()) {
            return redirect()->route('carrito.index')
                ->with('aviso', 'Agrega al menos un artículo.');
        }

        // Revalidar contra la existencia actual de SQL Server.
        $items = $carrito->items();
        $actuales = $catalogo->encontrarVarios(array_keys($items));
        $ajustes = [];

        foreach ($items as $codigo => $item) {
            $articulo = $actuales[$codigo] ?? null;
            $nueva = $articulo ? $carrito->fijar($articulo, $item['cantidad']) : 0;
            if (! $articulo) {
                $carrito->quitar($codigo);
            }
            if ($nueva != $item['cantidad'] || ($articulo && $articulo->precio != $item['precio'])) {
                $ajustes[] = $codigo;
            }
        }

        if ($ajustes) {
            return redirect()->route('carrito.index')
                ->withInput()
                ->with('aviso', 'La existencia o el precio cambió en '.count($ajustes).' artículo(s). Revisa el pedido antes de confirmar.')
                ->with('ajustados', $ajustes);
        }

        $pedido = DB::transaction(function () use ($data, $carrito) {
            $pedido = Pedido::create($data + $this->totales($carrito));

            foreach ($carrito->items() as $item) {
                $pedido->items()->create($item + [
                    'subtotal' => round($item['precio'] * $item['cantidad'], 2),
                ]);
            }

            $pedido->update(['folio' => 'P-'.str_pad((string) $pedido->id, 6, '0', STR_PAD_LEFT)]);

            return $pedido;
        });

        $carrito->terminar(); // el siguiente pedido empieza eligiendo cliente

        return redirect()->route('pedidos.show', $pedido)->with('creado', true);
    }

    private function totales(Carrito $carrito): array
    {
        $r = $carrito->resumen();

        return ['lineas' => $r['lineas'], 'piezas' => $r['piezas'], 'total' => $r['total']];
    }
}
