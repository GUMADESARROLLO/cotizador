<?php

namespace App\Http\Middleware;

use App\Pedidos\Carrito;
use Closure;
use Illuminate\Http\Request;

/**
 * Los artículos y el pedido solo se usan con un cliente elegido, y se
 * muestran con el nivel de precio y la moneda de ese cliente.
 */
class RequiereCliente
{
    public function __construct(private Carrito $carrito) {}

    public function handle(Request $request, Closure $next)
    {
        if ($cliente = $this->carrito->cliente()) {
            // Precios del nivel del cliente (como el NIVEL_PRECIO del pedido en Softland)
            // y su moneda: L = C$, D = US$.
            if ($cliente->nivelPrecio !== '') {
                config(['articulos.precio.nivel' => $cliente->nivelPrecio]);
            }
            config(['articulos.currency' => config("softland.monedas.{$cliente->monedaNivel}", 'C$')]);

            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Primero elige un cliente.'], 409);
        }

        if ($request->ajax()) {
            return response('', 409, ['X-Redirect' => route('clientes.index')]);
        }

        return redirect()->route('clientes.index')->with('aviso', 'Primero elige el cliente del pedido.');
    }
}
