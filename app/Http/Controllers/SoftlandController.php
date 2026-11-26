<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Pedidos\Carrito;
use App\Softland\ConsecutivoPedido;
use App\Softland\EnviarPedidoSoftland;
use App\Softland\PedidoSoftland;
use DomainException;
use Illuminate\Support\Facades\Log;
use Throwable;

class SoftlandController extends Controller
{
    /**
     * Inserta en Softland un pedido ya guardado en MySQL.
     * Solo por acción manual (botón con confirmación en el detalle del pedido).
     */
    public function enviar(Pedido $pedido, EnviarPedidoSoftland $envio)
    {
        try {
            $numero = $envio->enviar($pedido);
        } catch (DomainException $e) {
            Log::warning("Envío a Softland del pedido {$pedido->folio}: ".$e->getMessage());

            return redirect()->route('pedidos.show', $pedido)->with('softland_error', $e->getMessage());
        }

        Log::info("Pedido {$pedido->folio} enviado a Softland como {$numero}.");

        return redirect()->route('pedidos.show', $pedido)->with('softland_ok', $numero);
    }

    /**
     * Vista previa de lo que llevaría el pedido en Softland.umk.PEDIDO.
     * Solo lectura: no inserta ni reserva nada.
     */
    public function vistaPrevia(Carrito $carrito, ConsecutivoPedido $consecutivo, PedidoSoftland $pedido)
    {
        if ($carrito->vacio()) {
            return redirect()->route('carrito.index')->with('aviso', 'Agrega artículos para ver los datos de Softland.');
        }

        try {
            $preparado = $pedido->preparar(
                numero: $consecutivo->siguiente() ?? '—',
                clienteCodigo: $carrito->cliente()->codigo,
                items: $carrito->items(),
                ahora: now(),
                observaciones: $carrito->observaciones(),
                fechaPedido: $carrito->fecha(),
            );
            $error = null;
        } catch (Throwable $e) {
            Log::warning('Vista previa Softland: '.$e->getMessage());
            $preparado = ['encabezado' => [], 'lineas' => []];
            $error = $e->getMessage();
        }

        return view('softland.vista-previa', [
            'encabezado' => $preparado['encabezado'],
            'lineas' => $preparado['lineas'],
            'error' => $error,
            'resumen' => $carrito->resumen(),
        ]);
    }
}
