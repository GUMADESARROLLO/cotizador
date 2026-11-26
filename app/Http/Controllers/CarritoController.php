<?php

namespace App\Http\Controllers;

use App\Catalogo\Articulo;
use App\Catalogo\Catalogo;
use App\Catalogo\Lote;
use App\Catalogo\Lotes;
use App\Models\Pedido;
use App\Pedidos\Carrito;
use App\Softland\ConsecutivoPedido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class CarritoController extends Controller
{
    public function index(Carrito $carrito, ConsecutivoPedido $consecutivo, Lotes $lotes)
    {
        // Número tentativo que tendría el pedido en Softland (solo lectura).
        try {
            $numero = $carrito->vacio() ? null : $consecutivo->siguiente();
        } catch (Throwable $e) {
            Log::warning('Consecutivo de pedido Softland: '.$e->getMessage());
            $numero = null;
        }

        // Lotes de la bodega para elegir en cada artículo.
        try {
            $lotesPorArticulo = $carrito->vacio() ? [] : $lotes->porArticulos(array_keys($carrito->items()));
        } catch (Throwable $e) {
            Log::warning('Lotes Softland: '.$e->getMessage());
            $lotesPorArticulo = [];
        }

        return view('carrito.index', [
            'items' => $carrito->items(),
            'lotes' => $lotesPorArticulo,
            'resumen' => $carrito->resumen(),
            'cliente' => $carrito->cliente(),
            'numeroPedido' => $numero,
            'codigoConsecutivo' => $consecutivo->codigo(),
            // Pedido guardado que se está modificando (null = pedido nuevo).
            'editando' => $carrito->editando() ? Pedido::find($carrito->editando()) : null,
            'observaciones' => $carrito->observaciones(),
        ]);
    }

    /** Fija la cantidad de un artículo; 0 lo quita. Responde JSON. */
    public function fijar(Request $request, Catalogo $catalogo, Lotes $lotes, Carrito $carrito)
    {
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:50'],
            'cantidad' => ['required', 'numeric', 'min:0'],
        ]);

        $articulo = $catalogo->encontrar($data['codigo']);

        if (! $articulo) {
            $carrito->quitar($data['codigo']);

            return response()->json([
                'message' => 'El artículo ya no existe en el catálogo.',
                'resumen' => $carrito->resumen(),
            ], 404);
        }

        // Respeta el lote ya elegido; si no hay, el que vence primero.
        $lote = $carrito->loteVigente($articulo->codigo, $lotes->porArticulos([$articulo->codigo])[$articulo->codigo] ?? []);

        return $this->aplicar($carrito, $articulo, floor((float) $data['cantidad']), $lote);
    }

    /** Cambia el lote de un artículo del pedido (elección manual). Responde JSON. */
    public function lote(Request $request, Catalogo $catalogo, Lotes $lotes, Carrito $carrito)
    {
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:50'],
            'lote' => ['required', 'string', 'max:15'],
        ]);

        $item = $carrito->items()[$data['codigo']] ?? null;
        $articulo = $catalogo->encontrar($data['codigo']);
        $lote = collect($lotes->porArticulos([$data['codigo']])[$data['codigo']] ?? [])
            ->first(fn (Lote $l) => $l->lote === $data['lote']);

        if (! $item || ! $articulo || ! $lote) {
            return response()->json([
                'message' => 'Ese lote ya no tiene existencia en la bodega.',
                'resumen' => $carrito->resumen(),
            ], 422);
        }

        return $this->aplicar($carrito, $articulo, (float) $item['cantidad'], $lote);
    }

    public function vaciar(Carrito $carrito)
    {
        $carrito->vaciar();

        return redirect()->route('carrito.index');
    }

    private function aplicar(Carrito $carrito, Articulo $articulo, float $pedida, ?Lote $lote)
    {
        $aplicada = $carrito->fijar($articulo, $pedida, $lote);
        $maximo = $lote ? $lote->maximoPedible() : $articulo->maximoPedible();
        $ajustado = $aplicada < $pedida;

        return response()->json([
            'codigo' => $articulo->codigo,
            'cantidad' => $aplicada,
            'maximo' => $maximo,
            'existencia' => $articulo->existencia,
            'lote' => $lote?->toArray(),
            'subtotal' => round($aplicada * $articulo->precio, 2),
            'ajustado' => $ajustado,
            'message' => $ajustado
                ? 'Solo hay '.$this->num($maximo).' disponibles'.($lote ? " en el lote {$lote->lote}." : '.')
                : null,
            'resumen' => $carrito->resumen(),
        ]);
    }

    private function num(float $n): string
    {
        return number_format($n, 0, '.', ',');
    }
}
