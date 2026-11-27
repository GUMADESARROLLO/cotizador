<?php

namespace App\Http\Controllers;

use App\Clientes\Clientes;
use App\Cotizaciones\CotizacionExcel;
use App\Cotizaciones\FichaCotizacion;
use App\Models\ClienteFicha;
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
        $datos = $request->validate([
            'licitacion' => ['nullable', 'string', 'max:60'],
            'nombre' => ['nullable', 'string', 'max:150'],
            'atencion' => ['nullable', 'string', 'max:150'],
            'cargo' => ['nullable', 'string', 'max:100'],
            'telefono' => ['nullable', 'string', 'max:60'],
            'fax' => ['nullable', 'string', 'max:60'],
            'responsable' => ['nullable', 'string', 'max:150'],
        ]);
        // Licitación y responsable (línea verde): de esta cotización, van con el pedido.
        // Datos del cliente: se recuerdan para sus próximas cotizaciones.
        $pedido->update([
            'licitacion' => trim((string) ($datos['licitacion'] ?? '')) ?: null,
            'responsable' => trim((string) ($datos['responsable'] ?? '')) ?: null,
        ]);
        $ficha = array_map(fn ($v) => trim((string) $v), array_intersect_key($datos + array_fill_keys(ClienteFicha::CAMPOS, ''), array_flip(ClienteFicha::CAMPOS)));
        FichaCotizacion::recordar($pedido->cliente_codigo, $ficha);

        // Teléfonos, fax y contacto al día desde Softland; si no se puede leer, sin ellos.
        try {
            $cliente = $pedido->cliente_codigo ? $clientes->encontrar($pedido->cliente_codigo) : null;
        } catch (Throwable $e) {
            Log::warning("Cotización {$pedido->id}: cliente no disponible: ".$e->getMessage());
            $cliente = null;
        }

        $pedido->load('items');
        $archivo = $excel->generar(CotizacionExcel::datos($pedido, $cliente, $ficha, PedidoController::lotesDe($pedido, app('lotes.cotizacion'))));
        $nombre = 'COTIZACION '.Str::of($pedido->licitacion ?: $pedido->numero())->replaceMatches('/[^\w.\- ]+/u', '-')->trim().'.xlsx';

        return response()->download($archivo, $nombre, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }
}
