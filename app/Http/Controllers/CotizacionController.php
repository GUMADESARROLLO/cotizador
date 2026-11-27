<?php

namespace App\Http\Controllers;

use App\Clientes\Clientes;
use App\Cotizaciones\CotizacionExcel;
use App\Models\Pedido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/** Cotización en Excel de un pedido guardado (reemplaza al "Imprimir"). */
class CotizacionController extends Controller
{
    public function descargar(Request $request, Pedido $pedido, Clientes $clientes, CotizacionExcel $excel)
    {
        // N.º de licitación que comparte el cliente: se guarda para la próxima descarga.
        $datos = $request->validate(['licitacion' => ['nullable', 'string', 'max:60']]);
        $pedido->update(['licitacion' => trim((string) ($datos['licitacion'] ?? '')) ?: null]);

        // Teléfonos, fax y contacto al día desde Softland; si no se puede leer, sin ellos.
        try {
            $cliente = $pedido->cliente_codigo ? $clientes->encontrar($pedido->cliente_codigo) : null;
        } catch (Throwable $e) {
            Log::warning("Cotización {$pedido->id}: cliente no disponible: ".$e->getMessage());
            $cliente = null;
        }

        $archivo = $excel->generar(CotizacionExcel::datos($pedido->load('items'), $cliente));
        $nombre = 'COTIZACION '.Str::of($pedido->licitacion ?: $pedido->numero())->replaceMatches('/[^\w.\- ]+/u', '-')->trim().'.xlsx';

        return response()->download($archivo, $nombre, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }
}
