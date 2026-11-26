<?php

namespace App\Http\Middleware;

use App\Pedidos\Carrito;
use Closure;
use Illuminate\Http\Request;

/**
 * Los artículos y el pedido solo se usan con un cliente elegido.
 */
class RequiereCliente
{
    public function __construct(private Carrito $carrito) {}

    public function handle(Request $request, Closure $next)
    {
        if ($this->carrito->cliente()) {
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
