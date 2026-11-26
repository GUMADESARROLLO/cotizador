<?php

namespace App\Http\Controllers;

use App\Catalogo\Catalogo;
use App\Pedidos\Carrito;
use Illuminate\Http\Request;

class CarritoController extends Controller
{
    public function index(Carrito $carrito)
    {
        return view('carrito.index', [
            'items' => $carrito->items(),
            'resumen' => $carrito->resumen(),
            'cliente' => $carrito->cliente(),
        ]);
    }

    /** Fija la cantidad de un artículo; 0 lo quita. Responde JSON. */
    public function fijar(Request $request, Catalogo $catalogo, Carrito $carrito)
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

        $pedida = floor((float) $data['cantidad']);
        $aplicada = $carrito->fijar($articulo, $pedida);

        return response()->json([
            'codigo' => $articulo->codigo,
            'cantidad' => $aplicada,
            'maximo' => $articulo->maximoPedible(),
            'existencia' => $articulo->existencia,
            'subtotal' => round($aplicada * $articulo->precio, 2),
            'ajustado' => $aplicada < $pedida,
            'message' => $aplicada < $pedida
                ? 'Solo hay '.$this->num($articulo->maximoPedible()).' disponibles.'
                : null,
            'resumen' => $carrito->resumen(),
        ]);
    }

    public function vaciar(Carrito $carrito)
    {
        $carrito->vaciar();

        return redirect()->route('carrito.index');
    }

    private function num(float $n): string
    {
        return number_format($n, 0, '.', ',');
    }
}
